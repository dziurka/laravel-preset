# 🛠 dziurka/laravel-preset

> Scaffolding dla świeżych projektów Laravel 13. Instaluje paczki, kopiuje pliki konfiguracyjne, ustawia środowisko i konfiguruje CI/CD — jednym poleceniem.

---

## 🚀 Szybki start (wizard)

Uruchom poniższe polecenie w terminalu. Jedyne co potrzebujesz to **Docker**.

```sh
curl -sSL https://raw.githubusercontent.com/dziurka/laravel-preset/main/wizard.sh -o /tmp/laravel-wizard.sh && bash /tmp/laravel-wizard.sh
```

> 💡 Skrypt jest napisany w czystym Bash — działa na każdym shellu (bash, zsh, fish, sh).

Wizard przeprowadzi Cię przez:

1. ✅ Sprawdzenie wymagań (Docker, just)
2. 🆕 `laravel new` — interaktywny kreator projektu Laravel 13
3. 📦 `php artisan preset:install` — instalacja presetu
4. 🤖 `php artisan boost:install` — konfiguracja integracji z AI agentem
5. 🏗 `just install <project-name>` — pierwsze uruchomienie (build, migrate, seed)

Po zakończeniu masz gotowy projekt z Dockerem, CI/CD, justfile i skonfigurowanym środowiskiem.

---

## 📦 Co instaluje preset

### Paczki Composer — produkcyjne

| Paczka | Opis |
|--------|------|
| `inertiajs/inertia-laravel` | Adapter Inertia.js po stronie serwera |
| `laravel/wayfinder` | Typowane helpery do routingu (Vue/TypeScript) |
| `spatie/laravel-data` | Typowane obiekty danych / DTO |

### Paczki Composer — developerskie

| Paczka | Opis |
|--------|------|
| `laravel/sail` | Środowisko developerskie oparte o Docker |
| `laravel/pint` | Formatowanie kodu (Laravel preset) |
| `laravel/pail` | Podgląd logów w czasie rzeczywistym |
| `larastan/larastan` | Statyczna analiza kodu (PHPStan dla Laravel) |
| `barryvdh/laravel-ide-helper` | Autouzupełnianie modeli w IDE |
| `brianium/paratest` | Równoległe uruchamianie testów PHPUnit |
| `laravel/boost` | Integracja z AI agentami (Copilot, Claude, Cursor…) |
| `deployer/deployer` | *(opcjonalne)* Automatyczne deploymenty na serwer |

### Kopiowane pliki

| Plik / katalog | Opis |
|----------------|------|
| `justfile` | Skróty do Sail, testów, lintingu i deploymentu |
| `docker-compose.yml` | Stack: PHP, MariaDB 11 / PostgreSQL 17, Valkey, Mailpit |
| `docker/` | Dockerfile PHP 8.3/8.4, php.ini, konfiguracja Supervisora |
| `.env.example` | Prekonfigurowane zmienne środowiskowe |
| `.env.pipelines` | Plik `.env` dla CI/CD |
| `.github/workflows/app.yml` | Pipeline GitHub Actions (lint → test → deploy) |
| `.github/copilot-instructions.md` | Dobre praktyki dla GitHub Copilot |
| `deploy.yaml` | *(opcjonalne)* Konfiguracja Deployera |
| `deploy/` | *(opcjonalne)* Skrypty provisioningu i deploymentu |

### 🐳 Stack Dockerowy

| Serwis | Obraz | Env |
|--------|-------|-----|
| `app` | PHP 8.3 / 8.4 (Sail) | local |
| `mariadb` | `mariadb:11` | local |
| `pgsql` | `postgres:17-alpine` | local |
| `valkey` | `valkey/valkey:8-alpine` | local |
| `mailpit` | `axllent/mailpit` | local / staging |

> 📬 Mailpit (webowy klient email) działa tylko lokalnie i na stagingu. Na produkcji wymagany jest zewnętrzny provider (Mailgun, Postmark, SES, SMTP).

### ⚙️ Domyślne zmienne środowiskowe

| Zmienna | Wartość | Opis |
|---------|---------|------|
| `SESSION_DRIVER` | `redis` | Sesje przechowywane w Valkey |
| `QUEUE_CONNECTION` | `horizon` | Kolejki obsługiwane przez Laravel Horizon |
| `CACHE_STORE` | `redis` | Cache w Valkey |
| `REDIS_HOST` | `valkey` | Nazwa serwisu Valkey w Dockerze |
| `DB_HOST` | `mariadb` / `pgsql` | Zgodnie z wybraną bazą danych |
| `MAIL_HOST` | `mailpit` | Lokalny klient email |

---

## 🔧 Wymagania

- **Docker** — jedyne wymaganie do uruchomienia wizarda
- PHP `^8.3` (Sail zapewnia PHP w kontenerze)
- Laravel `^13.0`
- Yarn (dla zależności frontendowych)

---

## 🛠 Instalacja ręczna (bez wizarda)

