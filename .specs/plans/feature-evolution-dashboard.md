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
[ ] Not started
