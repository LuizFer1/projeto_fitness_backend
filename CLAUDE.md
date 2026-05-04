# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- PHP 8.2 / Laravel 12
- MySQL 8 via Docker Compose (container `laravel_app`, Nginx on `localhost:8000`)
- Laravel Sanctum for token auth
- `l5-swagger` / `darkaonline/l5-swagger` for OpenAPI at `storage/api-docs/api-docs.json`
- Redis for rankings and cache
- Tests: SQLite in-memory (`phpunit.xml`)

## Common commands

All artisan/composer calls run inside the `laravel_app` container via Makefile:

```bash
make up                                   # start containers
make shell                                # bash into laravel_app
make artisan cmd="migrate:fresh --seed"
make composer cmd="install"
make clear                                # optimize:clear
```

Tests (run inside container):

```bash
php artisan test                          # full suite (123 tests)
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=SomeTestName
composer test                             # clears config then runs tests
```

Lint: `./vendor/bin/pint`. Swagger: `php artisan l5-swagger:generate`.

## Architecture

The app mixes classic Laravel MVC with a partial Clean/Hexagonal layering. Inspect the specific domain before editing — not all features follow the same style.

### Controllers

- **Root-level** (`app/Http/Controllers/`): `AuthController`, `OnboardingController`, `GoalController` — legacy non-versioned routes.
- **`app/Http/Controllers/Api/V1/`**: all versioned endpoints. New endpoints go here under the `v1` prefix in `routes/api.php`.

Key V1 controller directories:
```
Gamification/   — LeaderboardController, GamificationController
PublicProfile/  — PublicProfileController
Workouts/       — WorkoutLogController, CardioWorkoutController, VictoryAssetController, PersonalRecordController
Nutrition/      — NutritionController, MealLogController, NutritionExportController
Onboarding/     — TdeeConfigController
Follow/         — FollowController
```

### Application layer

- `app/Application/UseCases/` — use-case classes (`CalculateDailyCaloriesUseCase`, `RedistributeMacrosUseCase`, `RegisterCardioSessionUseCase`, …). Contracts in `app/Application/Contracts/`.
- `app/Domain/` — domain value objects/services for `Nutrition`, `Onboarding`, `User`, and `Workout` bounded contexts.
- `app/Infrastructure/Repositories/` — Eloquent implementations (`EloquentUserRepository`, `EloquentOnboardingRepository`, `EloquentNutritionRepository`). Bound in service providers.

### Key services (`app/Services/`)

| Service | Purpose |
|---|---|
| `GamificationService` | XP ledger, streak, level-up, badges. Config-driven via `config/gamification.php`. |
| `GroqService` | Groq LLM client (text + vision). Used by meal analysis and AI plan generation. Responses in pt-BR. |
| `AuditLogger` | Append-only audit log for LGPD-sensitive actions (data export, account deletion, photo access). |
| `Diet/DietEngineService` | Recalculates daily nutrition delta after each meal log (RF-01). |
| `Workout/ProgressiveOverloadService` | Epley 1RM + progressive load suggestions (RF-08/09). |
| `Storage/EncryptedPhotoStorageService` | AES-256-GCM encrypted progress photos on S3 (RF-24). |
| `Ranking/RedisRankingService` | Redis Sorted Sets for real-time leaderboards (RNF-10). |
| `Assets/StrengthVictoryAssetService` | Generates 1080×1080 strength victory image (RF-16). |
| `Assets/CardioVictoryAssetService` | Generates cardio victory image with Mapbox route (RF-17). |
| `Notifications/QuietHoursPolicy` | Checks whether current time falls inside user's quiet window. |

### Scheduling (`routes/console.php`)

Laravel 12 has no `Kernel.php` — scheduled jobs are declared in `routes/console.php`:

- `RecalculateLeaderboardJob` — hourly
- `EvaluateStreakAtRiskJob` — hourly (fires between 19h–22h user local time)
- `VerifyAchievementsRetroactivelyJob` — daily at 03:00 UTC
- `GenerateBiweeklyReportJob` — every 14 days at 06:00 UTC

### ID / UUID pattern

**New tables (Phase 1–3, 2026)**: use `$table->uuid('id')->primary()` — UUID is the PK directly. FK columns are `foreignUuid('user_id')` referencing `users.id`.

**Legacy tables**: some use `id` (auto-increment PK) + separate `uuid` column. FKs on those tables point to the `uuid` column with explicit key names:

```php
// legacy pattern
public function user() {
    return $this->belongsTo(User::class, 'user_uuid', 'uuid');
}
```

When adding a new table, follow the **new pattern**: `uuid('id')->primary()` + `HasUuids` on the model (no `uniqueIds()` override needed).

### Gamification rules

XP values, caps, and streak multipliers are in `config/gamification.php` — **do not hardcode them in `GamificationService`**.

Key events and base XP:

| Event | Base XP | Daily cap |
|---|---|---|
| `workout_strength` | 100 | 150 |
| `workout_cardio` | 80 | 112 |
| `clean_diet_day` | 60 | 120 |
| `protein_goal_met` | 50 | 75 |
| `water_goal_met` | 30 | 39 |
| `pr_set` | 200 | 200 |
| `progress_photo` | 20 | 20 |
| `asset_shared` | 15 | 30 |

Global daily cap: 300 XP. Streak multiplier: `min((streak_days / N) * mult, cap)`.

Level thresholds and badge slugs are documented in `AGENT_INTEGRATION.md` — treat that as source of truth for XP/level/badge semantics.

## API reference

`AGENT_INTEGRATION.md` is the endpoint catalogue. Consult it before adding or changing an endpoint to keep request/response shapes consistent.

All new endpoints go under `auth:sanctum` middleware and the `v1` prefix. Swagger annotations (`#[OA\...]`) are required for every new endpoint.

## Testing notes

- `phpunit.xml` forces `DB_CONNECTION=sqlite` + `:memory:` — all migrations must remain SQLite-compatible. Avoid MySQL-only types (`enum` in migrations is fine via `->string()` on SQLite; avoid `->json()` in indexes).
- `BCRYPT_ROUNDS=4`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync` in tests — do not rely on real queue/cache behaviour in feature tests. Use `Queue::fake()` when testing jobs.
- `MAIL_MAILER=log` — no real mail in tests.
- Current suite: **123 tests, 346 assertions**.
