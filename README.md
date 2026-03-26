# 🛠 dziurka/laravel-preset

> Scaffolding for fresh Laravel 13 projects. Installs packages, copies configuration files, sets up the environment and configures CI/CD — in a single command.

---

## 🚀 Quick Start (wizard)

Run the following command in your terminal. The only requirement is **Docker**.

```sh
curl -sSL https://raw.githubusercontent.com/dziurka/laravel-preset/main/wizard.sh -o /tmp/laravel-wizard.sh && bash /tmp/laravel-wizard.sh
```

> 💡 The script is written in pure Bash — works in any shell (bash, zsh, fish, sh).

The wizard will guide you through:

1. ✅ Requirements check (Docker, just)
2. 🆕 `laravel new` — interactive Laravel 13 project creator
3. 📦 `php artisan preset:install` — preset installation
4. 🤖 `php artisan boost:install` — AI agent integration setup
5. 🏗 `just install <project-name>` — first run (build, migrate, seed)

When finished you have a ready project with Docker, CI/CD, justfile and a configured environment.

---

## 📦 What the preset installs

### Composer packages — production

Installed by `laravel new` (starter kit) — not duplicated by the preset:

| Package | Description |
|---------|-------------|
| `inertiajs/inertia-laravel` | Server-side Inertia.js adapter |
| `laravel/wayfinder` | Typed route helpers for Vue/TypeScript |

Add manually when Laravel 13 support is available:

| Package | Description |
|---------|-------------|
| `spatie/laravel-data` | Typed data objects / DTOs *(waiting for v5)* |

### Composer packages — development

| Package | Description |
|---------|-------------|
| `laravel/sail` | Docker-based local development environment |
| `laravel/pint` | Code style fixer (Laravel preset) |
| `laravel/pail` | Real-time log viewer |
| `larastan/larastan` | Static analysis (PHPStan for Laravel) |
| `barryvdh/laravel-ide-helper` | IDE model auto-completion |
| `brianium/paratest` | Parallel PHPUnit test runner |
| `laravel/boost` | AI agent integration (Copilot, Claude, Cursor…) |
| `deployer/deployer` | *(optional)* Automated server deployments |

### Copied files

| File / directory | Description |
|------------------|-------------|
| `justfile` | Shortcuts for Sail, testing, linting and deployment |
| `docker-compose.yml` | Stack: PHP, MariaDB 11 / PostgreSQL 17, Valkey, Mailpit |
| `docker/` | PHP 8.3/8.4 Dockerfiles, php.ini, Supervisor config |
| `.env.example` | Pre-configured environment variables |
| `.env.pipelines` | Environment file for CI/CD |
| `.github/workflows/app.yml` | GitHub Actions pipeline (lint → test → deploy) |
| `.github/copilot-instructions.md` | Best practices for GitHub Copilot |
| `deploy.yaml` | *(optional)* Deployer configuration |
| `deploy/` | *(optional)* Provisioning and deployment scripts |

### 🐳 Docker stack

| Service | Image | Environment |
|---------|-------|-------------|
| `app` | PHP 8.3 / 8.4 (Sail) | local |
| `mariadb` | `mariadb:11` | local |
| `pgsql` | `postgres:17-alpine` | local |
| `valkey` | `valkey/valkey:8-alpine` | local |
| `mailpit` | `axllent/mailpit` | local / staging |

> 📬 Mailpit (local email client) runs only on local and staging environments. Production requires an external provider (Mailgun, Postmark, SES, SMTP).

### ⚙️ Default environment variables

| Variable | Value | Description |
|----------|-------|-------------|
| `SESSION_DRIVER` | `redis` | Sessions stored in Valkey |
| `QUEUE_CONNECTION` | `horizon` | Queues handled by Laravel Horizon |
| `CACHE_STORE` | `redis` | Cache stored in Valkey |
| `REDIS_HOST` | `valkey` | Valkey service name in Docker |
| `DB_HOST` | `mariadb` / `pgsql` | Based on the chosen database |
| `MAIL_HOST` | `mailpit` | Local email client |

---

## 🔧 Requirements

- **Docker** — the only requirement to run the wizard
- PHP `^8.3` (Sail provides PHP inside the container)
- Laravel `^13.0`

---

## 🛠 Manual installation (without wizard)

If you already have an existing Laravel 13 project:

