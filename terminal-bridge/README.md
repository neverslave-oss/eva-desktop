# kernel-terminal-bridge

WebSocket sidecar that exposes a **real bash PTY** per connection for the Kernel Desktop
agent Terminal tab.

## How it works

Each WebSocket connection spawns its own `bash` PTY via [`node-pty`](https://www.npmjs.com/package/node-pty).
The desktop frontend (one `xterm.js` instance per tab) connects to a distinct WS path
(`ws://127.0.0.1:6090/term/1`, `/term/2`, …) so every tab is its own terminal.

## Start

```bash
cd terminal-bridge
npm i
node server.js
```

- Listens on `127.0.0.1:6090` (override with `KERNEL_TERMINAL_PORT`).
- `GET /health` returns `{"ok":true,...}` for simple probing.

## Wire protocol (JSON over WS)

Client → server:

```json
{ "type": "resize", "cols": 120, "rows": 30 }
{ "type": "input",  "data": "ls -la\r" }
```

Server → client:

```json
{ "type": "output", "data": "..." }
{ "type": "exit",   "code": 0 }
```

## Notes for this iteration

Deliberately **not** wired into the desktop build / auto-start — it is a separately
runnable sidecar. A future PR can add a launch hook (e.g. start it alongside the
NativePHP shell) when the terminal tab is first opened.
