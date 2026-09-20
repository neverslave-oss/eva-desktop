#!/usr/bin/env node
/**
 * kernel-terminal-bridge — WebSocket sidecar for the Kernel Desktop agent
 * Terminal tab.
 *
 * Each WebSocket connection gets its own real bash PTY (via node-pty).
 * The client sends messages:
 *   { type: 'resize', cols: <int>, rows: <int> }
 *   { type: 'input',  data: '<raw string>' }
 * The server forwards PTY output back to the client as:
 *   { type: 'output', data: '<raw string>' }
 *   { type: 'exit',   code: <int|null> }
 *
 * Start:
 *   cd terminal-bridge && npm i && node server.js
 *
 * This is intentionally NOT wired into the desktop build/auto-start — it is a
 * separately runnable sidecar for this iteration.
 *
 * Port: 6090 via KERNEL_TERMINAL_PORT env override.
 */
'use strict';

const http = require('http');
const os = require('os');
const { WebSocketServer } = require('ws');
const { spawn } = require('node-pty');

const PORT = Number(process.env.KERNEL_TERMINAL_PORT || 6090);
const SHELL = process.env.SHELL || '/bin/bash';

const server = http.createServer((req, res) => {
  // Plain HTTP GET on /health returns ok so the desktop app can probe the bridge.
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true, service: 'kernel-terminal-bridge', pty: true }));
    return;
  }
  res.writeHead(200, { 'Content-Type': 'text/plain' });
  res.end('kernel-terminal-bridge ws');
});

const wss = new WebSocketServer({ server });

function defaultShellArgs() {
  if (os.platform() === 'win32') return [];
  return ['-l'];
}

wss.on('connection', (ws, req) => {
  const path = req.url || '/term/0';
  ws._termPath = path;

  const pty = spawn(SHELL, defaultShellArgs(), {
    name: 'xterm-256color',
    cols: 80,
    rows: 24,
    cwd: process.env.HOME || os.homedir(),
    env: { ...process.env, TERM: 'xterm-256color', COLORTERM: 'truecolor' },
  });

  pty.onData((data) => {
    if (ws.readyState === 1) ws.send(JSON.stringify({ type: 'output', data }));
  });

  pty.onExit(({ exitCode }) => {
    if (ws.readyState === 1) ws.send(JSON.stringify({ type: 'exit', code: exitCode }));
    ws.close();
  });

  ws.on('message', (raw) => {
    let msg;
    try {
      msg = JSON.parse(raw.toString());
    } catch (_err) {
      return;
    }
    if (msg.type === 'input' && typeof msg.data === 'string') {
      pty.write(msg.data);
    } else if (msg.type === 'resize' && Number.isFinite(msg.cols) && Number.isFinite(msg.rows)) {
      try {
        pty.resize(Math.max(2, Math.floor(msg.cols)), Math.max(1, Math.floor(msg.rows)));
      } catch (_err) { /* ignore transient resize errors */ }
    }
  });

  ws.on('close', () => {
    try { pty.kill(); } catch (_err) { /* already gone */ }
  });
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`[kernel-terminal-bridge] ws://127.0.0.1:${PORT} (${wss._server ? '' : ''}) ready — one bash PTY per connection`);
});