```bash
# 1. Install the preset
composer require dziurka/laravel-preset --dev

# 2. Run the installer
php artisan preset:install
```

The installer will ask:

1. 🗄 **Database driver** — MySQL/MariaDB or PostgreSQL
2. 🐘 **PHP version (local / Sail)** — 8.4 *(recommended)* or 8.3
3. 🖥 **PHP version (production)** — 8.4 or 8.3
4. 🚢 **Deployer** — optional deployment tool installation. If selected, the installer will also ask:
   - 📦 Git repository URL (e.g. `git@github.com:your-org/your-app.git`)
   - 🖥 Production server IP or hostname
   - 🎭 Staging server IP or hostname
5. 🤖 **Boost** — AI agent integration setup

---

## 💻 Daily development

```bash
just build          # Build Docker images (first run)
just install myapp  # Full project setup (up, migrate, seed)
```

| Command | Description |
|---------|-------------|
| `just up` / `just down` | Start / stop containers |
| `just shell` | Shell into the app container |
| `just tinker` | Open Laravel Tinker REPL |
| `just fresh` | Fresh migrate + seed |
| `just migrate-rollback` | Roll back the last migration batch |
| `just cache-clear` | Clear config, route, view and app cache |
| `just test` | Run all tests in parallel |
| `just test MyClass` | Run a single test class |
| `just test-coverage` | Generate HTML coverage report |
| `just pint` | Fix code style |
| `just pint check=true` | Dry-run Pint (CI mode) |
| `just phpstan` | Run static analysis |
| `just lint` | Pint + PHPStan |
| `just check` | Lint + tests |
| `just pre-commit` | Fresh migrate + IDE helpers + lint + tests |
| `just artisan "route:list"` | Run any artisan command |
| `just ide` | Generate IDE helper model docblocks |
| `just provision staging` | Provision a server (first-time setup) |
| `just secrets staging` | Set GitHub Secrets via gh CLI |
| `just deploy staging` | Deploy to an environment |

---

## 🌐 Deployment (requires Deployer)

### 1️⃣ Configure deploy.yaml

`deploy.yaml` is pre-filled with values you entered during `preset:install` (repository URL, server hostnames, PHP version, database driver). Review and adjust if needed:

```yaml
config:
  repository: 'git@github.com:your-org/your-app.git'

hosts:
  production:
    hostname: '1.2.3.4'   # production server IP
  staging:
    hostname: '5.6.7.8'   # staging server IP
```

> 💡 If you skipped any field during installation, look for `# REQUIRED` comments in `deploy.yaml`.

> 💡 **Multiple environments on one VPS?** Fully supported! Each environment gets its own:
> - Deployment directory (`/var/www/myapp-production`, `/var/www/myapp-staging`)
> - Supervisor config (`horizon-production`, `horizon-staging`)
> - Valkey database (`REDIS_DB=0` for production, `REDIS_DB=1` for staging)

#### Interactive prompts vs pre-configured values

Some provisioning tasks ask interactively for credentials (e.g. Mailpit password, basic auth). You can skip prompts by setting values in `deploy.yaml`:

```yaml
# Mailpit (staging)
mailpit_user: admin
mailpit_password: secret

# Basic auth (staging)
basic_auth_user: admin
basic_auth_password: secret
```

If a value is **not** set in `deploy.yaml`, the task will ask during provisioning.  
Pre-configuring values is useful for **unattended / CI-driven provisioning**.

> ⚠️ Never commit real passwords to the repository.

---

### 2️⃣ Provision the server (first time only)

```bash
just shell
./vendor/bin/dep provision staging      # or: just provision staging
./vendor/bin/dep provision production   # repeat for production
```

Provisioning runs the following tasks in order:

| Task | What it does |
|------|-------------|
| `provision:sudoers` | Grants `deployer` passwordless sudo |
| `provision:packages` | Installs PHP, extensions, unzip, micro |
| `provision:yarn` | Installs Yarn (official APT repo) |
| `provision:valkey` | Installs Valkey (official APT repo) |
| `provision:mailpit` | Installs Mailpit + Supervisor + Caddy proxy (**staging only** — `mailpit_enabled: true`) |
| `provision:basic-auth` | Adds HTTP Basic Auth via Caddy (**staging only** — `basic_auth_enabled: true`) |
| `provision:permissions` | Sets ownership of the deployment directory |
| `provision:horizon` | Creates Supervisor config for Laravel Horizon |
| `provision:github` | Generates deploy SSH key, prints configuration |
| `provision:github-secrets` | Sets GitHub Secrets automatically (if `gh` is available) |

