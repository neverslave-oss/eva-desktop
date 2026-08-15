# Plan: Align Settings Providers tab with kernel-evolving cloud config

## Objective
Update the desktop app's Settings → Providers tab so it matches the recent
kernel-evolving provider-routing changes (HF Router support, all 5 text
inference roles, new env vars) made on 2026-08-15.

## Background / why
kernel-evolving's `providers` block now supports:
- 5 text-inference roles: `task_inference`, `synthesis`, `critic`, `planning`,
  `trajectory_teacher` (all routable to `local`/`openai`/`anthropic`/`hf`/
  `copilot`/`openrouter` via `/provider/set`).
- A new `providers.hf_provider` (env `HF_ROUTER_PROVIDER`) that selects the HF
  Router inference provider (e.g. `deepinfra`) via a `:provider` suffix on the
  model id.
- New/extra env keys: `GITHUB_COPILOT_TOKEN`, `TMP_OPEN_AI_API_KEY`.

The desktop Providers tab currently only exposes 3 roles (`task_inference`,
`synthesis`, `critic`), omits `planning`/`trajectory_teacher`, has no UI for the
HF Router provider, and its provider dropdown omits `google`/`ollama`.

## Changes

### 1. `app/Livewire/Settings.php`
- Add props: `providerPlanning`, `providerTrajectoryTeacher` (+ `...Model`).
- Add prop `hfProvider` (default `deepinfra`).
- Add props `tmpOpenAiKey`, `githubCopilotToken`.
- Load the new roles + `hf_provider` from `/provider` routing / config.
- Extend `save()` payload to `/provider/set` with all 5 roles + `hf_provider`.
- Extend key push + persistence with the new keys.

### 2. `resources/views/livewire/settings.blade.php`
- Add dropdowns for `planning` and `trajectory_teacher` to the Providers tab.
- Add `google`/`ollama` options to every provider select.
- Add an "HF Router Provider" input (maps to `hf_provider` / `HF_ROUTER_PROVIDER`).
- Add `GITHUB_COPILOT_TOKEN` and `TMP_OPEN_AI_API_KEY` fields.

## Non-goals
- ~~Fixing the `/config/env` key-push endpoint gap~~ — RESOLVED: added a
  `/config/env` endpoint to kernel-evolving that persists keys to a dedicated
  JSON override store (workspace data/env_overrides.json) loaded into os.environ
  at startup. This avoids rewriting `.env` (no shell-parsing fragility, survives
  repo updates). The desktop's `updateProviderKeys()` now works against this
  endpoint.

## Status
[x] Completed

## Notes
- Also added `hf_provider` support to kernel-evolving's `/provider/set` endpoint
  (src/api.py) so the desktop can push the HF Router provider; live-tested:
  `POST /provider/set {"hf_provider":"deepinfra"}` → `changed: {hf_provider: deepinfra}`.
- Env overrides: new `src/core/env_overrides.py` (JSON store at
  `~/.kernel-evolving/workspace/data/env_overrides.json`), loaded at API startup
  and written by `/config/env`. `.env` is never rewritten.
- Verified: Settings.php + KernelEvolvingService.php pass `php -l`; api.py +
  env_overrides.py pass `py_compile`; no editor errors in Settings.php or
  settings.blade.php.
