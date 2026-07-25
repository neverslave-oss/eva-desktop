# Feature: Settings & System Management

## Objective
Settings panel for configuring providers, models, Docker container, kernel-central pairing, voice samples, and theme — plus an auto-update system for the desktop app itself.

## Dependencies
- Setup Wizard (initial config done)
- API client layer

## Stack
Blade + Livewire 4 + Tailwind CSS 4 + Docker CLI

## Expected output
### Provider Config
- Set provider per capability: task_inference, synthesis, critic, planning, trajectory_teacher, vision, stt, tts
- Provider model selection (live catalog from kernel-central)
- API keys management (stored locally, never sent to central)

### Model Management
- List available local models (downloaded in HF cache)
- Download new models from HuggingFace
- Hot-swap active model (unload current, load new)
- Set generation mode (AR, diffusion, linear_spec)

### Docker Management
- Container status indicator (running/stopped/error)
- Start/stop/restart buttons
- View container logs
- Resource usage (CPU, RAM, VRAM)

### kernel-central Pairing
- Login/logout from kernel-central
- Device name and pairing status
- WebSocket tunnel health
- Re-pair flow

### Voice & Theme
- Voice sample selection
- Test voice clone
- Dark/light theme toggle

### Auto-Update
- Check for updates on GitHub Releases
- Download progress
- Install with DB backup
- Changelog display
- Same pattern as note-keeper update system

## Status
[ ] Not started
