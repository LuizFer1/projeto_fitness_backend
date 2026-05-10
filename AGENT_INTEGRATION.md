# FitAI Backend - Guia de Integração para Agentes

> Documento completo para integração com a API REST do backend FitAI.
> Base URL: `http://localhost:8000/api`

> **Catálogo completo de endpoints**: este documento cobre os endpoints mais usados e a semântica do contrato (XP, gamificação, idempotência). Para a referência exaustiva, consulte o Swagger em `http://localhost:8000/api/documentation` (gerado de `storage/api-docs/api-docs.json` via `php artisan l5-swagger:generate`).

---

## Autenticação

Todas as rotas protegidas exigem o header:

```
Authorization: Bearer {token}
```

O token é retornado nos endpoints de login e registro (campo `token`).

---

## 1. Registro de Usuário

```
POST /api/register
Content-Type: application/json
```

**Body:**

```json
{
  "name": "João",
  "last_name": "Silva",
  "email": "joao@email.com",
  "cpf": "123.456.789-00",
  "password": "senhaSegura123",
  "password_confirmation": "senhaSegura123"
}
```

| Campo                   | Tipo   | Regras                                  |
|-------------------------|--------|-----------------------------------------|
| name                    | string | obrigatório, max 80                     |
| last_name               | string | obrigatório, max 120                    |
| email                   | string | obrigatório, email válido, max 180, único |
| cpf                     | string | obrigatório, max 14, único              |
| password                | string | obrigatório, min 8                      |
| password_confirmation   | string | obrigatório, deve ser igual a password  |

**Resposta 201:**

```json
{
  "user": {
    "id": "uuid",
    "name": "João",
    "last_name": "Silva",
    "email": "joao@email.com",
    "cpf": "123.456.789-00"
  },
  "token": "1|abc123..."
}
```

---

## 2. Login

```
POST /api/login
Content-Type: application/json
```

**Body:**

```json
{
  "email": "joao@email.com",
  "password": "senhaSegura123"
}
```

**Resposta 200:**

```json
{
  "user": { "id": "uuid", "name": "João", "..." : "..." },
  "token": "2|xyz789..."
}
```

---

## 3. Logout

```
POST /api/logout
Authorization: Bearer {token}
```

**Resposta 200:**

```json
{ "message": "Logged out" }
```

---

## 4. Perfil do Usuário Autenticado

```
GET /api/me
Authorization: Bearer {token}
```

**Resposta 200:** Retorna o usuário com relações `onboarding` e `gamification`.

```json
{
  "id": "uuid",
  "name": "João",
  "last_name": "Silva",
  "email": "joao@email.com",
  "nickname": "joaofit",
  "bio": "Treino todo dia",
  "avatar_url": null,
  "onboarding": {
    "gender": "M",
    "age": 28,
    "height_cm": 180,
    "weight_kg": 80,
    "body_fat_pct": 15.0,
    "exercise_frequency": 5,
    "work_style": "sedentary",
    "bmr": 1850.00
  },
  "gamification": {
    "xp_total": 450,
    "current_level": 3,
    "xp_to_next": 500,
    "current_streak": 12,
    "max_streak": 30,
    "total_workouts": 25,
    "current_week_xp": 120,
    "current_month_xp": 450
  }
}
```

> **Nota:** Este endpoint também concede +10 XP de login diário (uma vez por dia).

---

## 5. Onboarding

### Consultar Onboarding

```
GET /api/onboarding
Authorization: Bearer {token}
```

### Criar/Atualizar Onboarding

```
POST /api/onboarding
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**

```json
{
  "gender": "M",
  "age": 28,
  "height_cm": 180,
  "weight_kg": 80,
  "body_fat_percent": 15,
  "workouts_per_week": 5,
  "work_style": "sedentary"
}
```

| Campo             | Tipo    | Regras                                                         |
|-------------------|---------|----------------------------------------------------------------|
| gender            | string  | nullable, valores: `M`, `F`, `other`, `prefer_not_to_say`     |
| age               | integer | nullable, min 10, max 120                                      |
| height_cm         | integer | nullable, min 100, max 250                                     |
| weight_kg         | numeric | nullable, min 30, max 300                                      |
| body_fat_percent  | numeric | nullable, min 3, max 60                                        |
| workouts_per_week | integer | nullable, min 0, max 7                                         |
| work_style        | string  | nullable, valores: `white_collar`, `blue_collar`, `sedentary`, `moderate`, `active` |

> O backend calcula automaticamente BMR (Mifflin-St Jeor) e TDEE a partir desses dados.

---

## 6. Busca de Usuários

```
GET /api/v1/users/search?q={termo}
Authorization: Bearer {token}
```

| Query Param | Tipo   | Regras                    |
|-------------|--------|---------------------------|
| q           | string | obrigatório, min 3 chars  |

**Resposta 200:** Lista de usuários encontrados (exclui usuários bloqueados).

---

## 7. Perfil Público

### Dados do Perfil

```
GET /api/v1/users/{username}
Authorization: Bearer {token}
```

**Resposta 200:**

```json
{
  "username": "joaofit",
  "name": "João",
  "last_name": "Silva",
  "avatar_url": null,
  "bio": "Treino todo dia"
}
```

### Conquistas do Usuário

```
GET /api/v1/users/{username}/achievements
Authorization: Bearer {token}
```

### Metas do Usuário

```
GET /api/v1/users/{username}/goals
Authorization: Bearer {token}
```

---

## 8. Amizades

### Listar Amigos Aceitos

```
GET /api/v1/friends
Authorization: Bearer {token}
```

Paginado (20 por página). Suporta `?page=N`.

### Listar Pedidos Pendentes Recebidos

```
GET /api/v1/friends/requests
Authorization: Bearer {token}
```

### Enviar Pedido de Amizade

```
POST /api/v1/friends/request
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**

