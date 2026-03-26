#!/usr/bin/env bash
# wizard.sh — Bootstrap a new Laravel project with dziurka/laravel-preset
# Usage: bash <(curl -sSL https://raw.githubusercontent.com/dziurka/laravel-preset/main/wizard.sh)
set -euo pipefail

# ── Colours ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'
info()    { echo -e "${CYAN}→${NC} $*"; }
success() { echo -e "${GREEN}✓${NC} $*"; }
warn()    { echo -e "${YELLOW}⚠${NC}  $*"; }
die()     { echo -e "${RED}✗${NC} $*" >&2; exit 1; }

PHP_IMAGE="laravelsail/php84-composer:latest"
PRESET_PACKAGE="dziurka/laravel-preset"

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   🧙 Laravel Preset Wizard               ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════╝${NC}"
echo ""

# ── Preflight: Docker ─────────────────────────────────────────────────────────
if ! command -v docker &>/dev/null; then
    die "Docker is required but not installed. Install it from https://docs.docker.com/get-docker/"
fi

if ! docker info &>/dev/null; then
    die "Docker daemon is not running. Start Docker and try again."
fi

success "Docker is available."

# ── Pull image ────────────────────────────────────────────────────────────────
info "Pulling Docker image ${PHP_IMAGE}..."
docker pull --quiet "$PHP_IMAGE"
success "Image ready."

# ── Project name ──────────────────────────────────────────────────────────────
echo ""
while true; do
    read -rp "$(echo -e "${BOLD}Project name${NC} (e.g. my-app): ")" PROJECT_NAME
    if [[ -z "$PROJECT_NAME" ]]; then
        warn "Project name cannot be empty."
    elif [[ -d "$PROJECT_NAME" ]]; then
        warn "Directory '${PROJECT_NAME}' already exists. Choose a different name."
    else
        break
    fi
done

# ── Create Laravel project ────────────────────────────────────────────────────
echo ""
info "Installing Laravel installer and creating project '${PROJECT_NAME}'..."
echo -e "  ${YELLOW}(The Laravel wizard will start — answer its questions below)${NC}"
echo ""

UID_GID="$(id -u):$(id -g)"

docker run --rm -it \
    -u "$UID_GID" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    "$PHP_IMAGE" \
    bash -c "
        composer global require laravel/installer --quiet 2>/dev/null
        export PATH=\"\$HOME/.composer/vendor/bin:\$PATH\"
        laravel new '${PROJECT_NAME}'
    "

if [[ ! -d "$PROJECT_NAME" ]]; then
    die "Laravel project was not created. Check the output above for errors."
fi

success "Laravel project '${PROJECT_NAME}' created."

# ── Install preset ────────────────────────────────────────────────────────────
echo ""
info "Installing ${PRESET_PACKAGE}..."

docker run --rm -it \
    -u "$UID_GID" \
    -v "$(pwd)/${PROJECT_NAME}:/var/www/html" \
    -w /var/www/html \
    "$PHP_IMAGE" \
    bash -c "
        composer require '${PRESET_PACKAGE}' --dev --no-interaction --quiet
        php artisan preset:install
    "

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}${BOLD}✅ All done!${NC}"
echo ""
echo -e "  Your project is ready in ${BOLD}./${PROJECT_NAME}${NC}"
echo ""
echo -e "  Next steps:"
echo -e "    ${CYAN}cd ${PROJECT_NAME}${NC}"
echo -e "    ${CYAN}just install ${PROJECT_NAME}${NC}   # configure .env, start Sail, migrate"
echo ""
