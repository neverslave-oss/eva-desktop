#!/bin/bash
# ============================================================
# php_build_environment.sh
# Check + install dependencies for any Laravel + NativePHP project.
# Intended for Debian/Ubuntu-based CI runners or dev machines.
#
# Usage: copy to project root, then:
#   ./php_build_environment.sh [project-dir]
#   (default: parent of script's directory)
# ============================================================
set -euo pipefail

PROJECT_DIR="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
PHP_REQUIRED="${PHP_REQUIRED:-8.4}"

# Colors
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'

print_status() { echo -e "\n==> $1"; }
print_ok()     { echo -e "  ${GREEN}[✔]${NC} $1"; }
print_skip()   { echo -e "  ${YELLOW}[-]${NC} $1"; }
is_apt()       { command -v apt-get &>/dev/null; }

# ── 0. Ensure apt can find PHP 8.4+ ──
# Many distros (Kali, recent Debian/Ubuntu) already ship PHP 8.4 in their own
# repos. Only add a third-party source if the required PHP is genuinely missing.
ensure_php_repo() {
    [ "$(id -u)" = "0" ] && SUDO="" || SUDO="sudo"
    # Fast path: is the required PHP already available from any configured repo?
    if apt-cache show php${PHP_REQUIRED}-cli &>/dev/null; then
        print_ok "php${PHP_REQUIRED}-cli already available in apt"
        return 0
    fi
    # Otherwise add the canonical PHP source for this distro family.
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        case "$ID" in
            ubuntu|linuxmint|pop)
                print_status "Adding ondrej/php PPA (for PHP $PHP_REQUIRED+)..."
                $SUDO apt-get update -qq
                $SUDO apt-get install -y -qq software-properties-common
                $SUDO add-apt-repository -y ppa:ondrej/php
                $SUDO apt-get update -qq
                ;;
            debian|kali|parrot|raspbian)
                # Kali/Parrot are Debian-derived but use their own codename
                # (e.g. "kali-rolling") which sury doesn't publish. Pin to a
                # Debian codename that matches the underlying base.
                case "$ID" in
                    kali|parrot) SURY_CODENAME="trixie" ;;
                    *)            SURY_CODENAME="$(lsb_release -sc)" ;;
                esac
                print_status "Adding sury PHP repo ($SURY_CODENAME) for PHP $PHP_REQUIRED+..."
                $SUDO apt-get update -qq
                $SUDO apt-get install -y -qq ca-certificates curl gnupg
                $SUDO install -dm755 /etc/apt/keyrings
                curl -fsSL https://packages.sury.org/php/apt.gpg | $SUDO gpg --dearmor -o /etc/apt/keyrings/sury-php.gpg
                echo "deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php $SURY_CODENAME main" | $SUDO tee /etc/apt/sources.list.d/sury-php.list >/dev/null
                $SUDO apt-get update -qq
                ;;
            *)
                print_status "Adding sury PHP repo (fallback) for PHP $PHP_REQUIRED+..."
                $SUDO apt-get update -qq
                $SUDO apt-get install -y -qq ca-certificates lsb-release curl gnupg
                $SUDO install -dm755 /etc/apt/keyrings
                curl -fsSL https://packages.sury.org/php/apt.gpg | $SUDO gpg --dearmor -o /etc/apt/keyrings/sury-php.gpg
                echo "deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php $(lsb_release -sc) main" | $SUDO tee /etc/apt/sources.list.d/sury-php.list >/dev/null
                $SUDO apt-get update -qq
                ;;
        esac
    fi
}

# ── 1. PHP ──
print_status "PHP ($PHP_REQUIRED+)..."
INSTALLED_PHP=$(php -r "echo PHP_VERSION;" 2>/dev/null || echo "")
if [ -z "$INSTALLED_PHP" ]; then
    echo "PHP not found. Installing..."
    is_apt && { ensure_php_repo; sudo apt-get install -y php$PHP_REQUIRED-cli php$PHP_REQUIRED-mbstring php$PHP_REQUIRED-xml php$PHP_REQUIRED-sqlite3 php$PHP_REQUIRED-curl php$PHP_REQUIRED-zip; }
else
    print_ok "$INSTALLED_PHP"
    if php -r "exit(version_compare(PHP_VERSION, '$PHP_REQUIRED') >= 0 ? 0 : 1);"; then
        print_ok "Version OK"
    else
        echo -e "${YELLOW}WARNING: PHP $INSTALLED_PHP < $PHP_REQUIRED — installing $PHP_REQUIRED${NC}"
        is_apt && { ensure_php_repo; sudo apt-get install -y php$PHP_REQUIRED-cli php$PHP_REQUIRED-mbstring php$PHP_REQUIRED-xml php$PHP_REQUIRED-sqlite3 php$PHP_REQUIRED-curl php$PHP_REQUIRED-zip; }
        # Re-check after install: update-alternatives so `php` points to the new version
        if command -v update-alternatives &>/dev/null; then
            sudo update-alternatives --set php /usr/bin/php$PHP_REQUIRED 2>/dev/null || true
        fi
        INSTALLED_PHP=$(php -r "echo PHP_VERSION;" 2>/dev/null || echo "")
        print_ok "Now using $INSTALLED_PHP"
    fi
    MISSING=""
    for ext in mbstring xml sqlite3 curl zip; do
        php -m | grep -qi "$ext" || MISSING="$MISSING php$PHP_REQUIRED-$ext"
    done
    [ -n "$MISSING" ] && is_apt && { ensure_php_repo; sudo apt-get install -y $MISSING; }
fi

# ── 2. SQLite CLI ──
print_status "SQLite..."
if command -v sqlite3 &>/dev/null; then
    print_ok "$(sqlite3 --version | awk '{print $1}')"
else
    is_apt && sudo apt-get install -y sqlite3
fi

# ── 3. Composer ──
print_status "Composer..."
if command -v composer &>/dev/null; then
    print_ok "$(composer --version | awk '{print $3}')"
else
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php && sudo mv composer.phar /usr/local/bin/composer
    php -r "unlink('composer-setup.php');"
fi

# ── 4. Node.js ──
print_status "Node.js..."
if command -v node &>/dev/null; then
    print_ok "$(node --version) / npm $(npm --version)"
else
    is_apt && sudo apt-get install -y nodejs npm
fi

# ── 5. .env ──
print_status ".env..."
cd "$PROJECT_DIR"
if [ ! -f .env ]; then
    cp .env.example .env 2>/dev/null && print_ok "Created from .env.example" || print_skip "No .env.example found"
fi

# ── 6. Composer deps ──
print_status "Composer install..."
composer install --no-interaction --prefer-dist --no-progress
print_ok "Done"

# ── 7. APP_KEY ──
print_status "APP_KEY..."
grep -q "^APP_KEY=base64" .env 2>/dev/null || { php artisan key:generate --no-interaction; print_ok "Generated"; }

# ── 8. Permissions ──
print_status "Permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# ── 9. npm deps ──
print_status "npm install..."
[ -f package-lock.json ] && npm ci --no-audit --no-fund || npm install --no-audit --no-fund
print_ok "Done"

# ── 10. Vite build ──
print_status "Frontend..."
[ -f vite.config.js ] && npm run build && print_ok "Built" || print_skip "No vite.config.js"

# ── Done ──
echo ""
print_status "Ready."
echo "  dev:  php artisan serve"
echo "  desktop:  php artisan native:build --linux|--mac|--windows"
echo "  mobile:   php artisan native:build --android|--ios"