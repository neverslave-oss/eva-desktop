# Feature: Evolution Dashboard Tab

## Objective
Replicate the kernel-evolving evolution dashboard — D3.js force-directed graph of self-evolution pipeline, live log stream, stats panel, and control buttons.

## Dependencies
- Setup Wizard (kernel-evolving must be running)
- API client layer

## Stack
Blade + Livewire 4 + D3.js v7

## Expected output
- D3.js evolution graph with nodes (skills, replicas, iterations) and edges
- Live log stream panel (SSE from kernel-evolving /evolution/stream)
- Stats cards: iteration count, skills count, installed/community ratio, critic scores
- Control buttons: pause/resume/trigger evolution, backup state
- Legend with color-coded node types
- Responsive — graph resizes with window
- Matches existing kernel-evolving HTML dashboard functionality

## Status
[x] Completed — 2026-07-28

### Notes
- Full evolution dashboard implemented: D3.js force-directed graph with skills/routines/replicas layers, live SSE stream, stats cards (total events, resolved, synthesised, gaps, providers), control buttons (start/pause/resume/stop/reset), task outcomes panel, and legend.
- All JS logic extracted from kernel-evolving/src/views/evolution_dashboard.html into resources/js/dashboard.js
- All CSS extracted into resources/css/dashboard.css
- API calls proxy through kapi() helper to KERNEL_API_BASE (default localhost:8779)
