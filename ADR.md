# ADR-001: Kernel Desktop App Architecture

**Project:** kernel-desktop-v1 — native desktop dashboard + setup wizard for kernel-evolving
**Date:** 2026-07-25
**Author:** Fabio (pacificDev), Olly

## Decision

A native desktop application (Linux AppImage, macOS dmg, Windows exe) built with **Laravel 13 + NativePHP Desktop** that provides:
1. A **setup wizard** that installs kernel-evolving on the user's machine (Docker sandbox or bare metal)
2. The **full evolution dashboard** — D3.js graphs, memory tab, workspace tab, SQLite viewer, skills/routines management
3. A **chat tab** for interacting with the local kernel-evolving agent

The app pairs with **kernel-central.neverslave.com** for authentication and serves as the bridge between the user's local kernel-evolving instance and kernel-central's proxy for mobile access.

## Context

kernel-evolving is a self-evolving AI agent that runs locally. Currently, users must manually clone the repo, configure Docker, and access a plain HTML dashboard over the network. The desktop app solves the onboarding friction by shipping a setup wizard that automates everything, then provides a rich dashboard for monitoring and interacting with the agent.

The desktop app also enables mobile connectivity: once kernel-evolving is running and paired with kernel-central, the mobile app (kernel-mobile-v1) can reach the agent from anywhere via kernel-central's WebSocket tunnel.

## Chosen platforms

- **Frontend:** Blade + Livewire 4 + Tailwind CSS 4 + D3.js (dashboard visualizations)
- **Backend (local):** Laravel 13 embedded in the native app — serves the dashboard UI, SQLite databases, and local API
- **Desktop shell:** NativePHP Desktop (Electron wrapper) — renders the Laravel app in a native window
- **Agent backend:** kernel-evolving (FastAPI, port 8779, Docker or bare metal, installed by setup wizard)
- **Proxy hub:** kernel-central.neverslave.com (Sanctum auth, WebSocket tunnel for mobile relay)
- **Database (local):** SQLite — app settings, pairing state, dashboard data cache
- **Deploy:** GitHub Releases (AppImage, dmg, exe), auto-updater via GitHub

## Architecture

