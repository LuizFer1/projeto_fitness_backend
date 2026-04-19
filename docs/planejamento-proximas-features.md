# Planejamento — Próximas Features

**Branch alvo:** `ralph/infraestrutura-nfr-p2`
**Contexto:** continuação das lacunas identificadas no mapeamento de 2026-04-18. Os itens 1–4 (RecalculateLeaderboardJob, weight log, quests, rename Groq) foram entregues no commit `556ac1d`. Este documento cobre o que ficou pendente.

---

## Feature 1 — Monetização / Assinaturas

### Objetivo
Expor planos (Free / Plus / Pro), preços por período de cobrança e assinatura ativa do usuário. Primeira fase **read-only**, sem integração de pagamento.

### Estado atual
- Schema **não existe**. `PlanSeeder` antigo referenciava modelos inexistentes (removido no commit anterior).
- README menciona tabelas `plans`, `plan_prices`, `subscriptions` mas nenhuma foi criada.

### Decisões pendentes
| Tópico | Opções | Recomendação |
|---|---|---|
| Gateway de pagamento | Stripe / Pagar.me / Mercado Pago / nenhum por ora | **Nenhum por ora** — apenas catálogo + assinatura manual |
| Trial | Manter `trial_days` do seeder antigo (30 dias Plus/Pro) | Sim |
| Fonte de preço | Tabela `plan_prices` por período | Sim, replicar schema do README |
| Cancelamento | Imediato vs fim do ciclo | Fim do ciclo (`cancel_at_period_end`) |

### Escopo técnico

**Migrations (nova):**
- `plans` — `id uuid`, `code` (enum: free/plus/pro), `name`, `trial_days`, `is_active`, timestamps
- `plan_prices` — `id uuid`, `plan_id fk`, `billing_period` (monthly/semiannual/annual), `price_cents`, `currency` (default BRL), `is_active`
- `subscriptions` — `id uuid`, `user_id fk`, `plan_id fk`, `plan_price_id fk nullable`, `status` (trialing/active/canceled/expired), `started_at`, `trial_ends_at`, `current_period_end`, `canceled_at`, `cancel_at_period_end`, timestamps. Índice `(user_id, status)`.

**Models:** `Plan`, `PlanPrice`, `Subscription`. Padrão UUID como resto do projeto.

**Endpoints:**
- `GET /api/v1/plans/catalog` — lista pública de planos ativos com preços
- `GET /api/v1/subscriptions/me` — assinatura atual do usuário autenticado
- `POST /api/v1/subscriptions` — criar assinatura (body: `plan_code`, `billing_period`). Inicia em `trialing` se `trial_days > 0`, senão `active`
- `POST /api/v1/subscriptions/cancel` — marca `cancel_at_period_end=true`
- `POST /api/v1/subscriptions/resume` — reverte cancelamento se ainda no período

**Regras:**
- Usuário sem subscription → considerado "free" implícito.
- Apenas 1 subscription ativa/trialing por usuário.
- Command agendado `SubscriptionsExpireCommand` (diário) para mover `trialing`/`active` expiradas → `expired`.

**Seeder:** reintroduzir `PlanSeeder` depois das migrations, adicionar `PlanSeeder::class` no `DatabaseSeeder`.

### Esforço estimado
**M** — ~4h. 3 migrations + 3 models + 1 controller + 5 rotas + command + seeder + testes.

### Fora de escopo
- Integração com gateway real.
- Upgrade/downgrade com prorata.
- Invoices / histórico de cobrança.
- Gating de features por plano (feature flags por tier).

---

## Feature 2 — Admin CRUD (Badges / Quests / Exercises)

### Objetivo
Permitir gerenciar catálogos (badges, quests/missões, exercises) sem mexer em seeders/SQL.

### Estado atual
- **Sem sistema de roles.** `users.is_admin` ou `users.role` não existem.
- Tabelas `achievements`, `quests`, `exercises` existem e são seedadas via migration.
- Nenhum endpoint administrativo.

### Decisões pendentes
| Tópico | Opções | Recomendação |
|---|---|---|
| Auth admin | Coluna `is_admin` boolean vs tabela `roles`/`permissions` (spatie) | **Coluna `is_admin`** — simples, suficiente p/ 1 role |
| UI | Endpoint REST puro vs Filament / Nova | **REST puro** nesta fase |
| Escopo | Badges + Quests + Exercises | Começar por Quests e Badges, Exercises depois |

### Escopo técnico

**Migration:** adicionar `is_admin boolean default false` em `users`.

**Middleware:** `app/Http/Middleware/EnsureAdmin.php` — retorna 403 se `!$user->is_admin`. Registrar alias `admin`.

**Controllers:**
- `Api/V1/Admin/BadgeController` — `index/store/update/destroy` sobre `achievements`
- `Api/V1/Admin/QuestController` — `index/store/update/destroy` sobre `quests`
- `Api/V1/Admin/ExerciseController` — `index/store/update/destroy/bulkImport`

**Rotas:**
```php
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('badges', BadgeController::class);
    Route::apiResource('quests', QuestController::class);
    Route::apiResource('exercises', ExerciseController::class);
    Route::post('exercises/bulk', [ExerciseController::class, 'bulkImport']);
});
```

**Validações:** manter enum values conforme schema (`condition_type`, `periodicity`, `muscle_group`, etc.).

**Comando helper:** `php artisan admin:promote {email}` para promover usuário sem SQL manual.

### Esforço estimado
**M** — ~3h. 1 migration + 1 middleware + 3 controllers + rotas + command + testes de autorização.

### Fora de escopo
- Auditoria (quem mudou o quê).
- Roles múltiplos (editor/moderador).
- UI web — apenas API.