```json
{
  "username": "maria_fit"
}
```

### Aceitar Pedido

```
POST /api/v1/friends/{friendship_id}/accept
Authorization: Bearer {token}
```

### Rejeitar Pedido

```
POST /api/v1/friends/{friendship_id}/reject
Authorization: Bearer {token}
```

### Remover Amizade

```
DELETE /api/v1/friends/{friendship_id}
Authorization: Bearer {token}
```

### Bloquear Usuário

```
POST /api/v1/friends/{friendship_id}/block
Authorization: Bearer {token}
```

---

## 9. Posts e Feed Social

### Criar Post

```
POST /api/v1/posts
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: <uuid>
```

**Body:**

```json
{
  "content": "Treino de pernas concluído! 💪",
  "visibility": "public"
}
```

| Campo      | Tipo   | Regras                                    |
|------------|--------|-------------------------------------------|
| content    | string | obrigatório, max 500 chars                |
| visibility | string | obrigatório, valores: `public`, `friends_only` |

> Tipos de post suportados: `text`, `achievement`, `goal_completed`, `workout_completed`, `level_up`.

### Feed

```
GET /api/v1/feed
Authorization: Bearer {token}
```

Retorna posts próprios + de amigos. Paginação por cursor (15 por página).
Suporta `?cursor={cursor_value}` para próxima página.

### Ver Post Específico

```
GET /api/v1/posts/{post_id}
Authorization: Bearer {token}
```

Retorna o post com comentários.

### Deletar Post

```
DELETE /api/v1/posts/{post_id}
Authorization: Bearer {token}
```

Soft delete. Apenas o autor pode deletar.

### Curtir/Descurtir Post (toggle)

```
POST /api/v1/posts/{post_id}/like
Authorization: Bearer {token}
```

### Comentar em Post

```
POST /api/v1/posts/{post_id}/comments
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**

```json
{
  "content": "Muito bom! Continue assim!"
}
```

| Campo   | Tipo   | Regras                      |
|---------|--------|-----------------------------|
| content | string | obrigatório, max 500 chars  |

### Deletar Comentário

```
DELETE /api/v1/posts/{post_id}/comments/{comment_id}
Authorization: Bearer {token}
```

---

## 10. Treinos (Workout Logs)

### Registrar Treino Concluído

```
POST /api/v1/workouts/finish
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: <uuid>
```

**Body:**

```json
{
  "date": "2026-04-07",
  "time_start": "07:00:00",
  "time_end": "08:15:00",
  "plan_workout_id": null,
  "observations": "Foco em hipertrofia",
  "exercises": [
    {
      "exercise_id": "uuid-do-exercicio",
      "sets": 4,
      "reps": 12,
      "weight_kg": 80
    },
    {
      "exercise_id": "uuid-do-exercicio-2",
      "sets": 3,
      "reps": 10,
      "weight_kg": 60
    }
  ]
}
```

| Campo                    | Tipo    | Regras                                    |
|--------------------------|---------|-------------------------------------------|
| date                     | date    | obrigatório                               |
| time_start               | string  | obrigatório, formato `H:i:s`             |
| time_end                 | string  | obrigatório, formato `H:i:s`             |
| plan_workout_id          | uuid    | nullable, deve existir em plan_workouts   |
| observations             | string  | nullable                                  |
| exercises                | array   | obrigatório, min 1 item                   |
| exercises.*.exercise_id  | uuid    | obrigatório, deve existir em exercises    |
| exercises.*.sets         | integer | obrigatório                               |
| exercises.*.reps         | integer | obrigatório                               |
| exercises.*.weight_kg    | numeric | obrigatório                               |

**Resposta 201:** Retorna o workout log com análise de IA:

```json
{
  "message": "Treino finalizado com sucesso!",
  "log": {
    "id": "uuid",
    "date": "2026-04-07",
    "modality": "strength",
    "duration_min": 75,
    "calories_burned": 450.0,
    "muscles_trained": ["peitoral", "tríceps", "ombros"],
    "observations": "Foco em hipertrofia",
    "ai_feedback": "Ótimo treino! Volume adequado para hipertrofia...",
    "mood": null,
    "external_source": "manual",
    "exercises": [
      { "exercise_id": "uuid", "sets": 4, "reps": 12, "weight_kg": 80 }
    ],
    "created_at": "2026-04-07T08:30:00+00:00"
  }
}
```

> Concede XP base de **100** (`workout_strength`, cap diário 150). Verifica badges de treino (10/50/100 treinos).

---

## 11. Refeições (Meal Logs)

### Registrar Refeição por Texto

```
POST /api/v1/meals/analyze-text
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: <uuid>
```

**Body:**

```json
{
  "date": "2026-04-07",
  "meal_type": "lunch",
  "text_description": "Arroz integral, frango grelhado 200g, brócolis e batata doce"
}
```

### Registrar Refeição por Imagem

```
POST /api/v1/meals/analyze-image
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: <uuid>
```

**Body:**

```json
{
  "date": "2026-04-07",
  "meal_type": "dinner",
  "image_base64": "/9j/4AAQSkZJRg..."
}
```

| Campo            | Tipo   | Regras                                                                            |
|------------------|--------|-----------------------------------------------------------------------------------|
| date             | date   | obrigatório                                                                       |
| meal_type        | string | obrigatório, valores: `breakfast`, `snack`, `lunch`, `dinner`, `pre_workout`, `post_workout` |
| text_description | string | obrigatório (apenas analyze-text)                                                 |
| image_base64     | string | obrigatório (apenas analyze-image)                                                |

**Resposta 201:**

```json
{
  "message": "Refeição registrada via IA.",
  "log": {
    "id": "uuid",
    "date": "2026-04-07",
    "meal_type": "lunch",
    "calories_consumed": 650.5,
    "protein_g": 45.2,
    "carbs_g": 80.0,
    "fat_g": 12.4,
    "fiber_g": 6.1,
    "user_note": null,
    "ai_feedback": "Refeição equilibrada...",
    "items_json": [
      { "name": "Arroz integral", "quantity": "150g", "calories": 180 },
      { "name": "Frango grelhado", "quantity": "200g", "calories": 330 }
    ],
    "created_at": "2026-04-07T13:05:11+00:00"
  }
}
```

> `calories_consumed`, `protein_g`, `carbs_g`, `fat_g` e `fiber_g` são `float` (nunca strings) — `MealLogResource` faz cast explícito de `(float)`.
>
> Concede +20 XP (uma vez por dia).

---

## 12. Planos de IA

### Gerar Plano de Treino

```
POST /api/v1/plans/generate-workout
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**

