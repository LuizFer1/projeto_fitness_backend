# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- PHP 8.2+ / Laravel 12 (note: `composer.json` requires `^12.0` though README mentions Laravel 11)
- MySQL 8 via Docker Compose (container name `laravel_app`, Nginx exposed on `localhost:8000`)
- Laravel Sanctum for token auth, `l5-swagger` for OpenAPI at `storage/api-docs/api-docs.json`
- Tests use SQLite in-memory (see `phpunit.xml`)

## Common commands

All artisan/composer calls run inside the `laravel_app` container. Use the Makefile wrappers:

```bash
make up                           # start containers
make shell                        # bash into laravel_app
make artisan cmd="migrate:fresh --seed"
make composer cmd="install"
make clear                        # optimize:clear
```

Tests (run inside container):

```bash
php artisan test                              # full suite
php artisan test --testsuite=Unit             # unit only
php artisan test --testsuite=Feature          # feature only
php artisan test --filter=SomeTestName        # single test/method
composer test                                 # clears config then runs tests
```

Lint/format: `./vendor/bin/pint`. Dev all-in-one (outside docker): `composer dev` (runs `artisan serve`, queue listener, `pail` logs, vite concurrently).

## Architecture

The app mixes classic Laravel MVC with a partial Clean/Hexagonal layering. Do not assume all features follow one style — inspect the specific domain before editing.

- `app/Http/Controllers/` — two tiers:
  - Root-level controllers (`AuthController`, `OnboardingController`, `GoalController`, `ProfileController`, `LeaderboardController`) handle legacy/non-versioned routes.
  - `app/Http/Controllers/Api/V1/*` — versioned API (friends, posts, meals, workouts, AI plans, gamification, privacy, dashboard). New public endpoints go here under the `v1` prefix in `routes/api.php`.
- `app/Application/UseCases/{Auth,Onboarding}/` + `app/Application/Contracts/` — use-case classes used by some controllers (e.g. `CalculateDailyCaloriesUseCase`). Contracts live here; concrete services/repos elsewhere.
- `app/Domain/{Nutrition,Onboarding,User}/` — domain value objects / domain services for these three bounded contexts only. Most features still live directly on Eloquent models.
- `app/Infrastructure/Repositories/` — Eloquent repository implementations (`EloquentUserRepository`, `EloquentOnboardingRepository`, `EloquentNutritionRepository`). Bound to their interfaces in service providers.
- `app/Services/` — cross-cutting services. Two key ones:
  - `GamificationService` — XP ledger, streak, safety-day, level thresholds, badges. Invoked from multiple controllers after user actions (login, workout finish, meal analyze).
  - `GeminiService` — Google Gemini client used by meal analysis (`/meals/analyze-text|image`) and plan generation (`/plans/generate-workout|meal`). Responses are in Portuguese.
- `app/Models/` — Eloquent models. See ID pattern below.
- `routes/api.php` — single routing file. Public routes at the top, everything else behind `auth:sanctum`. `v1` prefix holds the main REST surface. `Route::bind('username', ...)` resolves `{username}` path params to active `User` records.

### ID/UUID pattern (important)

Every table carries both `id` (auto-increment PK, used for indexes) and `uuid` (external identifier, exposed via API). **Foreign keys reference UUIDs, not ids.** Relations must specify the FK and owner-key explicitly:

```php
public function uniqueIds(): array { return ['uuid']; }

public function user() {
    return $this->belongsTo(User::class, 'user_uuid', 'uuid');
}
```

When adding tables/relations, follow the same pattern (`foreignUuid('..._uuid')` in migrations, `HasUuids` + `uniqueIds()` override on the model). The route binding for `{username}` and most API IDs returns UUIDs in responses.

### Gamification rules (hard-coded in `GamificationService`)

- Daily XP caps per action: login +10, meal log +20, workout +30, daily ceiling 300.
- Streak bonus: `min((streak_days / 7) * 0.10, 0.50)` applied to XP.
- Safety day: 1 per ISO week preserves streak without activity.
- Level thresholds and badge slugs are documented in `AGENT_INTEGRATION.md` — treat that file as the source of truth for XP/level/badge semantics when editing `GamificationService` or related controllers.

## API reference

`AGENT_INTEGRATION.md` is a complete endpoint catalogue (auth, onboarding, friends, posts, workouts, meals, AI plans, leaderboards, LGPD, health). Consult it before adding or changing an endpoint to keep request/response shapes consistent.

Swagger spec lives at `storage/api-docs/api-docs.json` and is served by `darkaonline/l5-swagger`. Regenerate with `php artisan l5-swagger:generate` after adding OpenAPI annotations.

## Testing notes

- `phpunit.xml` forces `DB_CONNECTION=sqlite` + `:memory:`, so migrations must remain SQLite-compatible (avoid MySQL-only column types/functions in migrations you add).
- `BCRYPT_ROUNDS=4`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync` during tests — do not rely on real queue/cache behaviour in feature tests.
