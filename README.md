# Kernel Desktop v1

[![Hippocratic License HL3-LAW-MIL-SV](https://img.shields.io/static/v1?label=Hippocratic%20License&message=HL3-LAW-MIL-SV&labelColor=5e2751&color=bc8c3d)](https://firstdonoharm.dev/version/3/0/law-mil-sv.html)

Native desktop app for installing, monitoring, and chatting with your kernel-evolving AI agent.

Built with **Laravel 13 + NativePHP Desktop**.

## Features

### Setup Wizard
One-click kernel-evolving installation — Docker detection, image pull, configuration, container start, and kernel-central pairing.

### Full Dashboard
- 🧬 **Evolution Tab** — D3.js force-directed graph, live log stream, control panel
- 🧠 **Memory Tab** — File browser with CRUD, editor, search
- 📁 **Workspace Tab** — Navigable file tree, file viewer with syntax highlighting
- 🗄️ **SQLite Viewer** — Browse databases, tables, rows, export CSV
- 🔧 **Skills Tab** — Browse, run, install, security scan
- ⚙️ **Routines Tab** — Browse and run routines with logs
- 💬 **Chat Tab** — Direct chat with kernel-evolving (matches mobile app UI)
- ⚙️ **Settings Tab** — Providers, models, Docker, pairing, themes, updates

### Auto-Update
Checks GitHub Releases, downloads and installs updates with automatic database backup.

## How It Works

```
Desktop app → starts kernel-evolving (Docker) → pairs with kernel-central → mobile can connect
```

The desktop app is the bridge — it runs kernel-evolving locally and maintains the WebSocket tunnel to kernel-central so your mobile app (kernel-mobile-v1) can reach it from anywhere.

## Build

```bash
# Environment setup
chmod +x php_build_environment.sh && ./php_build_environment.sh

# Build Linux AppImage
./build-desktop.sh linux

# Build macOS dmg
./build-desktop.sh mac

# Build Windows exe
./build-desktop.sh windows
```

## Related

- [kernel-evolving](https://github.com/fabiopacifici-bot/kernel-evolving) — the AI agent
- [kernel-central](https://kernel-central.neverslave.com) — proxy hub + skills catalog
- [kernel-mobile-v1](https://github.com/fabiopacifici-bot/kernel-mobile-v1) — mobile chat app

---

Built by [Fabio Pacifici](https://github.com/fabiopacifici-bot) · Part of the NSA Agency ecosystem
