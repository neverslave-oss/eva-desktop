# Feature: Dashboard Tabs

## Objective
Full suite of dashboard tabs wrapping the kernel-evolving agent's existing HTML dashboard and adding native management interfaces.

## Dependencies
- Laravel 13 + Livewire 4 scaffold (complete)
- Setup wizard (for post-setup redirect)

## Components

### Evolution Tab
- [ ] Embed existing kernel-evolving dashboard via iframe (`/evolution/dashboard`)
- [ ] Quick stats: status, container name, port, pairing state

### Chat Tab
- [ ] Message bubble UI with user/assistant color coding
- [ ] Send messages to kernel-evolving FastAPI chat endpoint
- [ ] Streaming response display

### Memory Tab
- [ ] File browser for kernel-evolving memory directory
- [ ] CRUD: create, rename, delete, edit with preview
- [ ] Search and filter

### Workspace Tab
- [ ] Navigable file tree
- [ ] File viewer with syntax highlighting
- [ ] Upload and delete

### SQLite Viewer
- [ ] List kernel-evolving databases
- [ ] Browse tables and rows
- [ ] Query editor
- [ ] CSV export

### Skills Tab
- [ ] Browse installed skills
- [ ] Run skills with input parameters
- [ ] View skill source code

### Routines Tab
- [ ] Browse and run routines
- [ ] View run history

### Settings Tab
- [ ] Provider configuration (task inference, synthesis, critic)
- [ ] Model management
- [ ] Docker container management
- [ ] Kernel-central pairing status
- [ ] Theme toggle

## Status
[ ] Not started — skeleton Livewire components created
