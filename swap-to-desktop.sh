#!/usr/bin/env bash
# swap-to-desktop.sh — Swap composer packages from mobile back to desktop
# Run this in the Laravel project root

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "==> Removing nativephp/mobile..."
composer remove nativephp/mobile --no-interaction 2>&1 || true

echo "==> Requiring nativephp/desktop..."
composer require nativephp/desktop --no-interaction 2>&1 || true

echo ""
echo "==> Done. Ready for desktop build:"
echo "  php artisan native:build <linux|mac|win> --no-interaction"