Jeśli masz już istniejący projekt Laravel 13:

```bash
# 1. Zainstaluj preset
composer require dziurka/laravel-preset --dev

# 2. Uruchom instalator
php artisan preset:install
```

Instalator zapyta o:

1. 🗄 **Silnik bazy danych** — MySQL/MariaDB lub PostgreSQL
2. 🐘 **Wersja PHP (lokalna / Sail)** — 8.4 *(zalecana)* lub 8.3
3. 🖥 **Wersja PHP (produkcja)** — 8.4 lub 8.3
4. 🚢 **Deployer** — opcjonalna instalacja narzędzia do deploymentu
5. 🤖 **Boost** — konfiguracja integracji z AI agentem

---

## 💻 Codzienny development

```bash
just build          # Zbuduj obrazy Dockerowe (pierwsze uruchomienie)
just install myapp  # Pełna konfiguracja projektu (up, migrate, seed)
```

| Polecenie | Opis |
|-----------|------|
| `just up` / `just down` | Uruchom / zatrzymaj kontenery |
| `just shell` | Wejdź do kontenera aplikacji |
| `just tinker` | Otwórz Laravel Tinker |
| `just fresh` | Fresh migrate + seed |
| `just migrate-rollback` | Cofnij ostatnią migrację |
| `just cache-clear` | Wyczyść cache (config, route, view, app) |
| `just test` | Uruchom wszystkie testy równolegle |
| `just test MyClass` | Uruchom pojedynczą klasę testową |
| `just test-coverage` | Generuj raport pokrycia kodu (HTML) |
| `just pint` | Napraw styl kodu |
| `just pint check=true` | Sprawdź styl bez zmian (tryb CI) |
| `just phpstan` | Statyczna analiza kodu |
| `just lint` | Pint + PHPStan |
| `just check` | Lint + testy |
| `just pre-commit` | Fresh migrate + IDE helpers + lint + testy |
| `just artisan "route:list"` | Dowolne polecenie artisan |
| `just ide` | Generuj docblocki modeli dla IDE |
| `just provision staging` | Provisioning serwera (pierwsza konfiguracja) |
| `just secrets staging` | Ustaw GitHub Secrets przez gh CLI |
| `just deploy staging` | Deploy na środowisko |

---

## 🌐 Deployment (jeśli Deployer jest zainstalowany)

### 1️⃣ Konfiguracja deploy.yaml

Uzupełnij `repository` i adresy serwerów:

```yaml
config:
  repository: 'git@github.com:your-org/your-app.git'

hosts:
  production:
    hostname: '1.2.3.4'   # IP serwera produkcyjnego
  staging:
    hostname: '5.6.7.8'   # IP serwera stagingowego
```

> 💡 **Wiele środowisk na jednym VPS?** To obsługiwane! Każde środowisko dostaje osobny:
> - katalog deploymentu (`/var/www/myapp-production`, `/var/www/myapp-staging`)
> - konfigurację Supervisora (`horizon-production`, `horizon-staging`)
> - bazę danych Valkey (`REDIS_DB=0` dla produkcji, `REDIS_DB=1` dla stagingu)

#### Interaktywne pytania vs. prekonfiguracja

Niektóre zadania provisioningu pytają interaktywnie (np. hasło Mailpit, basic auth). Możesz pominąć pytania ustawiając wartości w `deploy.yaml`:

```yaml
# Mailpit (staging)
mailpit_user: admin
mailpit_password: secret

# Basic auth (staging)
basic_auth_user: admin
basic_auth_password: secret
```

Jeśli wartość **nie jest** ustawiona, zadanie zapyta podczas provisioningu.  
Prekonfiguracja przydaje się przy **automatycznym provisioningu (CI/CD)**.

> ⚠️ Nigdy nie commituj prawdziwych haseł do repozytorium.

---

### 2️⃣ Provisioning serwera (jednorazowo)

```bash
just shell
./vendor/bin/dep provision staging      # lub: just provision staging
./vendor/bin/dep provision production   # powtórz dla produkcji
```

Provisioning wykonuje zadania w kolejności:

| Zadanie | Co robi |
|---------|---------|
| `provision:sudoers` | Nadaje `deployer` bezhasłowe sudo |
| `provision:packages` | Instaluje PHP, rozszerzenia, unzip, micro |
| `provision:yarn` | Instaluje Yarn (oficjalne APT repo) |
| `provision:valkey` | Instaluje Valkey (oficjalne APT repo) |
| `provision:mailpit` | Instaluje Mailpit + Supervisor + proxy Caddy (**tylko staging** — `mailpit_enabled: true`) |
| `provision:basic-auth` | Dodaje HTTP Basic Auth przez Caddy (**tylko staging** — `basic_auth_enabled: true`) |
| `provision:permissions` | Ustawia właściciela katalogu deploymentu |
| `provision:horizon` | Tworzy konfigurację Supervisora dla Laravel Horizon |
| `provision:github` | Generuje klucz SSH deploy, drukuje konfigurację |
| `provision:github-secrets` | Ustawia GitHub Secrets automatycznie (jeśli `gh` jest dostępne) |

