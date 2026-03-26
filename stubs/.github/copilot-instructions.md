# Copilot Instructions

## Git Workflow

- **Do NOT add `Co-authored-by` trailers** to commit messages
- **Do NOT push commits automatically** — always let the user push manually

## Dependency Injection over Facades

**Never use proxy facades.** Always inject dependencies through the constructor or method injection.

```php
// ❌ Bad
use Illuminate\Support\Facades\Cache;
Cache::get('key');
Cache::put('key', $value, 3600);

// ✅ Good
use Illuminate\Contracts\Cache\Repository as Cache;

public function __construct(private readonly Cache $cache) {}

$this->cache->get('key');
$this->cache->put('key', $value, 3600);
```

This applies to **all** facades:

| Instead of | Inject |
|---|---|
| `Cache::` | `Illuminate\Contracts\Cache\Repository` |
| `DB::` | `Illuminate\Database\ConnectionInterface` |
| `Queue::` | `Illuminate\Contracts\Queue\Queue` |
| `Storage::` | `Illuminate\Contracts\Filesystem\Filesystem` |
| `Mail::` | `Illuminate\Contracts\Mail\Mailer` |
| `Event::` | `Illuminate\Contracts\Events\Dispatcher` |
| `Log::` | `Psr\Log\LoggerInterface` |
| `Auth::` | `Illuminate\Contracts\Auth\Guard` |
| `Config::` | `Illuminate\Contracts\Config\Repository` |
| `Validator::` | `Illuminate\Contracts\Validation\Factory` |

> Helper functions (`cache()`, `config()`, `auth()`, etc.) are also discouraged — use injected contracts.

## Code Style

- **Type everything**: all properties, parameters, and return types must be typed
- Use `readonly` properties where the value never changes after construction
- Prefer `final` classes unless inheritance is explicitly needed
- Use named arguments when calling functions with multiple parameters of the same type
- No `mixed` types — if you need one, it is a signal to refactor

## Models

- Keep business logic **out of models** — models are data mappers only
- Use **Form Requests** for validation, never validate in controllers
- Use **Resources** (`JsonResource`) for API responses, never return raw models

## Testing

- Write **unit tests** for all business logic (services, actions, value objects)
- Write **feature tests** for all HTTP endpoints
- Use **factories** for test data — never hardcode values
- Prefer `assertDatabaseHas()` and `assertDatabaseMissing()` over manual queries in tests