```json
{
  "goal": "hipertrofia",
  "muscles": "peito, costas, pernas",
  "level": "intermediate",
  "days_per_week": 5,
  "workout_time_minutes": 60,
  "limitations": "dor no ombro direito",
  "location": "gym"
}
```

| Campo                | Tipo    | Regras                                              |
|----------------------|---------|-----------------------------------------------------|
| goal                 | string  | obrigatório, max 100                                |
| muscles              | string  | nullable, max 255                                   |
| level                | string  | obrigatório, valores: `beginner`, `intermediate`, `advanced` |
| days_per_week        | integer | obrigatório, min 1, max 7                           |
| workout_time_minutes | integer | obrigatório, min 15, max 180                        |
| limitations          | string  | nullable, max 500                                   |
| location             | string  | obrigatório, valores: `home`, `gym`                 |

**Resposta 201:** Retorna `AiPlan` com treinos estruturados por dia da semana, cada um com exercícios detalhados (nome, séries, reps, descanso, peso sugerido).

### Gerar Plano Alimentar

```
POST /api/v1/plans/generate-meal
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**

```json
{
  "goal": "emagrecimento",
  "daily_calories": 1800,
  "dietary_preferences": "sem lactose"
}
```

**Resposta 201:** Retorna `AiPlan` com refeições estruturadas e macros detalhados.

---

## 13. Gamificação

### Leaderboards

```
GET /api/v1/gamification/leaderboard/weekly
GET /api/v1/gamification/leaderboard/monthly
GET /api/v1/gamification/leaderboard/alltime
GET /api/v1/gamification/leaderboard/friends
Authorization: Bearer {token}
```

Todos suportam `?limit=N` (max 100, padrão 20). Cache de 5 minutos (exceto friends).

**Resposta 200:**

```json
[
  {
    "user_id": "uuid",
    "name": "João",
    "xp": 1250,
    "rank": 1
  }
]
```

### Conquistas do Usuário

```
GET /api/v1/gamification/achievements
Authorization: Bearer {token}
```

**Resposta 200:**

```json
[
  {
    "slug": "streak_7",
    "name": "Uma semana firme",
    "description": "Mantenha uma sequência de 7 dias",
    "icon": "🔥",
    "category": "consistency",
    "unlocked_at": "2026-03-20T14:30:00Z",
    "xp_received": 50
  }
]
```

### Histórico de XP

```
GET /api/v1/gamification/xp-history
Authorization: Bearer {token}
```

Paginado (20 por página). Suporta `?page=N`.

**Resposta 200:**

```json
{
  "data": [
    {
      "type": "workout_completed",
      "xp_gained": 30,
      "description": "Treino registrado",
      "date": "2026-04-07",
      "xp_total_snapshot": 480,
      "created_at": "2026-04-07T08:30:00Z"
    }
  ],
  "current_page": 1,
  "last_page": 3
}
```

---

## 14. Privacidade e LGPD

### Exportar Todos os Dados

```
GET /api/v1/privacy/my-data
Authorization: Bearer {token}
```

Retorna JSON com todos os dados do usuário (perfil, onboarding, treinos, refeições, gamificação, posts, amizades).

### Excluir Conta Permanentemente

```
DELETE /api/v1/privacy/delete-account
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**

```json
{
  "password": "senhaAtual123"
}
```

> Exclusão em cascata de todos os dados do usuário. Irreversível.

---

## 15. Health Check

```
GET /api/health
```

Não requer autenticação.

**Resposta 200:**

```json
{
  "status": "ok",
  "database": "ok",
  "redis": "ok"
}
```

---

## 16. Metas do Usuário (Goals)

> Endpoints **root-level** (não usam o prefixo `v1`). Cada usuário tem **uma** linha ativa em `user_goals` (`is_active = true`); os PUTs fazem `updateOrCreate`.

### Consultar Metas Ativas

```
GET /api/goals
Authorization: Bearer {token}
```

