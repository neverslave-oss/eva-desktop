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
[ ] In progress — scaffolded with placeholder implementations
