#!/usr/bin/env bash
# build-mobile.sh — Full mobile build pipeline (Android)
# Usage: ./build-mobile.sh [android|ios]
# Default: android

set -euo pipefail

TARGET="${1:-android}"
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "==> Building mobile for: $TARGET"

# Swap desktop -> mobile (using --no-update to keep deps healthy)
composer remove nativephp/desktop --no-interaction --no-update
composer require nativephp/mobile --no-interaction --no-update
composer update --no-interaction --prefer-dist

echo "==> Building frontend..."
[ -f package-lock.json ] && npm ci --ignore-scripts || npm install
npm run build

echo "==> Setting up .env..."
cp -n .env.example .env 2>/dev/null || true
grep -q "^APP_KEY=" .env 2>/dev/null || php artisan key:generate --no-interaction
echo "NATIVEPHP_APP_VERSION=$(grep -Po '\\d+\\.\\d+\\.\\d+' composer.json 2>/dev/null || echo '0.1.0')" >> .env
echo "NATIVEPHP_APP_VERSION_CODE=1" >> .env

echo "==> Installing NativePHP Mobile..."
php artisan native:install --force --no-interaction

if [ "$TARGET" = "android" ]; then
    echo "==> Fixing Android placeholders..."
    GRADLE_FILE="nativephp/android/app/build.gradle.kts"
    sed -i 's/REPLACE_COMPILE_SDK/36/g' "$GRADLE_FILE"
    sed -i 's/REPLACE_MIN_SDK/24/g' "$GRADLE_FILE"
    sed -i 's/REPLACE_TARGET_SDK/36/g' "$GRADLE_FILE"
    sed -i 's/REPLACE_APP_ID/com.myapp.app/g' "$GRADLE_FILE"
    sed -i 's/REPLACEMECODE/1/g' "$GRADLE_FILE"
    APP_VER=$(grep -Po '\\d+\\.\\d+\\.\\d+' composer.json 2>/dev/null || echo '0.1.0')
    sed -i "s/REPLACEME/${APP_VER}/g" "$GRADLE_FILE"
    sed -i 's/REPLACE_MINIFY_ENABLED/false/g' "$GRADLE_FILE"
    sed -i 's/REPLACE_SHRINK_RESOURCES/false/g' "$GRADLE_FILE"
    sed -i 's/REPLACE_DEBUG_SYMBOLS/none/g' "$GRADLE_FILE"
    echo "sdk.dir=$ANDROID_SDK_ROOT" > nativephp/android/local.properties
    MANIFEST="nativephp/android/app/src/main/AndroidManifest.xml"
    sed -i 's/android:label="NativePHP"/android:label="My App"/g' "$MANIFEST"
    sed -i 's/package="[^"]*"/package="com.myapp.app"/g' "$MANIFEST"

    echo "==> Bundling Laravel app into Android assets..."
    ASSETS_DIR="nativephp/android/app/src/main/assets"
    BUNDLE_ZIP="${ASSETS_DIR}/laravel_bundle.zip"
    STAGING="nativephp/android/laravel_staging"
    mkdir -p "${ASSETS_DIR}" "${STAGING}"
    rsync -a --delete \
      --exclude='.git' \
      --exclude='node_modules' \
      --exclude='nativephp' \
      --exclude='*.zip' \
      --exclude='*.jks' \
      . "${STAGING}/"
    composer install --no-dev --no-interaction --prefer-dist \
      --optimize-autoloader --no-scripts \
      --working-dir="${STAGING}"
    composer dump-autoload --optimize --classmap-authoritative --no-scripts \
      --working-dir="${STAGING}"
    cp vendor/nativephp/mobile/bootstrap/android/artisan.php "${STAGING}/artisan.php"
    cp .env "${STAGING}/.env"
    mkdir -p "${STAGING}/bootstrap/cache"
    mkdir -p "${STAGING}/storage/framework/"{cache,sessions,views}
    rm -f "${STAGING}/bootstrap/cache/"{packages.php,services.php}
    cd "${STAGING}" && zip -r "$(pwd)/../${BUNDLE_ZIP}" . && cd -

    echo "==> Building Android APK..."
    cd nativephp/android && chmod +x gradlew && ./gradlew assembleDebug --no-daemon
fi

echo "==> Done. APK in nativephp/android/app/build/outputs/apk/debug/"