**Resposta 200** (shape estável `UserGoalResource` — mesmas chaves quando vazio ou preenchido):

```json
{
  "id": "uuid|null",
  "user_id": "uuid",
  "main_goal": "hypertrophy|null",
  "diet_objective": "muscle_gain|null",
  "goal_calories_day": 2200,
  "goal_steps_day": 10000,
  "goal_weight_kg": 78.5,
  "goal_protein_g": 150.0,
  "goal_carbs_g": 260.0,
  "goal_fat_g": 60.0,
  "goal_workouts_week": 4,
  "goal_water_liters": 2.5,
  "deadline": "2026-12-31|null",
  "is_active": true,
  "created_at": "2026-04-01T08:00:00+00:00",
  "updated_at": "2026-04-15T09:30:00+00:00"
}
```

> Quando o usuário ainda não tem meta cadastrada, todos os campos numéricos vêm `null`, `id`/`created_at`/`updated_at` vêm `null` e `is_active` vem `false`. O frontend pode confiar no mesmo schema sempre.

### Atualizar Metas de Exercício

```
PUT /api/goals/exercise
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**

```json
{
  "goal_steps_day": 10000,
  "goal_workouts_week": 4
}
```

| Campo               | Tipo    | Regras                          |
|---------------------|---------|---------------------------------|
| goal_steps_day      | integer | nullable, min 0, max 100000     |
| goal_workouts_week  | integer | nullable, min 0, max 14         |

**Resposta 200:**

```json
{
  "message": "Exercise goals updated successfully.",
  "goal": { "id": "uuid", "user_id": "uuid", "goal_steps_day": 10000, "goal_workouts_week": 4, "is_active": true, "...": "..." }
}
```

### Atualizar Metas de Alimentação

```
PUT /api/goals/alimentation
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**

```json
{
  "diet_objective": "muscle_gain",
  "goal_calories_day": 2200,
  "goal_protein_g": 150,
  "goal_carbs_g": 260,
  "goal_fat_g": 60,
  "goal_water_liters": 2.5
}
```

| Campo              | Tipo    | Regras                                                                  |
|--------------------|---------|-------------------------------------------------------------------------|
| diet_objective     | string  | nullable, valores: `weight_loss`, `maintenance`, `muscle_gain` (persistido em `user_goals.diet_objective`; também serve de hint para o auto-cálculo de macros) |
| goal_calories_day  | integer | nullable, min 0, max 10000                                              |
| goal_protein_g     | numeric | nullable, min 0, max 1000                                               |
| goal_carbs_g       | numeric | nullable, min 0, max 1000                                               |
| goal_fat_g         | numeric | nullable, min 0, max 500                                                |
| goal_water_liters  | numeric | nullable, min 0, max 20                                                 |

> **Auto-cálculo:** se o `diet_objective` for informado e algum dos macros (`goal_calories_day`, `goal_protein_g`, `goal_carbs_g`, `goal_fat_g`) estiver ausente/vazio, o backend calcula valores padrão a partir do onboarding (BMR Mifflin-St Jeor + fator de atividade) via `MacroGoalCalculator`.

**Resposta 200:** mesmo `goal` no shape `UserGoalResource` mostrado em `GET /api/goals`.

```json
{
  "message": "Alimentation goals updated successfully.",
  "goal": { "id": "uuid", "user_id": "uuid", "diet_objective": "muscle_gain", "goal_calories_day": 2200, "...": "..." }
}
```

---

## 17. Dashboard Agregado

> Endpoints **root-level** (não usam o prefixo `v1`). Servem como agregadores backend-for-frontend para as três páginas principais (Hub, Alimentação, Exercícios), evitando o N+1 que ocorreria se o app montasse cada cartão a partir de chamadas separadas. Toda a lógica de composição vive em `App\Services\Dashboard\DashboardService`.

### Hub Principal

```
GET /api/dashboard
Authorization: Bearer {token}
```

**Resposta 200:** payload consolidado para a tela inicial — metas do dia, refeições já registradas, treinos da semana, hidratação, gráfico nutricional dos últimos 7 dias, gamificação. A forma exata é definida em `DashboardService::buildHome()`; o frontend consome via `normalizeDashboardData.ts`.

### Página Alimentação

```
GET /api/dashboard/alimentation
Authorization: Bearer {token}
```

**Resposta 200:** metas alimentares ativas, totais consumidos, macros, hidratação do dia e refeições agrupadas por `meal_type` (`breakfast`, `snack`, `lunch`, `dinner`, `pre_workout`, `post_workout`). Vide `DashboardService::buildAlimentation()`.

### Página Exercícios

```
GET /api/dashboard/exercise
Authorization: Bearer {token}
```

**Resposta 200:** meta semanal, treinos da semana, estatísticas (calorias queimadas, duração total), treino do dia (do plano ativo, se houver) e histórico dos últimos 30 dias. Vide `DashboardService::buildExercise()`.

> **Nota de versionamento:** quando precisar de campos adicionais, prefira estender o agregador no backend a fazer chamadas extras no frontend.

---

## 18. Catálogo de Exercícios

```
GET /api/v1/exercises
Authorization: Bearer {token}
```

**Query params:**

| Param         | Tipo   | Regras                                  |
|---------------|--------|-----------------------------------------|
| muscle_group  | string | opcional, max 60                        |
| category      | string | opcional, max 60                        |
| search        | string | opcional, max 80, busca por `name LIKE %x%` |

**Resposta 200:** paginado por 50 itens (paginator padrão Laravel envelopado em `data`):

