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
1. Installs required Composer prod deps (`inertia-laravel`, `laravel/wayfinder`, `spatie/laravel-data`)
2. Installs dev deps (`larastan`, `pint`, `ide-helper`, `sail`, `paratest`, `pail`)
3. Copies stubs: `Makefile`, `docker-compose.yml`, `docker/`, `.env.example`, `.env.pipelines`, `.github/workflows/app.yml`
4. Patches `.env` / `.env.example` (session/queue/cache/DB drivers)
5. Adds `@inertiajs/vue3` + `vue` to `package.json`, runs `yarn install`
6. Optionally installs Deployer + copies `deploy.yaml` + `deploy/`

### Package structure
```
src/
├── ScaffoldingServiceProvider.php   # registers preset:install command
└── Console/
    └── InstallCommand.php           # main install logic
stubs/                               # files copied into the target Laravel project
├── Makefile
├── docker-compose.yml
├── docker/
├── .env.example
├── .env.pipelines
├── .github/workflows/app.yml
└── deploy/                          # optional — installed when user confirms Deployer
    ├── deploy.yaml
    ├── app.php
    └── provision.php
```

## Stack

- **PHP**: 8.2+ (CI), 8.4 (Docker/Sail)
- **Laravel**: ^12.0
- **Frontend**: Inertia.js v2 + Vue, Vite, Yarn
- **Docker stack**: Laravel Sail (PHP 8.4), MariaDB 10, Redis, Mailpit
- **Deployment**: Deployer PHP (`./vendor/bin/dep`), Caddy, Supervisor (Horizon + Mailpit)
- **Queue/Cache**: Redis (cache), database (queue driver), Laravel Horizon (production)
- **Sessions**: database driver

## Key Dependencies

### Production
- `inertiajs/inertia-laravel` ^2.0
- `laravel/wayfinder` ^0.1 — typed route helpers for Vue/TS
- `spatie/laravel-data` ^4 — typed data objects / DTOs

### Dev
- `larastan/larastan` ^3 — PHPStan for Laravel
- `laravel/pint` — code style fixer (Laravel preset)
- `barryvdh/laravel-ide-helper` — IDE model helpers
- `deployer/deployer` ^7 — deployment automation
- `brianium/paratest` — parallel PHPUnit

## Commands

All commands use `just` (justfile) with Laravel Sail (`./vendor/bin/sail`).

```bash
# First-time setup
just build                  # Install Composer deps via Docker (no Sail needed)
just install <project-name> # Full install: configures .env, starts Sail, migrates, seeds

# Daily development
just up / just down
just shell                  # Open shell in the app container

# Code quality
just pint                   # Fix code style (Laravel Pint)
just pint true              # Dry-run / CI mode (--test)
just phpstan                # Static analysis (PHPStan/Larastan)
just lint                   # pint + phpstan

# Testing
just test                   # Run all tests (parallel)
just test MyTestClass       # Run a single test / filter

# Pre-commit checklist
just pre-commit             # fresh migrate → ide-helper → lint → test

# Artisan shortcuts
just fresh                  # migrate:fresh --seed
just ide                    # ide-helper:model -M
just artisan "route:list"   # Run any artisan command
```

## CI/CD (GitHub Actions — `.github/workflows/app.yml`)

- **lint** job: PHPStan + Pint (`--test` mode, no fix)
- **tests** job: MariaDB service, yarn build, `php artisan test --parallel`
- **deploy-staging**: triggers on `main` branch push
- **deploy-production**: triggers on GitHub release publish
- Environment secrets required: `SSH_KEY_STAGING`, `KNOWN_HOSTS_STAGING`, `SSH_KEY_PRODUCTION`, `KNOWN_HOSTS_PRODUCTION`
- CI uses `.env.pipelines` (not `.env.example`)

## Deployment (`deploy.yaml` + `deploy/`)

Uses Deployer PHP with two hosts: `staging` and `production`.

```bash
# Inside container (just shell):
./vendor/bin/dep provision   # First-time server setup (optional)
./vendor/bin/dep deploy staging
./vendor/bin/dep deploy production
```

Deploy flow: `app:custom-config` → `deploy:vendors` → cache artisan commands → `yarn build` → `php-fpm:reload` → `horizon:terminate` → `supervisorctl restart`.

Server provisioning (`deploy/provision.php`) installs: `php8.2-tidy`, `php8.2-redis`, Yarn, Redis, Mailpit (with Caddy reverse proxy + basic auth), Laravel Horizon via Supervisor.

## Environment Conventions

- `APP_URL` format: `http://<project>.local` (set during `just install <name>`)
- `/etc/hosts` entry added automatically by `just install`
- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=redis`
- Docker mounts `~/.ssh` into the container for deployment from within Sail

## Package Development Notes

When building the Composer package itself:
- The package entry point will be a Laravel Service Provider
- Use `Artisan::call()` or `$this->publishes()` for file copying
- Post-install commands go in the package's `composer.json` `scripts` section or as an artisan command
- Target: `composer require <vendor>/laravel-scaffolding` on a fresh `laravel new` project
