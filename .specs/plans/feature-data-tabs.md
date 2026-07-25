# Feature: Memory, Workspace & SQLite Tabs

## Objective
Three data inspection tabs replicating the kernel-evolving dashboard panels: Memory (file CRUD), Workspace (navigable file tree), and SQLite Viewer (browse tables/rows, export).

## Dependencies
- Setup Wizard (kernel-evolving must be running)
- API client layer

## Stack
Blade + Livewire 4 + Tailwind CSS 4

## Expected output
### Memory Tab
- File list with create, rename, delete, edit operations
- Editor with preview for Markdown files
- Mobile-responsive layout (list on narrow, split on wide)
- Search and filter

### Workspace Tab
- Navigable file tree with clickable folders
- File viewer with syntax highlighting (code) and Markdown rendering (docs)
- Upload and delete files
- Path breadcrumbs

### SQLite Viewer
- List all kernel-evolving databases
- Browse tables and browse rows
- Simple query editor
- Export to CSV
- Read-only by default, toggleable write mode

## Status
[ ] Not started