```json
{
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "uuid",
        "name": "Supino reto com barra",
        "muscle_group": "chest",
        "category": "strength",
        "difficulty": "intermediate",
        "is_active": true
      }
    ],
    "per_page": 50,
    "last_page": 4,
    "total": 187
  }
}
```

---

## 19. Cárdio, PRs e Sobrecarga Progressiva

### Registrar Sessão de Cárdio

```
POST /api/v1/workouts/cardio
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: <uuid>
```

**Body:**

```json
{
  "date": "2026-04-07",
  "duration_min": 35,
  "calories_burned": 320,
  "distance_m": 5400,
  "pace_seconds_per_km": 388,
  "avg_hr": 148,
  "max_hr": 172,
  "route_polyline": "u{~vFvyys@...",
  "external_source": "manual",
  "external_id": null
}
```

| Campo               | Tipo    | Regras                                                  |
|---------------------|---------|---------------------------------------------------------|
| date                | date    | obrigatório                                             |
| duration_min        | integer | obrigatório                                             |
| calories_burned     | numeric | obrigatório                                             |
| distance_m          | integer | nullable                                                |
| pace_seconds_per_km | integer | nullable                                                |
| avg_hr / max_hr     | integer | nullable                                                |
| route_polyline      | string  | nullable (Encoded Polyline 5 — usado pelo asset cardio) |
| external_source     | string  | obrigatório, valores: `manual`, `healthkit`, `googlefit`, `garmin` |
| external_id         | string  | nullable; deduplica importações de wearable             |

**Resposta 201:**

```json
{
  "message": "Treino de cárdio registrado com sucesso.",
  "workout_log": { "id": "uuid", "modality": "cardio", "duration_min": 35, "calories_burned": 320.0, "distance_m": 5400, "...": "..." }
}
```

> Concede XP base **80** (`workout_cardio`, cap diário 112).

### Listar Recordes Pessoais

```
GET /api/v1/workouts/personal-records
Authorization: Bearer {token}
```

**Resposta 200:** retorna o último PR por exercício (1RM Epley):

```json
{
  "personal_records": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "exercise_id": "uuid",
      "estimated_1rm_kg": 102.5,
      "weight_kg": 90,
      "reps": 5,
      "achieved_at": "2026-04-02T08:14:00Z",
      "exercise": { "id": "uuid", "name": "Supino reto", "muscle_group": "chest", "category": "strength" }
    }
  ]
}
```

### Sugestão de Carga Progressiva

```
GET /api/v1/workouts/exercises/{exercise_id}/suggest-load
Authorization: Bearer {token}
```

**Resposta 200:** sugestão calculada por `ProgressiveOverloadService` (RF-08/09). Shape:

```json
{
  "suggested_weight_kg": 82.5,
  "suggested_reps": 8,
  "rationale": "Última série completou 8x80kg com RPE baixo — sugerido +2.5kg.",
  "based_on_pr": true
}
```

### Histórico de Cargas

```
GET /api/v1/workouts/exercises/{exercise_id}/history
Authorization: Bearer {token}
```

**Resposta 200:**

```json
{
  "history": [
    { "date": "2026-04-02", "sets": 4, "reps": 8, "weight_kg": 80, "estimated_1rm_kg": 100.0 },
    { "date": "2026-03-28", "sets": 4, "reps": 8, "weight_kg": 77.5, "estimated_1rm_kg": 96.9 }
  ]
}
```

---

## 20. Nutrição: Resumo Diário e Ajustes

### Resumo Nutricional do Dia

```
GET /api/v1/nutrition/today
Authorization: Bearer {token}
```

**Resposta 200:**

```json
{
  "date": "2026-04-07",
  "goals":   { "calories": 2200, "protein_g": 150, "carbs_g": 260, "fat_g": 60 },
  "consumed":{ "calories": 1380, "protein_g": 92,  "carbs_g": 165, "fat_g": 38 },
  "delta_kcal": -820,
  "adjustment_ratio": 1.0,
  "dilution_active": false,
  "compensation_kcal": 0,
  "remaining_calories": 820
}
```

> `delta_kcal` negativo = ainda há calorias para consumir; positivo = excedeu a meta. `adjustment_ratio`/`dilution_active` vêm do `DietEngineService` (RF-01: rebalanceamento dinâmico de macros).

### Ajustes de Dieta

```
GET /api/v1/diet-adjustments?date=2026-04-07
Authorization: Bearer {token}
```

**Query params:** `date` (opcional, default = hoje no fuso do usuário).

**Resposta 200:**

```json
{
  "date": "2026-04-07",
  "adjustments": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "target_date": "2026-04-07",
      "delta_kcal": -150,
      "delta_protein_g": 5,
      "delta_carbs_g": -20,
      "delta_fat_g": -3,
      "mode": "redistribute",
      "applied_at": null,
      "created_at": "2026-04-07T12:30:00Z"
    }
  ]
}
```

---

## 21. AI Plans — Treino (CRUD)

> O endpoint `POST /api/v1/plans/generate-workout` (geração) já está documentado na seção **12. Planos de IA**. Esta seção cobre o ciclo de vida (listar/ativar/arquivar) usado pela página Exercícios.

### Listar Planos do Usuário

```
GET /api/v1/plans?type=workout
Authorization: Bearer {token}
```

**Query params:** `type` opcional (`workout` ou `nutritional`).

**Resposta 200:**

