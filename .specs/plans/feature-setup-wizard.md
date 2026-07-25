# Feature: Setup Wizard

## Objective
First-run wizard that installs kernel-evolving on the user's machine — Docker detection/install, image pull, configuration, container startup, and kernel-central pairing.

## Dependencies
- Laravel 13 project scaffold (composer create-project, nativephp/desktop installed)
- Docker CLI available on host

## Stack
Blade + Livewire 4 + Tailwind CSS 4 + Docker CLI

## Expected output
- Step 1: Welcome screen with system requirements check
- Step 2: Docker detection — install guide if missing (Docker Desktop on Win/Mac, apt on Linux)
- Step 3: Pull kernel-evolving Docker image with progress bar
- Step 4: Configure VRAM, default model, cloud providers, voice sample
- Step 5: Start container with live startup log
- Step 6: Login to kernel-central + generate device pairing token + establish WS tunnel
- Step 7: Done — launch dashboard with quick-start tips
- Wizard remembers state — can be skipped on subsequent launches

## Status
[ ] Not started
