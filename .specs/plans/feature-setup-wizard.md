# Feature: Setup Wizard

## Objective
7-step first-run wizard that automates kernel-evolving installation (Docker check, image pull, configuration, container start, kernel-central pairing).

## Dependencies
- Laravel 13 + Livewire 4 scaffold (complete)
- NativePHP Desktop shell (complete)

## Stack
- Livewire 4 component (`App\Livewire\SetupWizard`)
- Blade view with step-by-step UI
- Tailwind CSS 4 for styling

## Expected output
- / route serves the setup wizard as the default first-run page
- Step navigation (previous/next) works
- Docker detection via `docker --version` CLI call
- Configuration form saves VRAM, model, API keys
- Container start and kernel-central pairing are wired (placeholder implementations)
- On completion, redirects to /dashboard

## Status
[x] Completed — 2026-07-28

### Notes
- Full 7-step setup wizard implemented with real system detection:
  - Step 1: Welcome — detects if API already running (skips to Done)
  - Step 2: System Check — real Docker, Docker Compose, GPU detection via nvidia-smi
  - Step 3: Install Agent — clones repo, writes .env + config.yaml, runs install.sh
  - Step 4: Configure — VRAM, inference mode, API keys, Telegram, evolution toggle
  - Step 5: Start Agent — runs start.sh (bare-metal) or docker compose up (Docker), polls health
  - Step 6: Pair Device — kernel-central pairing (placeholder for OAuth)
  - Step 7: Done — redirects to dashboard
- Created App\Services\KernelEvolvingService with full lifecycle management
- Supports both Docker (docker compose) and bare-metal (install.sh + start.sh) modes
- GPU detection correctly identifies RTX 4090 (16 GB VRAM)
