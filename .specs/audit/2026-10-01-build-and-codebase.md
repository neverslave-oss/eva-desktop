# Kernel Desktop v1 — Audit (2026-10-01)

**Repo:** kernel-desktop-v1 · **Branch audited:** main @ `91dde36` · **Identity:** fabiopacifici-bot (private origin)
**Scope:** repo structure/docs, codebase quality, ADR freshness, build path, tests. One-time full pass; no code changed.

---

## 1. Solid / well-crafted — don't touch

- **Setup-wizard gating is genuinely well-built.** `EnsureSetupCompleted` middleware + `NativeAppServiceProvider::ensureDatabaseReady()` + versioned `setup_completed_version` (SetupWizard.php:479-482) form a coherent, correct first-run/upgrade flow. The recent fix that always runs `migrate --force` on boot (rather than only when DB is empty) correctly prevents the "no such table: app_settings" crash. Good.
- **TunnelService is a careful, well-structured WebSocket client.** Clear lifecycle, Pusher protocol handling (ping/pong, subscribe, auth), exponential backoff cap, and clean state machine. The unit tests for `wsEndpoint` URL construction are sensible and green.
- **MessageForwarderService (KD-003) is clean and correctly tested.** Response completion via kernel-central HTTP endpoint + Bearer token is sound. The Feature test asserts both the forward and the completion request with exact payloads.
- **CI separation of concerns is good.** Test job is separate from tagged-only build jobs (linux/mac/windows) and a release job. Artifact paths and `needs:` graph are correct.
- **`php_build_environment.sh` is thorough** — handles distro-specific PHP repo setup (Debian/Kali/Ubuntu), SQLite, composer, node, env, perms. Real care went into it.
- **Config cleanliness:** `.env.example`, `.gitignore`, `nativephp.php` `cleanup_env_keys` (secret scrubbing before packaging) — well considered.

## 2. Fragile / incomplete / missing — to fix

### 🔴 CRITICAL — CI build blockers

**C1. Windows CI uses `composer update` instead of `composer install`.**
`.github/workflows/build.yml:199` runs `composer update` for the windows job, while Linux (45, 93) and macOS (146) run `composer install`. Composer lock exists (373042 bytes). Update ignores the lock, can pull incompatible dependency versions, and — critically — triggers `post-update-cmd` which force-runs `native:install`. This makes the Windows build nondeterministic vs the locked Linux/mac builds and is a likely source of Windows-specific build failure.

**C2. `post-update-cmd` force-runs `native:install` on every composer update** (composer.json). Combined with C1, the Windows job's `composer update` fires `native:install --force --quiet --publish` as a side effect, then the job also runs `php artisan native:install` explicitly (build.yml). Redundant and can conflict.

### 🟠 MEDIUM — runtime/robustness

**M1. `TunnelService::__destruct()` throws a fatal at process teardown.**
Confirmed by running the project's own suite: exit 255 with
`ReflectionException: Target class [log] does not exist` originating at `TunnelService.php:527` (`disconnect()` → `setState()` → `Log`) invoked from `__destruct()` after the container is torn down. Any long-running process (native app, queue worker) that drops the last TunnelService reference after shutdown hits this. Should not call facades (Log) from a destructor — guard with a `Log::spy()`-safe check or resolve the logger before teardown.

**M2. Scaffold `ExampleTest` fails — `/` returns 500, not 200.**
`Tests/Feature/ExampleTest` "the application returns a successful response" asserts 200 but the root route now returns 500. The `/` route is the SetupWizard behind `setup.completed` middleware; the test is stale relative to the new routing and either the test or the route needs reconciliation. Left as-is it blocks CI's test signal.

**M3. Long files, weak separation in Livewire tabs.**
`app/Livewire/Settings.php` is 838 lines, `SetupWizard.php` 505, `EvolutionDashboard`/tabs are large. Multiple concerns (view logic + service calls + state) in one component. Not urgent, but the largest are refactor candidates. `KernelEvolvingService.php` (542) and `TunnelService.php` (549) also carry a lot.

**M4. `TunnelService` WS endpoint falls back to literal `kernel-central.neverslave.com`** if `config('kernel-desktop.central.url')` is unset (`TunnelService.php:55`) — a silent production assumption embedded in code, not config. Low, but worth moving fully to config.

## 3. What's left to be "demo-ready"

- **Resolve the failing ExampleTest (M2)** and confirm the full suite is green in CI — this is the visible "tests failing" gap.
- **Fix the Windows `composer update` inconsistency (C1/C2)** so all three OS builds use the same locked dependency path. Until then the Windows artifact is not trustworthy.
- **Harden `TunnelService` teardown (M1)** so the native app / queue worker can exit cleanly without a fatal.
- **Confirm an end-to-end demo path actually builds an artifact** — nothing here proves a tagged release has produced a working AppImage/dmg/exe yet (no artifacts in repo, no release evidence). The build jobs exist but there's no verified successful run captured.

---

## Evidence index
- Build workflow: `.github/workflows/build.yml` (composer update at :199 vs install at :45/:93/:146)
- `composer.json` post-update-cmd: `native:install --force`
- Destructor fatal: `app/Services/TunnelService.php:527` (`__destruct` → `disconnect` → `setState` → `Log`)
- Test failure: `tests/Feature/ExampleTest.php`
- Line lengths: `app/Livewire/Settings.php` 838, `SetupWizard.php` 505, `KernelEvolvingService.php` 542, `TunnelService.php` 549
- WS fallback host literal: `app/Services/TunnelService.php:55`
