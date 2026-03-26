# laravel-preset

> **New project? Run this in your terminal** (requires Docker):
> ```sh
> curl -sSL https://raw.githubusercontent.com/dziurka/laravel-preset/main/wizard.sh -o /tmp/laravel-wizard.sh && bash /tmp/laravel-wizard.sh
> ```

A Composer package that scaffolds a fresh Laravel project with custom configuration, dependencies, and files - in a single command.

## What it installs

### Composer packages
| Package | Purpose |
|---------|---------|
| `inertiajs/inertia-laravel` | Server-side Inertia.js adapter |
| `laravel/wayfinder` | Typed route helpers for Vue/TypeScript |
| `spatie/laravel-data` | Typed data objects / DTOs |
| `larastan/larastan` | PHPStan static analysis for Laravel |
| `laravel/pint` | Code style fixer (Laravel preset) |
| `barryvdh/laravel-ide-helper` | IDE model helpers |
| `laravel/sail` | Docker development environment |
| `laravel/pail` | Real-time log viewer |
| `brianium/paratest` | Parallel PHPUnit runner |

### Files copied
- `justfile` — shortcuts for Sail, testing, linting, deployment
- `docker-compose.yml` — Sail stack: chosen PHP version, MariaDB 11 / PostgreSQL 17, Redis 7, Mailpit
- `docker/` — PHP Dockerfiles (8.2–8.4), php.ini, Supervisor config
- `.env.example` — pre-configured with correct drivers and hostnames
- `.env.pipelines` — CI/CD environment file
- `.github/workflows/app.yml` — GitHub Actions pipeline (lint → test → deploy)

### Environment configuration
Automatically sets in `.env` and `.env.example`:
- `SESSION_DRIVER=database`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=redis`
- `DB_HOST=mariadb` or `DB_HOST=pgsql`
- `REDIS_HOST=redis`
- `MAIL_HOST=mailpit`

### Frontend
Adds `@inertiajs/vue3`, `vue`, `@vitejs/plugin-vue`, `vue-tsc` to `package.json` and runs `yarn install`.

### Optional: Deployer
When prompted, installs `deployer/deployer` and copies `deploy.yaml` + `deploy/` scripts for staging/production deployments via Deployer PHP.

## Requirements

- PHP ^8.2
- Laravel ^12.0
- Yarn (for frontend dependencies)

## Installation

```bash
# 1. Create a fresh Laravel project
laravel new my-app
cd my-app

# 2. Require the preset
composer require dziurka/laravel-preset --dev

# 3. Run the installer
php artisan preset:install
```

The installer will ask:

1. **Database driver** — MySQL / MariaDB or PostgreSQL
2. **PHP version for local development (Sail)** — 8.4 (recommended), 8.3, or 8.2
3. **PHP version for production** — 8.4, 8.3, or 8.2
4. **Install Deployer?** — optional, can be skipped

## After installation

```bash
# Copy and configure environment
cp .env.example .env

# Install Composer deps via Docker (no local PHP needed)
just build

# Full project setup (starts Sail, migrates, seeds)
just install <project-name>
```

## Daily development

| Command | Description |
|---------|-------------|
| `just up` / `just down` | Start / stop Docker containers |
| `just shell` | Shell into the app container |
| `just tinker` | Open Laravel Tinker REPL |
| `just fresh` | Fresh migrate + seed |
| `just migrate-rollback` | Rollback last migration batch |
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
| `just provision staging` | Provision a server for first use |
| `just secrets staging` | Set GitHub Secrets via gh CLI |
| `just deploy staging` | Deploy to an environment |

## Deployment (if Deployer was installed)

### 1. Configure deploy.yaml

Fill in `repository` and server `hostname` values:

```yaml
config:
  repository: 'git@github.com:your-org/your-app.git'

hosts:
  production:
    hostname: '1.2.3.4'   # your production server IP
  staging:
    hostname: '5.6.7.8'   # your staging server IP
```

#### Interactive prompts vs pre-configured values

Some provisioning tasks ask questions interactively (e.g. Mailpit credentials, basic auth password). You can skip these prompts by setting the values directly in `deploy.yaml`:

```yaml
# Mailpit credentials (staging)
mailpit_user: admin
mailpit_password: secret

