# Feature: NativePHP Desktop Shell

## Objective
Configure NativePHP Desktop to render the Laravel app as a native desktop window with correct window title, icon, size, and app metadata.

## Dependencies
- Laravel 13 + NativePHP Desktop installed (complete)

## Stack
- NativePHP Desktop v2
- Electron (underlying shell)

## Expected output
- `php artisan native:dev` launches the desktop app
- Window title: "Kernel Desktop"
- App ID: `com.neverslave.kernel-desktop`
- Default window size: 1280x800
- Proper author and copyright metadata

## Configuration
Update `config/nativephp.php`:
- app_id: `com.neverslave.kernel-desktop`
- author: Fabio Pacifici
- copyright: (c) 2026 Neverslave
- version: 1.0.0
- deeplink_scheme: `kernel-desktop`

## Status
[ ] Not started — NativePHP scaffolding installed, needs configuration