---

### 3️⃣ Konfiguracja GitHub

#### Opcja A — Automatyczna (zalecana): gh CLI

Zainstaluj [GitHub CLI](https://cli.github.com/) i zaloguj się **przed** uruchomieniem Sail:

```bash
gh auth login
```

Provisioning ustawi wszystkie sekrety automatycznie. Możesz też uruchomić to osobno:

```bash
just secrets staging
just secrets production
```

> 💡 Jeśli `gh` nie jest zainstalowane, skrypt `provision:github-secrets` zapyta czy zainstalować je przez [webi.sh](https://webinstall.dev/gh/).

Ustawiane sekrety:

| Sekret | Opis |
|--------|------|
| `SSH_KEY_STAGING` | Prywatny klucz deploy dla stagingu |
| `KNOWN_HOSTS_STAGING` | Fingerprint serwera stagingowego |
| `SSH_KEY_PRODUCTION` | Prywatny klucz deploy dla produkcji |
| `KNOWN_HOSTS_PRODUCTION` | Fingerprint serwera produkcyjnego |

> 🔒 Każdy serwer dostaje osobną niezależną parę kluczy — klucze nie są współdzielone.

#### Opcja B — Ręczna: GitHub UI

Po provisioningu zadanie `provision:github` wydrukuje trzy wartości. Skopiuj je do odpowiednich miejsc:

**1. Deploy key** — pozwala serwerowi na `git pull`

> GitHub → repo → **Settings → Deploy keys → Add deploy key**
> - Title: `deployer@your-server (staging)`
> - Key: wklej klucz `ssh-ed25519 …`
> - ☑ Allow write access: **zostaw odznaczone**

**2. Prywatny klucz SSH** — pozwala GitHub Actions łączyć się z serwerem

> GitHub → repo → **Settings → Secrets and variables → Actions → New repository secret**
> - Name: `SSH_KEY_STAGING`
> - Secret: wklej blok `-----BEGIN OPENSSH PRIVATE KEY-----`

Powtórz z `SSH_KEY_PRODUCTION` dla produkcji.

**3. Known hosts** — zapobiega weryfikacji hosta w CI

> GitHub → repo → **Settings → Secrets and variables → Actions → New repository secret**
> - Name: `KNOWN_HOSTS_STAGING`
> - Secret: wklej linię `ssh-ed25519 …` z `ssh-keyscan`

Powtórz z `KNOWN_HOSTS_PRODUCTION` dla produkcji.

---

### 4️⃣ Deploy

Po skonfigurowaniu sekretów:

```bash
# Push do main → automatyczny deploy na staging
git push origin main

# Publish Release → automatyczny deploy na produkcję

# Lub ręcznie:
just deploy staging
just deploy production
```

> ⚠️ **Deployer jest opcjonalny.** Jeśli nie jest zainstalowany (`./vendor/bin/dep` nie istnieje), kroki deploy w pipeline będą pominięte z informacją jak go dodać.

---

## 🤖 AI Agent (laravel/boost)

Preset instaluje `laravel/boost` i uruchamia wizard `boost:install`, który konfiguruje integrację z Twoim AI agentem:

- **GitHub Copilot** (VS Code / JetBrains)
- **Cursor**
- **Claude** (Anthropic)
- **Gemini CLI**
- i inne…

Boost generuje pliki konfiguracyjne (`.mcp.json`, `CLAUDE.md` itp.) odpowiednie dla wybranego narzędzia.

Do każdego projektu kopiowany jest też `.github/copilot-instructions.md` z dobrymi praktykami (m.in. zakaz używania fasad, typowanie, konwencje modeli i testów).

---

## 🔍 Rozwiązywanie problemów

**Docker build się wysypuje**
```bash
just down
docker system prune -f
just build
```

**Testy padają z błędami bazy danych**  
Upewnij się że `.env` istnieje, następnie uruchom `just fresh`.

**Problemy z kluczem SSH w deploymencie**  
Uruchom `just provision staging` ponownie, a następnie `just secrets staging` żeby zaktualizować GitHub.

**`gh secret set` — brak uprawnień**  
Upewnij się że masz dostęp `admin` lub `write` do repozytorium i że `gh auth login` użył tokenu z zakresem `repo`.

**Provisioning uciął się w połowie**  
Większość zadań jest idempotentna (bezpieczna do ponownego uruchomienia). Napraw problem i uruchom `just provision staging` ponownie — ukończone kroki zostaną automatycznie pominięte.

**Skrypt wizard.sh nie startuje na fish / zsh**  
Użyj formy z pobraniem pliku — działa na każdym shellu:
```sh
curl -sSL https://raw.githubusercontent.com/dziurka/laravel-preset/main/wizard.sh -o /tmp/laravel-wizard.sh && bash /tmp/laravel-wizard.sh
```