---

## Feature 3 — Cobertura de Testes

### Objetivo
Estender cobertura de `auth` + `gamification` (atualmente únicos testados) para features críticas restantes.

### Estado atual
- 21 testes: `Tests\Feature\Auth\*` + `Tests\Unit\Services\GamificationServiceTest` + 2 example tests.
- Sem testes para: meals, workouts, AI plans, social, privacy, dashboard, onboarding, goals, quests, weight logs, leaderboard.

### Prioridade

**Alta (cobrem dinheiro/retenção/LGPD):**
1. `MealLogControllerTest` — fake do GroqService, happy path text + image, validação.
2. `WorkoutLogControllerTest` — fake Groq, XP granted once/day, badge check.
3. `PrivacyControllerTest` — export retorna shape esperado, delete-account apaga em cascata e requer senha.
4. `SubscriptionsControllerTest` (junto com Feature 1).
5. `AdminAuthorizationTest` (junto com Feature 2) — 403 para não-admins.

**Média:**
6. `OnboardingControllerTest` — BMR/TDEE calculados corretamente.
7. `GoalControllerTest` — auto-cálculo de macros.
8. `FriendControllerTest` — fluxo request/accept/reject/block + não listar bloqueados.
9. `PostControllerTest` — visibility public vs friends_only no feed.
10. `LeaderboardControllerTest` — friends leaderboard inclui próprio usuário.
11. `RecalculateLeaderboardJobTest` — snapshot é escrito, position respeita ties.

**Baixa:**
12. `BodyMeasurementControllerTest` — upsert por data, XP +15 uma vez/dia.
13. `QuestControllerTest` — `mine` devolve progresso correto por period.
14. `AiPlanControllerTest` / `AiMealPlanControllerTest` — fake Groq, persiste `AiPlan`.

### Estratégia
- Fake para `GroqService`: criar `GroqServiceFake` e bindar no `TestCase` ou via `Service::fake()`.
- Para testes que dependem de dados seedados (badges, quests), rodar seeders nas migrations de teste (`RefreshDatabase` + `$seed = true`).
- Evitar fixtures pesadas — usar `UserFactory` + factories novas conforme demanda.

### Esforço estimado
**G** — ~10–12h somadas. Fazer em lotes:
- **Lote A** (alta prioridade, ~4h): 1, 2, 3
- **Lote B** (feature-coupled, ~2h): 4, 5 — feitos junto com Features 1 e 2
- **Lote C** (média, ~4h): 6–11
- **Lote D** (baixa, ~2h): 12–14

---

## Ordem de Execução Recomendada

```
P1 ──► Feature 1 (Subscriptions) + testes do Lote B parcial
       ~5h
P2 ──► Feature 2 (Admin) + testes do Lote B restante
       ~4h
P3 ──► Lote A de testes (meals / workouts / privacy)
       ~4h
P4 ──► Lote C (testes médios)
       ~4h
P5 ──► Lote D (testes baixos) + limpeza
       ~2h
```

Total: **~19h** de implementação + testes.

## Riscos / Bloqueios

- **Subscriptions sem gateway** — produto pode querer Stripe antes de expor endpoints públicos. Confirmar antes de começar.
- **Admin sem UI** — se o requisito real for uma interface web, considerar Filament no lugar de construir CRUD REST manual.
- **Fake do Groq** — atualmente `GroqService` chama `Http::post` direto; testar requer `Http::fake()` ou refatorar para injetar client mockável.
- **SQLite em testes** — migrations que usam `DB::statement("ALTER TABLE ... ENUM")` já têm guarda `if !== 'sqlite'`; manter padrão ao adicionar novas.

## Checklist de Pronto-para-Codar

Antes de começar Feature 1:
- [ ] Confirmar com produto: gateway de pagamento nesta fase? (se sim, reabrir escopo)
- [ ] Confirmar período de cobrança definitivo (monthly/semiannual/annual está ok?)
- [ ] Decidir se feature flags por plano entram neste batch ou em P6

Antes de começar Feature 2:
- [ ] Confirmar que `is_admin boolean` basta (vs roles system)
- [ ] Confirmar lista de catálogos prioritários (badges? quests? exercises? outros?)

---

_Documento vivo. Atualizar conforme cada P for concluído._

---

## Progresso

- [x] **P1 — Subscriptions** (2026-04-18): migrations `plans` / `plan_prices` / `subscriptions`, models, `PlanCatalogController`, `SubscriptionController`, `PlanSeeder`, `SubscriptionsExpireCommand`. Endpoints `GET /api/v1/plans/catalog`, `GET/POST /api/v1/subscriptions`, `/cancel`, `/resume`.
- [x] **P2 — Admin CRUD** (2026-04-18): migration `is_admin`, middleware `EnsureAdmin`, controllers `Admin\{Badge,Quest,Exercise}Controller`, comando `admin:promote`. Prefixo `api/v1/admin`.
- [x] **P3 — Lote A de testes** (2026-04-18): `MealLogControllerTest`, `WorkoutLogControllerTest`, `PrivacyControllerTest`, `Admin\*Test`, `Subscriptions\*Test`. Suíte total: 46 testes / 135 asserções.
- [x] **P4 — Lote C** (2026-04-18): `FriendControllerTest`, `PostControllerTest`, `LeaderboardControllerTest`, `RecalculateLeaderboardJobTest`, `GoalControllerTest`. Corrigido `RateLimiter::for('leaderboard')` que estava ausente em `AppServiceProvider`. Suíte total: 62 testes / 182 asserções.
- [x] **P5 — Lote D** (2026-04-18): `BodyMeasurementControllerTest`, `QuestControllerTest`. Suíte total: **67 testes / 201 asserções**.