```json
{
  "plans": [
    {
      "id": "uuid",
      "type": "workout",
      "version": 1,
      "status": "active",
      "valid_from": "2026-04-01",
      "valid_until": "2026-05-27",
      "plan_workouts": [
        {
          "id": "uuid",
          "day_of_week": 1,
          "workout_name": "Push A",
          "exercises": [ { "exercise_id": "uuid", "rec_sets": 4, "rec_reps": 10, "rec_weight_kg": 60, "rest_sec": 90 } ]
        }
      ],
      "plan_meals": []
    }
  ]
}
```

### Detalhe do Plano

```
GET /api/v1/plans/{id}
Authorization: Bearer {token}
```

**Resposta 200:** `{ "plan": { ... mesma forma do item acima ... } }`. Retorna 404 se o plano não pertencer ao usuário autenticado.

### Ativar Plano

```
PATCH /api/v1/plans/{id}/activate
Authorization: Bearer {token}
```

> Marca o plano como `status = 'active'` e arquiva (`status = 'replaced'`) qualquer outro plano ativo do mesmo `type`.

**Resposta 200:** `{ "message": "Plan activated!", "plan": { ... } }`

### Arquivar Plano

```
PATCH /api/v1/plans/{id}/archive
Authorization: Bearer {token}
```

**Resposta 200:** `{ "message": "Plan archived.", "plan": { ... } }`

> Status possíveis: `draft` | `active` | `archived` | `replaced`.

---

## 22. Medidas Corporais (Body Measurements)

### Listar Medidas

```
GET /api/v1/measurements?limit=30
Authorization: Bearer {token}
```

| Param  | Tipo    | Regras                                           |
|--------|---------|--------------------------------------------------|
| limit  | integer | opcional, default 30, max 365                    |

**Resposta 200:** `{ "data": [ ... ] }` ordenado por `date DESC`. Cada item: `id`, `user_id`, `date`, `weight_kg`, `body_fat_pct`, `muscle_mass_kg`, `waist_circumference_cm`, `hip_circumference_cm`, `arm_circumference_cm`, `bmi`.

### Registrar Medida

```
POST /api/v1/measurements
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: <uuid>
```

**Body:**

```json
{
  "date": "2026-04-18",
  "weight_kg": 78.5,
  "body_fat_pct": 18.2,
  "muscle_mass_kg": 35.4,
  "waist_circumference_cm": 82,
  "hip_circumference_cm": 95,
  "arm_circumference_cm": 36
}
```

| Campo                   | Tipo    | Regras                          |
|-------------------------|---------|---------------------------------|
| date                    | date    | nullable (default = hoje)       |
| weight_kg               | numeric | **obrigatório**, min 30, max 300|
| body_fat_pct            | numeric | nullable, min 3, max 60         |
| muscle_mass_kg          | numeric | nullable, min 10, max 200       |
| waist_circumference_cm  | numeric | nullable, min 30, max 250       |
| hip_circumference_cm    | numeric | nullable, min 30, max 250       |
| arm_circumference_cm    | numeric | nullable, min 10, max 100       |

> Faz `updateOrCreate` por `(user_id, date)` — registrar duas vezes no mesmo dia atualiza a medida existente. Calcula `bmi` automaticamente quando há `height_cm` no onboarding. Concede XP `weight_logged`.

**Resposta 201:**

```json
{
  "measurement": { "id": "uuid", "weight_kg": 78.5, "bmi": 24.2, "...": "..." },
  "xp_gained": 30
}
```

### Remover Medida

```
DELETE /api/v1/measurements/{id}
Authorization: Bearer {token}
```

**Resposta 200:** `{ "message": "Deleted" }`. 404 se não pertencer ao usuário.

---

## 23. Hidratação (Water Logs)

### Listar Registros

```
GET /api/v1/water-logs?date=2026-04-19&limit=60
Authorization: Bearer {token}
```

| Param  | Tipo    | Regras                                                    |
|--------|---------|-----------------------------------------------------------|
| date   | date    | opcional — filtra apenas o dia informado                  |
| limit  | integer | opcional, default 60, max 365                             |

**Resposta 200:**

```json
{
  "data": [
    { "id": "uuid", "user_id": "uuid", "date": "2026-04-19", "time": "14:30", "liters": 0.25 }
  ],
  "today_total": 1.75
}
```

> `today_total` é sempre o total de hoje (no fuso do servidor), independente do filtro `date`.

### Registrar Consumo

```
POST /api/v1/water-logs
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: <uuid>
```

**Body:**

```json
{
  "liters": 0.25,
  "date": "2026-04-19",
  "time": "14:30"
}
```

| Campo  | Tipo    | Regras                                |
|--------|---------|---------------------------------------|
| liters | numeric | **obrigatório**, min 0.01, max 10     |
| date   | date    | nullable (default = hoje)             |
| time   | string  | nullable, formato `H:i` (default = agora) |

> Múltiplos registros por dia são esperados (cada copo/garrafa). Não há `updateOrCreate` aqui.

**Resposta 201:**

```json
{
  "log": { "id": "uuid", "date": "2026-04-19", "time": "14:30", "liters": 0.25 },
  "day_total": 2.0
}
```

### Remover Registro

```
DELETE /api/v1/water-logs/{id}
Authorization: Bearer {token}
```

**Resposta 200:** `{ "message": "Deleted" }`.

---

## Fluxo Completo do Usuário