```
┌──────────────────────────────────────────────────────────┐
│                    User's Machine                         │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │              kernel-desktop-v1                      │  │
│  │              Laravel 13 + NativePHP Desktop         │  │
│  │                                                    │  │
│  │  ┌──────────────────────────────────────────────┐  │  │
│  │  │ Setup Wizard (first run)                      │  │  │
│  │  │ 1. Welcome + system check                     │  │  │
│  │  │ 2. Docker detection + install if missing      │  │  │
│  │  │ 3. Pull kernel-evolving image                 │  │  │
│  │  │ 4. Configure (VRAM, models, providers)        │  │  │
│  │  │ 5. Start container                            │  │  │
│  │  │ 6. Login to kernel-central + pair device      │  │  │
│  │  │ 7. Done — launch dashboard                    │  │  │
│  │  └──────────────────────────────────────────────┘  │  │
│  │                                                    │  │
│  │  ┌──────────────────────────────────────────────┐  │  │
│  │  │ Full Dashboard (post-setup)                    │  │  │
│  │  │ ┌────────────────┐ ┌──────────────────────┐  │  │  │
│  │  │ │ Evolution Tab   │ │ Memory Tab           │  │  │  │
│  │  │ │ - D3.js graphs  │ │ - File list + CRUD   │  │  │  │
│  │  │ │ - Live stream   │ │ - Editor + preview   │  │  │  │
│  │  │ │ - Stats + logs  │ │ - Search/filter      │  │  │  │
│  │  │ └────────────────┘ └──────────────────────┘  │  │  │
│  │  │ ┌────────────────┐ ┌──────────────────────┐  │  │  │
│  │  │ │ Workspace Tab   │ │ SQLite Viewer        │  │  │  │
│  │  │ │ - File tree     │ │ - Table list         │  │  │  │
│  │  │ │ - File viewer   │ │ - Row browser        │  │  │  │
│  │  │ │ - Upload/delete │ │ - Query editor       │  │  │  │
│  │  │ └────────────────┘ └──────────────────────┘  │  │  │
│  │  │ ┌────────────────┐ ┌──────────────────────┐  │  │  │
│  │  │ │ Skills Tab      │ │ Chat Tab             │  │  │  │
│  │  │ │ - Browse/list   │ │ - Message bubbles    │  │  │  │
│  │  │ │ - Install/run   │ │ - Typing indicators  │  │  │  │
│  │  │ │ - Security scan │ │ - Voice/Image/Docs   │  │  │  │
│  │  │ └────────────────┘ └──────────────────────┘  │  │  │
│  │  │ ┌────────────────┐ ┌──────────────────────┐  │  │  │
│  │  │ │ Routines Tab    │ │ Settings Tab         │  │  │  │
│  │  │ │ - Browse/run    │ │ - Provider config    │  │  │  │
│  │  │ │ - Status/logs   │ │ - Models management  │  │  │  │
│  │  │ └────────────────┘ │ - Docker settings     │  │  │  │
│  │  │                    │ - kernel-central pair │  │  │  │
│  │  │                    └──────────────────────┘  │  │  │
│  │  └──────────────────────────────────────────────┘  │  │
│  │                                                    │  │
│  │  Local SQLite:                                     │  │
│  │  - App settings (window size, theme, pairing)      │  │
│  │  - Dashboard data cache (stats, logs, file trees)  │  │
│  │  - Chat history cache                              │  │
│  └────────────┬───────────────────────────────────┬───┘  │
│               │ localhost API (port 8779)         │       │
│               ▼                                   │       │
│  ┌────────────────────────────────────┐           │       │
│  │      kernel-evolving (Docker)      │           │       │
│  │      FastAPI, Nemotron-3B          │           │       │
│  │      Chat / Voice / Vision / Docs  │           │       │
│  │      Evolution engine / Skills     │           │       │
│  └────────────┬───────────────────────┘           │       │
└───────────────┼───────────────────────────────────┘       │
                │ WS tunnel to kernel-central                │
                ▼                                           │
┌───────────────────────────────────────────────────────────┐
│              kernel-central.neverslave.com                  │
│              - Auth (Sanctum)                              │
│              - Device pairing                              │
│              - WS tunnel for mobile relay                  │
│              - Skills catalog sync                         │
└───────────────────────────────────────────────────────────┘
```

## Main components

### Setup Wizard
- **Step 1 — Welcome**: Introduction, system requirements check (OS, RAM, disk space)
- **Step 2 — Docker**: Detect Docker installation. If missing, guide through install (Docker Desktop on Windows/Mac, apt on Linux). Verify Docker daemon is running.
- **Step 3 — Pull Image**: Pull kernel-evolving Docker image. Show progress bar. Offer sandbox vs bare-metal toggle.
- **Step 4 — Configure**: Set VRAM allocation, choose default model (Nemotron-3B, Gemma 4 E2B), configure cloud providers (OpenAI key, OpenRouter key), set voice sample preference.
- **Step 5 — Start**: Launch the kernel-evolving container. Show startup log. Wait for health check (port 8779).
- **Step 6 — Pair**: Login/register on kernel-central. Generate device pairing token. Establish WebSocket tunnel.
- **Step 7 — Done**: Launch the dashboard. Show quick-start tips.

