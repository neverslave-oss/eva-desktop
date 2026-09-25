# Plan: Windows First-Run Installer Fix

## Goal
Restore the intended first-run experience for packaged desktop builds and make the Windows installer show the normal guided install flow.

## Scope
- Route fresh installs to the setup wizard instead of the chat tab.
- Persist explicit setup completion state for future launches.
- Keep existing installs landing on the current post-setup page.
- Configure the Windows NSIS build to allow install-directory selection.

## Acceptance criteria
- Fresh app state opens the `setup-wizard` route on launch.
- Completed or legacy installs continue to open the post-setup route.
- Finishing the wizard persists a `setup_completed` marker.
- NativePHP NSIS config disables one-click install and allows directory changes.

## Steps
- [x] Add startup-route decision logic in `app/Providers/NativeAppServiceProvider.php`.
- [x] Persist `setup_completed` in `app/Livewire/SetupWizard.php`.
- [x] Add an existence helper to `app/Models/AppSetting.php` for legacy detection.
- [x] Update `config/nativephp.php` NSIS options.
- [x] Add focused Pest coverage for startup routing and installer config.
- [x] Run targeted tests.

## Follow-up fix (2026-09-25) — leftover-data false positive
Even after the guided installer worked, the setup wizard was skipped on a machine
that had stale app data from a previous app identity/install. Root cause: `hasCompletedSetup()`
treated the mere presence of legacy keys (`install_mode`/`install_dir`/`workspace_path`)
as proof of completion, so leftover data skipped the wizard.

Fix (version-aware first-run detection):
- `NativeAppServiceProvider::hasCompletedSetup()` now only counts the explicit
  `setup_completed` marker. Legacy installs (marker present, no version) are
  preserved as complete; a fresh install or an app upgrade (marker present but
  `setup_completed_version` != current `nativephp.version`) re-runs the wizard.
- `SetupWizard::finish()` now also persists `setup_completed_version` =
  `config('nativephp.version')`.

## Follow-up fix (2026-09-25) — "no such table: app_settings" crash
Installed-app log (2026-09-20) showed the real reason the app didn't open after
install:

```
SQLSTATE[HY000]: General error: 1 no such table: app_settings
(Connection: nativephp, Database: .../database/nativephp.sqlite,
 SQL: select * from "app_settings" ...)
```

The app runs against the `nativephp` connection (`database/nativephp.sqlite`),
which NativePHP registers and sets as the default. `SetupWizard::mount()` →
`AppSetting::get('central_url')` crashed because `app_settings` didn't exist.

Root cause in `ensureDatabaseReady()`: it only ran `migrate` when the DB had
ZERO tables (`if (empty($tables))`), and it swallowed all exceptions. A partially
migrated DB (or a failed/skipped migration) left `app_settings` missing, then the
wizard crashed on mount.

Fix:
- `ensureDatabaseReady()` now runs `Artisan::call('migrate')` unconditionally on
  every boot (idempotent — Laravel tracks applied migrations). This guarantees
  `app_settings` exists before the wizard mounts, and no longer swallows the error
  that previously let the app proceed into a guaranteed crash.

## Validation notes
- Workspace diagnostics report no errors in the touched PHP/config/test files.
- `vendor/bin/pest tests/Feature/NativeAppStartupTest.php tests/Feature/EnsureDatabaseReadyTest.php`
  → 8 passed / 12 assertions.
- New `EnsureDatabaseReadyTest.php` (no RefreshDatabase — running migrate conflicts
  with the test-DB transaction) verifies `ensureDatabaseReady()` creates `app_settings`
  on a fresh database.
- Unrelated pre-existing teardown error: `TunnelService::__destruct()` calls the
  `Log` facade after the container is gone, producing a `Class "log" does not exist`
  fatal AFTER the suite finishes. Not caused by this change.