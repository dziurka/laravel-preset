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

if ! docker info &>/dev/null 2>&1; then
    if docker info 2>&1 | grep -q "permission denied"; then
        echo -e "${RED}✗${NC} Permission denied when accessing Docker socket." >&2
        echo "" >&2
        echo -e "  Your user is not in the ${BOLD}docker${NC} group. Fix it with:" >&2
        echo "" >&2
        echo -e "    ${BOLD}sudo usermod -aG docker \$USER${NC}" >&2
        echo -e "    ${BOLD}newgrp docker${NC}   ${CYAN}# apply without re-login${NC}" >&2
        echo "" >&2
        echo -e "  Then re-run the wizard." >&2
        exit 1
    else
        die "Docker daemon is not running. Start Docker Desktop (or 'sudo systemctl start docker') and try again."
    fi
fi

success "Docker is available."

# ── Preflight: just ───────────────────────────────────────────────────────────
if ! command -v just &>/dev/null; then
    warn "'just' command runner is not installed."
    echo ""
    read -rp "$(echo -e "Install ${BOLD}just${NC} now? [Y/n]: ")" INSTALL_JUST
    INSTALL_JUST="${INSTALL_JUST:-Y}"

    if [[ "$INSTALL_JUST" =~ ^[Yy]$ ]]; then
        if command -v brew &>/dev/null; then
            info "Installing just via Homebrew..."
            brew install just
        else
            info "Installing just via official installer..."
            mkdir -p "$HOME/.local/bin"
            curl --proto '=https' --tlsv1.2 -sSf https://just.systems/install.sh \
                | bash -s -- --to "$HOME/.local/bin"
            export PATH="$HOME/.local/bin:$PATH"
        fi
        success "just installed."
    else
        die "'just' is required to complete the setup. Install it from https://just.systems and re-run."
    fi
fi

success "just is available."

# ── Pull image ────────────────────────────────────────────────────────────────
echo ""
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

UID_GID="$(id -u):$(id -g)"

# ── Create Laravel project ────────────────────────────────────────────────────
echo ""
info "Installing Laravel installer and creating project '${PROJECT_NAME}'..."
echo -e "  ${YELLOW}(Laravel wizard: answer its questions — choose your starter kit, DB etc.)${NC}"
echo -e "  ${YELLOW}(When asked 'Would you like to start Sail?' — answer NO, we'll do it next)${NC}"
echo ""

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
info "Installing ${PRESET_PACKAGE} and running preset wizard..."
echo -e "  ${YELLOW}(You'll be asked about DB driver, PHP version etc.)${NC}"
echo ""

docker run --rm -it \
    -u "$UID_GID" \
    -v "$(pwd)/${PROJECT_NAME}:/var/www/html" \
    -w /var/www/html \
    "$PHP_IMAGE" \
    bash -c "
        composer require '${PRESET_PACKAGE}' --dev --no-interaction --quiet
        php artisan preset:install
    "

success "Preset installed."

# ── Configure project and start Sail ─────────────────────────────────────────
echo ""
info "Configuring project and starting Sail (this may take a minute)..."

cd "$PROJECT_NAME"
just install "$PROJECT_NAME"

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}${BOLD}║   ✅ Your Laravel project is ready!                  ║${NC}"
echo -e "${GREEN}${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Project:  ${BOLD}./${PROJECT_NAME}${NC}"
echo -e "  App URL:  ${BOLD}http://${PROJECT_NAME}.local${NC}"
echo ""
echo -e "  Useful commands (from the project directory):"
echo -e "    ${CYAN}just up${NC}          — start containers"
echo -e "    ${CYAN}just down${NC}        — stop containers"
echo -e "    ${CYAN}just shell${NC}       — open shell in app container"
echo -e "    ${CYAN}just migrate${NC}     — run migrations"
echo -e "    ${CYAN}just test${NC}        — run tests"
echo ""