---

### 3️⃣ Configure GitHub

#### Option A — Automated (recommended): gh CLI

Install the [GitHub CLI](https://cli.github.com/) and log in **before** starting Sail:

```bash
gh auth login
```

Provisioning will automatically set all secrets. You can also run it standalone:

```bash
just secrets staging
just secrets production
```

> 💡 If `gh` is not installed, `provision:github-secrets` will offer to install it via [webi.sh](https://webinstall.dev/gh/).  
> After installation it will check your authentication status — if you are not logged in, it will pause and display the command to run (`gh auth login`). Once authenticated, press Enter to continue and the secrets will be set automatically.

Secrets set per environment:

| Secret | Description |
|--------|-------------|
| `SSH_KEY_STAGING` | Private deploy key for staging |
| `KNOWN_HOSTS_STAGING` | Staging server fingerprint |
| `SSH_KEY_PRODUCTION` | Private deploy key for production |
| `KNOWN_HOSTS_PRODUCTION` | Production server fingerprint |

> 🔒 Each server gets its own independently generated key pair — keys are never shared between environments.

#### Option B — Manual: GitHub UI

After provisioning, the `provision:github` task prints three values. Copy each to the appropriate place:

**1. Deploy key** — allows the server to `git pull`

> GitHub → repo → **Settings → Deploy keys → Add deploy key**
> - Title: `deployer@your-server (staging)`
> - Key: paste the `ssh-ed25519 …` public key
> - ☑ Allow write access: **leave unchecked**

**2. Private SSH key** — allows GitHub Actions to connect to the server

> GitHub → repo → **Settings → Secrets and variables → Actions → New repository secret**
> - Name: `SSH_KEY_STAGING`
> - Secret: paste the full `-----BEGIN OPENSSH PRIVATE KEY-----` block

Repeat with `SSH_KEY_PRODUCTION` for the production server.

**3. Known hosts** — prevents host verification prompts in CI

> GitHub → repo → **Settings → Secrets and variables → Actions → New repository secret**
> - Name: `KNOWN_HOSTS_STAGING`
> - Secret: paste the `ssh-ed25519 …` line from `ssh-keyscan`

Repeat with `KNOWN_HOSTS_PRODUCTION` for the production server.

---

### 4️⃣ Deploy

Once secrets are in place:

```bash
# Push to main → automatic staging deploy
git push origin main

# Publish a GitHub Release → automatic production deploy

# Or manually:
just deploy staging
just deploy production
```

> ⚠️ **Deployer is optional.** If not installed (`./vendor/bin/dep` does not exist), the deploy steps in the pipeline are skipped with instructions on how to add it.

---

## 🤖 AI Agent (laravel/boost)

The preset installs `laravel/boost` and runs the `boost:install` wizard which configures integration with your AI agent:

- **GitHub Copilot** (VS Code / JetBrains)
- **Cursor**
- **Claude** (Anthropic)
- **Gemini CLI**
- and more…

Boost generates the appropriate config files (`.mcp.json`, `CLAUDE.md` etc.) for the chosen tool.

Every project also receives `.github/copilot-instructions.md` with best practices (no facades, strict typing, model and test conventions).

---

## 🔍 Troubleshooting

**Docker build fails**
```bash
just down
docker system prune -f
just build
```

**Tests fail with database errors**  
Make sure `.env` exists, then run `just fresh`.

**Deployment SSH key issues**  
Run `just provision staging` again, then `just secrets staging` to update GitHub.

**`gh secret set` — permission denied**  
Make sure you have `admin` or `write` access to the repository and that `gh auth login` used a token with the `repo` scope.

**Provisioning fails midway**  
Most tasks are idempotent (safe to re-run). Fix the issue and run `just provision staging` again — completed steps will be skipped automatically.

**wizard.sh doesn't start on fish / zsh**  
Use the download-to-file form — works in any shell:
```sh
curl -sSL https://raw.githubusercontent.com/dziurka/laravel-preset/main/wizard.sh -o /tmp/laravel-wizard.sh && bash /tmp/laravel-wizard.sh
```

