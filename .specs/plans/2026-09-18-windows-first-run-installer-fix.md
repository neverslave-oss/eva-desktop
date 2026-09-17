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
- [ ] Run targeted tests.

## Validation notes
- Workspace diagnostics report no errors in the touched PHP/config/test files.
- Targeted Pest execution is currently blocked in this checkout because `vendor/bin/pest` is unavailable.