### Dashboard Tabs
- **Evolution Tab** — D3.js force-directed graph of the self-evolution pipeline. Live log stream. Stats: iterations, skills count, installed/community ratio, critic scores. Control buttons (pause/resume/trigger/backup).
- **Memory Tab** — File browser for kernel-evolving memory (Markdown files). CRUD operations: create, rename, delete, edit with preview. Mobile-responsive layout (list on mobile, split on desktop). Search and filter.
- **Workspace Tab** — Navigable file tree of kernel-evolving workspace. Clickable folders. File viewer with syntax highlighting for code, markdown rendering for docs. Upload and delete.
- **SQLite Viewer** — List all kernel-evolving databases. Browse tables and rows. Simple query editor. Export to CSV. Read-only by default, toggleable write mode.
- **Skills Tab** — Browse installed skills with descriptions. Run skills with input parameters. View skill source code. Security scanner integration. Install from kernel-central catalog.
- **Routines Tab** — Browse and run routines. View routine definitions. See run history and logs.
- **Chat Tab** — Direct chat with the kernel-evolving agent. Message bubbles, typing indicators, streaming replies. Voice input (mic), image upload, document attach. Slash commands. Inline buttons. Matches the mobile app's chat UI.
- **Settings Tab** — Configure providers (task_inference, synthesis, critic, etc.). Model management (download, swap, unload). Docker container management (stop, restart, logs). kernel-central pairing status. Voice sample selection. Theme toggle (dark/light).

### Subsystems
- **Docker Manager** — CLI wrapper for Docker commands (check install, pull image, start/stop/restart container, view logs). Supports both Docker Desktop and CLI Docker.
- **kernel-central Pairing** — OAuth flow to kernel-central. Store Sanctum token. Establish and maintain WebSocket tunnel for mobile relay. Health check pings.
- **Auto-Updater** — Check GitHub Releases for new versions. Download and apply updates. Backup SQLite before update. Show changelog. Same pattern as note-keeper's update system.
- **API Client Layer** — HTTP client for kernel-evolving's 60+ endpoints. SSE streaming for chat and evolution. File uploads. Error handling with retry.

## Architectural decisions

| Decision | Alternative | Chosen | Rationale |
|---|---|---|---|
| Desktop framework | Electron / Tauri / PWA | NativePHP Desktop (Laravel) | Same stack as note-keeper and kernel-mobile. Shared code between desktop and mobile. Blade + Livewire already proven. |
| kernel-evolving install | Manual git clone | Docker with setup wizard | One-click onboarding. Docker sandbox provides isolation. Users don't need Python/PyTorch knowledge. |
| Dashboard rendering | Client-side SPA | Server-rendered Blade + D3.js | Existing dashboard is already HTML+D3. Blade wraps it naturally. Livewire adds reactivity without JS framework. |
| Docker vs bare metal | Docker-only | Docker default, bare metal toggle | Docker is safer (sandbox, no dependency conflicts). Power users can opt out. |
| Mobile relay | Direct LAN access | kernel-central WS tunnel | Mobile works from anywhere. Central handles auth and routing. Desktop manages the tunnel health. |
| Auto-update | Manual download | GitHub Releases auto-updater | Proven in note-keeper. Desktop app needs to stay current with kernel-evolving. |

## Constraints

- Must work on Linux (primary), macOS, and Windows
- Docker must be installable by non-technical users (wizard guides step-by-step)
- Dashboard must match the existing kernel-evolving HTML dashboard in functionality
- All tabs must be responsive (desktop app window can be resized)
- kernel-central pairing is required for mobile relay (not for local use)
- PHP 8.4+ required (NativePHP constraint)
- Desktop and mobile packages conflict — can't coexist in same composer install
- Auto-updater uses GitHub Releases — repo must be public

## What is NOT in scope

- Mobile relay without kernel-central (direct LAN → mobile is out)
- Multi-instance kernel-evolving management (v1: single local instance)
- Remote desktop access (not a remote control tool)
- The mobile chat UI (that's kernel-mobile-v1)
- Skills marketplace browsing within the app (links to kernel-central web)
- Team/collaboration features
- GPU passthrough configuration wizard (v1: auto-detect, manual config)
- Voice cloning from desktop (v1: voice in chat tab via kernel-evolving, no desktop mic clone)

## Planned future features

*(To be filled together during development)*