# Basic auth for the staging app
basic_auth_user: admin
basic_auth_password: secret
```

If a value is **not** set in `deploy.yaml`, the task will stop and ask during provisioning. This is useful for sensitive credentials you don't want committed. Pre-configuring values is useful for **unattended / CI-driven provisioning**.

> ⚠️ Never commit real passwords to the repository — use the interactive prompt or a secrets manager instead.

### 2. Provision the server (first time only)

The server must have the `deployer` user, PHP-FPM, Caddy, Redis and Mailpit set up. Run provisioning from inside the container:

```bash
just shell
./vendor/bin/dep provision staging    # or: just provision staging
./vendor/bin/dep provision production # repeat for production
```

Provisioning runs these tasks in order:

| Task | What it does |
|------|-------------|
| `provision:sudoers` | Grants `deployer` passwordless sudo |
| `provision:packages` | Installs PHP extensions, unzip, micro |
| `provision:yarn` | Installs Yarn via official APT repo |
| `provision:redis` | Installs Redis via official APT repo |
| `provision:mailpit` | Installs Mailpit + Supervisor + Caddy reverse proxy (**staging only** — `mailpit_enabled: true`) |
| `provision:basic-auth` | Adds HTTP basic auth to the app via Caddy (**staging only** — `basic_auth_enabled: true`) |
| `provision:permissions` | Fixes ownership of deploy path |
| `provision:horizon` | Creates Supervisor config for Laravel Horizon |
| `provision:github` | Generates deploy SSH key, prints configuration |
| `provision:github-secrets` | Sets GitHub Secrets automatically (if `gh` is available) |

### 3. Configure GitHub

#### Option A — Automated (recommended): gh CLI

Install the [GitHub CLI](https://cli.github.com/) and log in **before** starting Sail:

```bash
gh auth login
```

Then provisioning will automatically set all secrets. You can also run it standalone:

```bash
just secrets staging
just secrets production
```

This sets the following for each environment:
- **`SSH_KEY_*`** — private deploy key (used by GitHub Actions to SSH into the server)
- **`KNOWN_HOSTS_*`** — server fingerprint (prevents MITM warnings in CI)
- **Deploy key** — public key added to the repository (allows the server to `git pull`)

#### Option B — Manual: GitHub UI

After provisioning, the `provision:github` task prints three values. Copy each to the appropriate place:

**1. Deploy key** (allows server to clone the repository)

> GitHub → your repo → **Settings → Deploy keys → Add deploy key**
> - Title: `deployer@your-server (staging)`
> - Key: paste the `ssh-ed25519 ...` public key
> - ☑ Allow write access: **leave unchecked** (read-only is enough)

**2. Private SSH key** (allows GitHub Actions to connect to the server)

> GitHub → your repo → **Settings → Secrets and variables → Actions → New repository secret**
> - Name: `SSH_KEY_STAGING`
> - Secret: paste the full `-----BEGIN OPENSSH PRIVATE KEY-----` block

Repeat with `SSH_KEY_PRODUCTION` for the production server.

**3. Known hosts** (prevents host verification prompts in CI)

> GitHub → your repo → **Settings → Secrets and variables → Actions → New repository secret**
> - Name: `KNOWN_HOSTS_STAGING`
> - Secret: paste the `ssh-ed25519 ...` line from `ssh-keyscan`

Repeat with `KNOWN_HOSTS_PRODUCTION` for the production server.

#### Full list of required secrets

| Secret | Description | How to get it |
|--------|-------------|---------------|
| `SSH_KEY_STAGING` | Private deploy key for staging | `cat /home/deployer/.ssh/deployer_rsa` on staging |
| `KNOWN_HOSTS_STAGING` | Server fingerprint for staging | `ssh-keyscan -t ed25519 <staging-ip>` |
| `SSH_KEY_PRODUCTION` | Private deploy key for production | `cat /home/deployer/.ssh/deployer_rsa` on production |
| `KNOWN_HOSTS_PRODUCTION` | Server fingerprint for production | `ssh-keyscan -t ed25519 <production-ip>` |

> **Note:** Each server gets its own independently generated key pair. The same key is not shared between staging and production.

### 4. Deploy

Once secrets are in place, push to `main` to trigger a staging deploy, or publish a GitHub Release to deploy to production:

```bash
just deploy staging
just deploy production
```

## Troubleshooting

**Docker build fails**
```bash
just down
docker system prune -f
just build
```

**Tests fail with database errors**
Ensure `.env` is present and run `just fresh` to re-create tables.

**Deployment SSH key issues**
Run `just provision staging` again to regenerate keys, then `just secrets staging` to update GitHub.

**`gh secret set` permission denied**
Ensure you have `admin` or `write` access to the repository and that `gh auth login` used a token with `repo` scope.

**Provisioning fails midway**
Most tasks are idempotent (safe to re-run). Fix the underlying issue and run `just provision staging` again — completed steps will be skipped automatically.
