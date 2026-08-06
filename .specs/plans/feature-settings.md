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

---

## Addendum 2026-08-06 — XP scope items (prioritised bugs + cross-repo onboarding)

The following items from the cross-repo audit have been added to the roadmap for Settings and the SetupWizard. See the full audit at `kernel-evolving/.specs/audits/AUDIT-2026-08-06-crossplatform-onboarding-pairing.md`.

### XP2 — Model storage path (wizard + settings)
`KernelEvolvingService::writeEnvFile()` has a `MODELS_PATH` placeholder that is never populated from user input. Users have no way to set `HF_HOME` (the HuggingFace model cache root) from the wizard.
- Add `modelsPath` public field to `SetupWizard.php`
- Expose in wizard step 3 (local/Docker setup section) with a folder picker or text input
- Wire into `writeEnvFile()` as `HF_HOME=<modelsPath>` (replacing dead placeholder)
- Docker mode: pass as a named volume mount in compose (server-side already supports a named volume)
- Also expose in Settings → Model Management section for post-install changes

### XP3 — Provider API key management (settings, partially wizard)
`Settings.php::save()` is a complete stub — it emits a `saved` event with no persistence at all.
- Implement `save()`: read current provider config from kernel-evolving `/config` endpoint (or local cached copy), apply changes, write back via `KernelEvolvingService::updateProviderKeys()`
- Show existing keys masked (first 4 + last 4 chars visible)
- Add/replace flow for each provider key
- Store locally in kernel-evolving's config (keys are never sent to kernel-central)
- This implements the "API keys management" bullet already in the plan above

### XP4 — Guided Telegram bot setup
See dedicated plan: `feature-telegram-guided-setup.md`
Summary: add BotFather link + `testTelegramConnection()` Livewire action + gate Next on test pass.

### XP6a — Collective memory URL
See dedicated plan: `feature-collective-memory-setup.md`
Summary: add `collectiveMemoryUrl` field + `testCollectiveMemory()` action + persist to `config.yaml`.
The kernel-evolving HTTP client (XP6b) is already complete and live.

### XP1b — TunnelService reconnect backoff fix
See existing plan: `2026-08-02-tunnelservice-reconnectattempts-never-incremented-.md`

### XP1c — Dead port-append fix
See existing plan: `2026-08-03-dead-code-makes-wsendpoint-emit-80-443-in-websocke.md`

### XP1d — MessageForwarderService endpoint + response parsing fix
See existing plan: `2026-08-06-messageforwarderservice-calls-nonexistent-chat-mess.md`