```
┌─────────────────────────────────────────────────────────────────┐
│                     FLUXO PRINCIPAL                             │
│                                                                 │
│  1. REGISTRO                                                    │
│     POST /api/register                                          │
│     → Recebe token de autenticação                              │
│     → Perfil de gamificação criado automaticamente              │
│                                                                 │
│  2. ONBOARDING                                                  │
│     POST /api/onboarding                                        │
│     → Informar dados físicos (peso, altura, idade, etc.)        │
│     → Backend calcula BMR e TDEE                                │
│                                                                 │
│  3. GERAR PLANOS COM IA                                         │
│     POST /api/v1/plans/generate-workout                         │
│     POST /api/v1/plans/generate-meal                            │
│     → Plano personalizado gerado pela IA                        │
│                                                                 │
│  4. DIA A DIA DO USUÁRIO                                        │
│     ┌───────────────────────────────────────────┐               │
│     │  a) Abrir app → GET /api/me               │               │
│     │     → +10 XP login diário                  │               │
│     │                                            │               │
│     │  b) Registrar refeição                     │               │
│     │     POST /api/v1/meals/analyze-text        │               │
│     │     POST /api/v1/meals/analyze-image       │               │
│     │     → +20 XP (1x/dia)                      │               │
│     │     → IA calcula calorias e macros         │               │
│     │                                            │               │
│     │  c) Registrar treino concluído             │               │
│     │     POST /api/v1/workouts/finish           │               │
│     │     → +30 XP (1x/dia)                      │               │
│     │     → IA analisa músculos e calorias       │               │
│     │     → Verifica badges de treino            │               │
│     │                                            │               │
│     │  d) Interagir socialmente                  │               │
│     │     GET /api/v1/feed                       │               │
│     │     POST /api/v1/posts                     │               │
│     │     POST /api/v1/posts/{id}/like           │               │
│     │     POST /api/v1/posts/{id}/comments       │               │
│     └───────────────────────────────────────────┘               │
│                                                                 │
│  5. ACOMPANHAMENTO                                              │
│     GET /api/v1/gamification/leaderboard/*                      │
│     GET /api/v1/gamification/achievements                       │
│     GET /api/v1/gamification/xp-history                         │
│                                                                 │
│  6. SOCIAL                                                      │
│     POST /api/v1/friends/request                                │
│     GET /api/v1/friends                                         │
│     GET /api/v1/users/search?q=                                 │
│     GET /api/v1/users/{username}                                │
└─────────────────────────────────────────────────────────────────┘
```

---

## Sistema de Gamificação - Regras

> **Fonte da verdade:** `projeto_fitness_backend/config/gamification.php`. Os valores abaixo são lidos por `App\Services\GamificationService` — não duplique no frontend.

### Ganho de XP (eventos SRS v1.0)

| Evento                 | Base XP | Cap diário | Streak max | Streak full at | Único |
|------------------------|---------|------------|------------|----------------|-------|
| `workout_strength`     | 100     | 150        | 1.5×       | 7 dias         | —     |
| `workout_cardio`       | 80      | 112        | 1.4×       | 7 dias         | —     |
| `clean_diet_day`       | 60      | 120        | 2.0×       | 15 dias        | —     |
| `protein_goal_met`     | 50      | 75         | 1.5×       | 7 dias         | —     |
| `water_goal_met`       | 30      | 39         | 1.3×       | 5 dias         | —     |
| `pr_set`               | 200     | 200        | 1.0×       | —              | sim (1× por exercício) |
| `progress_photo`       | 20      | 20         | 1.0×       | 1 dia          | —     |
| `asset_shared`         | 15      | 30         | 1.0×       | 1 dia          | —     |
| `daily_login` (legacy) | 10      | 15         | 1.5×       | 35 dias        | —     |
| `meal_logged` (legacy) | 20      | 30         | 1.5×       | 35 dias        | —     |
| `weight_logged`        | 15      | 15         | 1.0×       | 1 dia          | —     |

**Cap diário global:** 300 XP. Eventos legacy (`daily_login`, `meal_logged`) coexistem com os SRS para retrocompatibilidade — ao registrar uma refeição o usuário recebe `meal_logged`; o bônus `clean_diet_day` é avaliado em batch quando metas do dia são atingidas.

### Bônus de Streak

- Multiplicador por evento: `mult = min(1 + (streak_days / full_at_days) × (max_mult − 1), max_mult)`.
- Cada evento tem seu próprio `max_mult` e `full_at_days` (ver tabela acima); não há fator global único.
- Aplica-se sobre o `base` antes do `cap` diário.

### Safety Day

- 1 por semana (semana ISO).
- Preserva o streak sem atividade do dia.
- Não gera XP; apenas evita o reset de `streak_days`.

### Penalidades

| Situação                                    | Penalidade XP |
|---------------------------------------------|---------------|
| Calorias não atingidas (`calories_missed`)  | −15           |
| Semana com < 3 treinos (`weekly_workouts`)  | −50           |

### Níveis (SRS v1.0 — 8 níveis)

