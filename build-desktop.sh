#!/usr/bin/env bash
# build-desktop.sh — Build note-keeper as native desktop app
# Usage: ./scripts/build-desktop.sh [linux|mac|windows]
# Default: linux

set -euo pipefail

TARGET="${1:-linux}"
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

cd "$ROOT_DIR"

echo "==> Installing PHP deps..."
composer install --no-interaction --prefer-dist --no-progress

echo "==> Installing JS deps..."
npm ci --ignore-scripts

echo "==> Building frontend..."
npm run build

echo "==> Caching Laravel config..."
php artisan config:cache --ansi
php artisan route:cache --ansi
php artisan view:cache --ansi

echo "==> Building native app (--$TARGET)..."
php artisan native:build --"$TARGET"

echo "==> Done! Check dist/ for artifacts"
ls -la dist/ 2>/dev/null || echo "(no dist/ folder yet)"