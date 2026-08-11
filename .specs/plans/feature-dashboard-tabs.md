# Feature: Dashboard Tabs

## Objective
Full suite of dashboard tabs wrapping the kernel-evolving agent's existing HTML dashboard and adding native management interfaces.

## Dependencies
- Laravel 13 + Livewire 4 scaffold (complete)
- Setup wizard (for post-setup redirect)

## Components

### Evolution Tab
- [x] Full D3.js force-directed graph with skills/routines/replicas layers
- [x] Live SSE stream from /evolution/stream
- [x] Stats cards: total events, resolved, synthesised, gaps, providers
- [x] Control buttons: start/pause/resume/stop/reset
- [x] Task outcomes panel with direct/escalated/open counts
- [x] Legend with color-coded node types

### Activity Tab (new)
- [x] Timeline SVG with D3 axis
- [x] Event log with badges (T1 acquire, T2 synth, gap, routine)
- [x] Open gaps panel

### Insights Tab (new)
- [x] Stat cards (total skills, resolved, synthesised, gaps)
- [x] Chart.js canvases for resolution rate, ecosystem growth, verification
- [x] Thoughts today panel

### Agent Chat Tab
- [x] Message bubbles with user/assistant color coding
- [x] Streaming SSE responses via /message/stream
- [x] Session management with localStorage persistence
- [x] Command menu (/help, /skills, /routines, /init, /system, /models, /thoughts, /verbose, /replica, /workspaces, /new, /evolve, /version)
- [x] Provider/model routing sheet
- [x] Inspector panel (system prompt, conversation history, tool calls, trajectories)

### Memory Tab
- [x] File tree with collapse/expand
- [x] CRUD: create, rename, delete, edit with preview
- [x] Search and filter
- [x] SQLite viewer embedded (table browser, pagination)

### Workspace Tab
- [x] Navigable file tree
- [x] File viewer with edit/save/cancel

### SQLite Viewer
- [x] List kernel-evolving databases
- [x] Browse tables and rows with pagination
- [x] Clear tables

### Skills Tab
- [x] Browse installed skills with filter/search
- [x] Tier badges (T1/T2), source badges
- [x] Routines list inline

### Routines Tab
- [x] Routines list loaded from /routines API

### Replicas Tab (new)
- [x] Spawn named replicas with role selection
- [x] Active replicas list
- [x] Async pipeline runner (writer→critic)

### Trajectories Tab (new)
- [x] Trajectory list with tool counts, critic scores
- [x] Expandable details with provider, reply, tool calls

### System Tab (new)
- [x] Backup history with create backup
- [x] Workspace status with initialize
- [x] Ecosystem & configuration

### Voice Tab (new)
- [x] Voice conversation UI with wave visualization
- [x] Server selector (Kernel-Evo, AI-Server, Ollama)
- [x] Mic button with push-to-talk hint

### Settings Tab
- [x] Provider configuration (task inference, synthesis, critic)
- [x] Docker container status
- [x] Kernel-central pairing status
- [x] Theme toggle (dark/light)
- [ ] Theme toggle

## Status
[ ] Not started — skeleton Livewire components created