| Nível | Título        | XP mínimo | XP máximo | Cor       | Ícone | Benefício                                |
|-------|---------------|-----------|-----------|-----------|-------|------------------------------------------|
| 1     | Iniciante     | 0         | 499       | `#94a3b8` | 🌱    | Acesso básico ao app                     |
| 2     | Atleta        | 500       | 1.499     | `#22c55e` | ⚡    | Rankings regionais                       |
| 3     | Guerreiro     | 1.500     | 3.999     | `#3b82f6` | ⚔️    | Troféus Bronze                           |
| 4     | Especialista  | 4.000     | 9.999     | `#8b5cf6` | 🎯    | Troféus Prata + ranking global           |
| 5     | Elite         | 10.000    | 24.999    | `#f59e0b` | 🔥    | Troféus Ouro + badge de perfil           |
| 6     | Lenda         | 25.000    | 59.999    | `#f97316` | 🏅    | Troféus Platina + perfil verificado      |
| 7     | Mestre        | 60.000    | 149.999   | `#ef4444` | 🏋️    | Acesso a desafios exclusivos             |
| 8     | GOAT          | 150.000   | ∞         | `#f0a500` | 👑    | Hall of Fame global + cosmético exclusivo |

Tiers de troféu desbloqueiam ao atingir o nível: **Bronze** (3), **Prata** (4), **Ouro** (5), **Platina** (6).

### Conquistas (Badges)

Slugs reais semeados via `AchievementSeeder` / migration `2026_03_06_200851_f_create_fitai_gamification_tables`:

| Slug                      | Nome                  | Condição               | Categoria    | Tier    | XP   |
|---------------------------|-----------------------|------------------------|--------------|---------|------|
| `streak_7`                | 7 Days Streak         | 7 dias seguidos        | consistency  | bronze  | 150  |
| `streak_30`               | 30 Days Streak        | 30 dias seguidos       | consistency  | silver  | 400  |
| `streak_90`               | 90 Days Streak        | 90 dias seguidos       | consistency  | gold    | 1200 |
| `treinos_10`              | 10 Workouts           | 10 treinos totais      | workout      | bronze  | 100  |
| `treinos_50`              | 50 Workouts           | 50 treinos totais      | workout      | silver  | 300  |
| `treinos_100`             | 100 Workouts          | 100 treinos totais     | workout      | gold    | 800  |
| `agua_5dias`              | Hydrated              | 5 dias de meta hídrica | water        | bronze  | 50   |
| `agua_20dias`             | Always Hydrated       | 20 dias de meta hídrica| water        | silver  | 150  |
| `hardcore_semana`         | Hardcore Week         | 6 treinos numa semana  | hardcore     | silver  | 250  |
| `ativo_3meses`            | 3 Active Months       | 90 dias ativo          | hardcore     | gold    | 700  |
| `clean_week`              | Primeira Semana Limpa | 7 dias sem furar dieta | consistency  | bronze  | 300  |
| `protein_machine`         | Máquina de Proteína   | 10 dias meta proteína  | nutrition    | bronze  | 250  |
| `armored_month`           | Mês Blindado          | 30 dias sem furar dieta| consistency  | silver  | 1000 |
| `pr_broken`               | PR Quebrado           | novo recorde 1RM       | workout      | silver  | 500  |
| `gold_trident` 🔒         | Tridente de Ouro      | treino+dieta+água 60d  | hardcore     | gold    | 5000 |
| `top_1_percent_global` 🔒 | Top 1% Global         | top 1% no leaderboard  | special      | gold    | 3000 |
| `evofit_legendary` 🔒     | EvoFit Lendário       | 365 dias compliance    | hardcore     | platinum| 20000|
| `the_immortal` 🔒         | O Imortal             | nível 8 (GOAT)         | special      | platinum| 50000|

> 🔒 = `is_hidden = true` (badge surpresa, não aparece em listagens públicas até ser desbloqueada).

---

## Tipos Enumerados (Referência)

### meal_type
`breakfast` | `snack` | `lunch` | `dinner` | `pre_workout` | `post_workout`

### gender
`M` | `F` | `other` | `prefer_not_to_say`

### work_style
`white_collar` | `blue_collar` | `sedentary` | `moderate` | `active`

### level (plano de treino)
`beginner` | `intermediate` | `advanced`

### location (plano de treino)
`home` | `gym`

### visibility (post)
`public` | `friends_only`

### post type
`text` | `achievement` | `goal_completed` | `workout_completed` | `level_up`

### friendship status
`pending` | `accepted` | `blocked`

### xp_transaction type
`workout_logged` | `workout_completed` | `long_workout` | `water_goal` | `weight_logged` | `streak_bonus` | `quest_completed` | `achievement_unlocked` | `meal_logged` | `manual_adjustment` | `daily_login` | `penalty_workout` | `penalty_calories`

### achievement category
`consistency` | `workout` | `water` | `nutrition` | `hardcore` | `special`

### main_goal (user goals)
`weight_loss` | `hypertrophy` | `maintenance` | `health` | `conditioning`

---

## Notas Técnicas para Integração

- **IDs:** Todos os IDs são UUID v4
- **Datas:** Formato ISO `YYYY-MM-DD`
- **Horários:** Formato `HH:mm:ss`
- **Timestamps:** ISO 8601 (`2026-04-07T08:30:00Z`)
- **Paginação:** Padrão Laravel (`page`, `per_page`, `current_page`, `last_page`). Feed usa cursor pagination (`cursor`)
- **Erros de validação:** HTTP 422 com body `{ "message": "...", "errors": { "campo": ["mensagem"] } }`
- **Não autenticado:** HTTP 401 `{ "message": "Unauthenticated." }`
- **Não encontrado:** HTTP 404
- **Soft deletes:** Posts e comentários usam soft delete (campo `deleted_at`)
- **IA:** Respostas da IA (Gemini) são em português
- **Rate limiting:** Nenhum rate limiting customizado configurado
- **CORS:** Configurado para localhost por padrão
