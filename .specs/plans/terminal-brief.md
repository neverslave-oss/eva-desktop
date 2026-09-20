# Builder Brief — Agent shell terminal + sessions + font (feat/agent-shell-sessions-font)

## Context (verified, not from memory)
- Repo: `kernel-desktop-v1` — Laravel 13 + Livewire + NativePHP (Electron), WSL2 Kali host.
- Agent tab = `app/Livewire/Chat.php` + `resources/views/livewire/chat.blade.php`.
- Chat flow: JS builds a thread, sends `POST /message` to kernel-evolving (`http://localhost:8779`) with `{message, chat_id}`. Chat.php uses `chat_id` (uuid). The session pill in the topbar shows `#agent-session-id`.
- Right sidebar is the tabbed viewers area (Files / Map / Browser / Use) in `chat.blade.php`.
- kernel-evolving stores conversations in SQLite via `src/database/agent/conversations.py` (table `conversations`: id, chat_id, title, created_at, updated_at; repo methods upsert/get/list_by_chat). Chat history per chat_id is stored elsewhere; list conversations to get titles.
- We are ON branch `feat/agent-shell-sessions-font` (already checkout out on kernel-desktop-v1).

## Deliverables (all three on this branch)

### 1. Integrated shell with multiple terminal tabs — REAL terminal (xterm.js + Node PTY sidecar)
Architecture (do NOT substitute with a fake/console-only terminal):
- Add a small Node sidecar under `terminal-bridge/` in kernel-desktop-v1:
  - `server.js` — a `ws` WebSocket server (port e.g. 6090) that, per connection, spawns a PTY via `node-pty` running `bash` (env TERM=xterm-256color). Each connected client = one terminal. Handle resize messages (`{type:'resize',cols,rows}`) and stdin (`{type:'input',data}`), broadcast PTY output.
  - `package.json` in terminal-bridge with deps `ws` and `node-pty`.
  - README or comment noting how to start: `cd terminal-bridge && npm i && node server.js`. Do NOT wire auto-start into the build yet (keep as a separately-runnable sidecar for this iteration; note it in the PR).
- Frontend: add xterm.js to the chat view. Load `xterm.js` (and css) from a static asset — vendor into `public/vendor/xterm/` (download xterm 5.x js+css) or npm. If npm is cleaner, add to package.json devDeps and import via Vite. Preference: vendored static files under `public/vendor/xterm/` to avoid build friction — include exact file paths used.
- Add a **Terminal tab** in the right sidebar viewers (`data-tab="terminal"`, label `🖥️` or `┃` + "Terminal"). The panel has:
  - A tab strip to open + switch multiple terminal instances (buttons: "+ New", one tab per open terminal, closeable).
  - Each terminal = one xterm.js instance connected to one WS (per-tab port/route). Provide WS URL routing so each tab opens its own PTY (e.g. `ws://127.0.0.1:6090/term/1`, `/term/2`, ...). Keep it simple: each tab opens a distinct websocket path; server treats each connection as its own bash.
- Styling must match the existing dark theme (bg `#0b1117`/`#0f1720`, monospace).

### 2. Agent tab — enlarge text font by 2px
- Bump the base message/text font sizes in `chat.blade.php` by +2px each (message text, composer input, thread). Apply to the main readable text (thread messages, input). Keep the tiny labels as-is. Be surgical; show the exact CSS rules changed.

### 3. Sessions / Conversations tab (multi-session switching)
- Add a **Sessions** control in the agent tab (either a new sidebar "💬 Sessions" tab OR a dropdown in the topbar next to the session pill — pick the cleaner; recommend a "💬 Sessions" sidebar tab but integrate with the existing session pill).
- Backend (kernel-evolving repo):
  - Add an endpoint (e.g. `GET /api/sessions` returning list of conversations sorted by updated_at desc, with id/chat_id/title/updated_at, and `POST /api/sessions` to create a new one returning its id/chat_id). Implement against the existing `ConversationsRepository` in `src/database/agent/conversations.py`. Add tests in kernel-evolving for list/create (mirror existing test style — check tests/).
  - Ensure the repo's DB path is the one the running agent uses (find how the app wires ConversationsRepository — check app startup / services).
- Frontend (kernel-desktop-v1):
  - Session list UI: fetch sessions from the new endpoint (add a route + controller or proxy in kernel-desktop-v1, or call agent directly at 8779 — prefer a small proxy route in the Laravel app so CORS/host is consistent, mirroring existing `/debug/prompt-logs` proxy style).
  - Allow switching the active chat_id (click a session → set current chat_id, reload/refresh thread, update session pill id + label). Allow creating a new session (button "New Session" → creates via API → becomes active, clears thread).
  - Persist active chat_id selection so a reload keeps the current session (localStorage or Livewire state — localStorage is fine).
- Font +2px also applies to the sessions UI text.

## Constraints (from AGENTS.md / workspace rules)
- Work ONLY on the branch `feat/agent-shell-sessions-font` in kernel-desktop-v1. Do NOT push. Do NOT merge. Commit locally on the branch.
- For kernel-evolving changes: work on a separate branch there too (e.g. `feat/sessions-api`), commit locally, do NOT push.
- DO NOT write custom ad-hoc scripts to test functionality. Use the project test suites (tests/ in each repo). Add/run properly isolated tests.
- Tests must be isolated (dedicated test DB). 
- Do not re-implement existing features — reuse `ConversationsRepository`.
- If you hit a genuine blocker outside your scope, STOP and report it — do not hack around it with destructive changes.
- Prefer minimal, surgical changes. Read the existing code before editing.

## Output
Return a structured summary:
1. Files changed (repo + path) for each of the 3 features.
2. How to run/verify each (command sequences).
3. Test results (which suites ran, green?).
4. Any open questions / assumptions / blockers.
5. Commit hash(es) on the branch (kernel-desktop-v1 AND kernel-evolving if touched).
