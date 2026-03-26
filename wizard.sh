#!/usr/bin/env bash
# wizard.sh — Bootstrap a new Laravel project with dziurka/laravel-preset
# Usage (stable):  bash wizard.sh
# Usage (dev/main): bash wizard.sh --dev
set -euo pipefail

# ── Colours ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'
info()    { echo -e "${CYAN}→${NC} $*"; }
success() { echo -e "${GREEN}✓${NC} $*"; }
warn()    { echo -e "${YELLOW}⚠${NC}  $*"; }
die()     { echo -e "${RED}✗${NC} $*" >&2; exit 1; }

PHP_IMAGE="laravelsail/php84-composer:latest"
PRESET_PACKAGE="dziurka/laravel-preset"
PRESET_DEV=false

# ── Parse flags ───────────────────────────────────────────────────────────────
for arg in "$@"; do
    case "$arg" in
        --dev|-d) PRESET_DEV=true ;;
    esac
done

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║   🧙 Laravel Preset Wizard               ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════╝${NC}"
if [[ "$PRESET_DEV" == true ]]; then
    echo -e "  ${YELLOW}⚠  Dev mode — installing preset from GitHub main branch${NC}"
fi
echo ""

# ── Preflight: Docker ─────────────────────────────────────────────────────────
if ! command -v docker &>/dev/null; then
    die "Docker is required but not installed. Install it from https://docs.docker.com/get-docker/"
fi

if ! docker info &>/dev/null 2>&1; then
    # Capture error from both docker info and docker ps (they give different messages)
    DOCKER_ERR="$(docker info 2>&1 || true) $(docker ps 2>&1 || true)"
    if echo "$DOCKER_ERR" | grep -qi "permission denied"; then
        echo -e "${RED}✗${NC} Permission denied when accessing Docker socket." >&2
        echo "" >&2
        echo -e "  Your user is not in the ${BOLD}docker${NC} group. Fix it with:" >&2
        echo "" >&2
        echo -e "    ${BOLD}sudo usermod -aG docker \$USER${NC}" >&2
        echo -e "    ${BOLD}newgrp docker${NC}   ${CYAN}# apply without re-login (or re-login)${NC}" >&2
        echo "" >&2
        echo -e "  Then re-run the wizard." >&2
        exit 1
    else
        echo -e "${RED}✗${NC} Docker daemon is not running." >&2
        echo "" >&2
        echo -e "  Start it with one of:" >&2
        echo -e "    ${BOLD}sudo systemctl start docker${NC}   ${CYAN}# Linux${NC}" >&2
        echo -e "    Open ${BOLD}Docker Desktop${NC}               ${CYAN}# macOS / Windows${NC}" >&2
        echo "" >&2
        echo -e "  Also make sure your user is in the docker group:" >&2
        echo -e "    ${BOLD}sudo usermod -aG docker \$USER && newgrp docker${NC}" >&2
        echo "" >&2
        echo -e "  Then re-run the wizard." >&2
        exit 1
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
echo ""
echo -e "  ${YELLOW}⚠️  A few things to keep in mind during the Laravel wizard:${NC}"
echo -e "  ${YELLOW}   • 'Would you like to run the default database migrations?' — answer ${BOLD}No${NC}${YELLOW}${NC}"
echo -e "  ${YELLOW}     (no database is available yet; migrations run automatically later via 'just install')${NC}"
echo -e "  ${YELLOW}   • 'Would you like to start Sail?' — answer ${BOLD}No${NC}${YELLOW}${NC}"
echo -e "  ${YELLOW}     (we start Sail ourselves after preset installation)${NC}"
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

if [[ "$PRESET_DEV" == true ]]; then
    COMPOSER_REQUIRE="composer config repositories.laravel-preset vcs https://github.com/dziurka/laravel-preset && composer config minimum-stability dev && composer config prefer-stable true && composer require '${PRESET_PACKAGE}:dev-main' --dev --no-interaction --quiet"
else
    COMPOSER_REQUIRE="composer require '${PRESET_PACKAGE}' --dev --no-interaction --quiet"
fi

docker run --rm -it \
    -u "$UID_GID" \
    -v "$(pwd)/${PROJECT_NAME}:/var/www/html" \
    -w /var/www/html \
    "$PHP_IMAGE" \
    bash -c "
        ${COMPOSER_REQUIRE}
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
