# Copilot Instructions

## Project Goal

This repository **is the `dziurka/laravel-preset` Composer package**. It scaffolds a fresh Laravel project with custom configuration, dependencies, and files — replacing the old workflow of maintaining a full boilerplate repo that required manual diffing on every Laravel update.

### Usage on a fresh Laravel project
```bash
laravel new my-app
cd my-app
composer require dziurka/laravel-preset --dev
php artisan preset:install
```

The `preset:install` command:
1. Installs dev deps (`larastan`, `pint`, `ide-helper`, `sail`, `paratest`, `pail`)
2. Copies stubs: `docker-compose.yml`, `docker/`, `.env.pipelines`, `.github/workflows/app.yml`, `justfile`
3. Patches `.env` / `.env.example` (DB, session, queue, cache, redis drivers)
4. Optionally installs Deployer v8 + copies `deploy.yaml` + `deploy/`
5. Optionally installs Laravel Boost (AI agent integration)

### Package structure
```
src/
├── ScaffoldingServiceProvider.php   # registers preset:install command
└── Console/
    └── InstallCommand.php           # main install logic
stubs/                               # files copied into the target Laravel project
├── docker-compose.{pgsql,mysql}.yml
├── docker/
├── justfile
├── .env.pipelines.{pgsql,mysql}
├── .github/
│   ├── copilot-instructions.md
│   └── workflows/app.{pgsql,mysql}.yml
└── deploy/                          # optional — installed when user confirms Deployer
    ├── app.php
    ├── provision.php
    └── provision/
        ├── system.php
        ├── services.php
        └── github.php
```

## Stack

- **PHP**: 8.3+ (prod), 8.4 (Docker/Sail)
- **Laravel**: ^13.0
- **Frontend**: Inertia.js + Vue, Vite, npm
- **Docker stack**: Laravel Sail, PostgreSQL or MariaDB, Valkey (Redis-compatible), Mailpit
- **Deployment**: Deployer PHP v8 (`./vendor/bin/dep`), Caddy, Supervisor (Horizon + Mailpit)
- **Queue/Cache**: Valkey (Redis), Laravel Horizon (production queue), Redis sessions

## Key Dependencies

### Dev (installed by preset)
- `larastan/larastan` ^3 — PHPStan for Laravel
- `laravel/pint` — code style fixer
- `barryvdh/laravel-ide-helper` — IDE model helpers
- `laravel/sail` ^1 — local Docker dev
- `brianium/paratest` — parallel PHPUnit
- `laravel/pail` — real-time log tailing
- `deployer/deployer` ^8 — deployment automation (optional)
- `laravel/boost` — AI agent integration (optional)

## Commands

All commands use `just` (justfile) with Laravel Sail (`./vendor/bin/sail`).

```bash
just build                  # Install Composer deps via Docker (no Sail needed)
just install <project-name> # Full install: configures .env, starts Sail, migrates, seeds
just up / just down
just shell
just pint / just phpstan / just lint
just test / just test MyTestClass
just fresh                  # migrate:fresh --seed
just pre-commit
```

## CI/CD (GitHub Actions)

- **lint** job: PHPStan + Pint (`--test`)
- **tests** job: DB service, npm ci + build, `php artisan test --parallel`
- **deploy-staging**: triggers on `main` push
- **deploy-production**: triggers on GitHub release
- Secrets: `SSH_KEY_STAGING`, `KNOWN_HOSTS_STAGING`, `SSH_KEY_PRODUCTION`, `KNOWN_HOSTS_PRODUCTION`

## Deployment (`deploy.yaml` + `deploy/`)

Deployer v8 with two hosts: `staging` and `production`.

Deploy flow: `deploy:prepare` → `deploy:vendors` → artisan cache commands → `npm:install` → `app:frontend` → `deploy:publish` → `php-fpm:reload` → `deploy:horizon` → `deploy:supervisor`.

## Environment Conventions

- `DB_CONNECTION=pgsql` (default) or `mysql`
- `REDIS_HOST=valkey`
- `SESSION_DRIVER=redis`
- `QUEUE_CONNECTION=horizon`
- `CACHE_STORE=redis`

## Git Workflow

- **Do NOT add `Co-authored-by` trailers** to commit messages
- **Do NOT push commits automatically** — always let the user push manually
