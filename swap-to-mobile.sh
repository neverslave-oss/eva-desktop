#!/usr/bin/env bash
# swap-to-mobile.sh — Swap composer packages from desktop to mobile
# Run this in the Laravel project root
# Uses --no-update to avoid dependency resolution errors during cross-platform swaps

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "==> Removing nativephp/desktop..."
composer remove nativephp/desktop --no-interaction --no-update 2>&1 || true

echo "==> Requiring nativephp/mobile..."
composer require nativephp/mobile --no-interaction --no-update 2>&1 || true

echo "==> Running composer update (both changes applied together)..."
composer update --no-interaction --prefer-dist 2>&1 || true

echo ""
echo "==> Done. Next steps:"
echo "  1. php artisan native:install --force --no-interaction"
echo "  2. Fix Android placeholders in nativephp/android/app/build.gradle.kts"
echo "  3. Bundle Laravel app: rsync -> zip -> nativephp/android/app/src/main/assets/"
echo "  4. cd nativephp/android && ./gradlew assembleDebug --no-daemon"