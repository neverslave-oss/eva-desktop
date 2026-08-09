# NativePHP Window Not Visible on Launch (2026-08-09)

## Symptom
- App process starts after installer launch.
- Main desktop window is not visible.

## Likely causes
- Persisted window state can restore to off-screen coordinates.
- App hides to tray on close, making it look like it did not open.
- On installed builds under protected folders, Laravel can fail writing `bootstrap/cache/*` manifests, which prevents normal UI startup.

## Changes made
- Updated [app/Providers/NativeAppServiceProvider.php](app/Providers/NativeAppServiceProvider.php):
  - Explicitly open main window with id `main`.
  - Removed `rememberState()` from startup chain.
  - Set a safe startup position (`80, 80`).
  - Added tray action `Show App` that calls `Window::show('main')` and repositions window to `80, 80`.
- Updated [bootstrap/app.php](bootstrap/app.php):
  - Redirected Laravel runtime cache env paths (`APP_SERVICES_CACHE`, `APP_PACKAGES_CACHE`, `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`, `APP_EVENTS_CACHE`) to a user-writable folder under `%LOCALAPPDATA%/eva-desktop/laravel-cache` (fallback: temp dir).
  - This avoids write attempts in read-only install locations.

## Verification
- File compiles with no diagnostics from workspace error checker.

## Follow-up
- Rebuild and reinstall app to apply fix in packaged installer output.
- If still hidden in older builds, use tray icon behavior or clear persisted window state in app data.
- If installing in `Program Files`, this cache-path redirect should prevent bootstrap cache permission failures.
