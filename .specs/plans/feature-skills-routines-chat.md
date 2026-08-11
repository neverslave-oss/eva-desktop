# Feature: Skills, Routines & Chat Tabs

## Objective
Skills browser/runner, routines browser/runner, and embedded chat tab for interacting with the local kernel-evolving agent directly from the desktop dashboard.

## Dependencies
- Setup Wizard (kernel-evolving must be running)
- API client layer

## Stack
Blade + Livewire 4 + Tailwind CSS 4

## Expected output
### Skills Tab
- Browse installed skills with name, description, source
- Run skills with input parameters
- View skill source code
- Security scanner integration
- Install from kernel-central catalog

### Routines Tab
- Browse routines with name, description
- Run routines and view logs
- See run history

### Chat Tab
- Message bubbles (sent/received)
- Text input with send
- Typing indicators
- Streaming replies (SSE)
- Voice input, image upload, document attach
- Slash commands and inline buttons
- Matches kernel-mobile-v1 chat UI patterns

## Status
[x] Completed — 2026-07-28

### Notes
- Skills Tab: Full skills browser with filter/search, skill count, tier badges, source badges. Loads from kernel-evolving /skills API.
- Routines Tab: Routines list loaded from kernel-evolving /routines API. Also shown inline in Skills tab.
- Chat Tab: Full Agent Chat with message bubbles, streaming replies via SSE, session management, command menu (/help, /skills, /routines, /init, /system, /models, /thoughts, /verbose, /replica, /workspaces, /new, /evolve, /version), provider/model routing sheet, inspector panel (system prompt, conversation history, tool calls, trajectories).
- All UI matches evolution_dashboard.html exactly.

## Progress update 2026-08-11

- [x] Chat composer moved to sticky bottom layout with multiline textarea (`Enter` sends, `Shift+Enter` inserts newline)
- [x] Toolbar refreshed for chat controls with grouped session/actions buttons
- [x] Added `Fresh` action button wired to kernel-evolving `/chat/fresh`
- [x] Updated `New` action to call kernel-evolving `/chat/new` and rotate local session id
