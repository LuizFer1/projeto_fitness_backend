# FitAI Backend - Guia de Integração para Agentes

> Documento completo para integração com a API REST do backend FitAI.
> Base URL: `http://localhost:8000/api`

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
  "id": "uuid",
  "date": "2026-04-07",
  "duration_min": 75,
  "calories_burned": 450,
  "muscles_trained": ["peitoral", "tríceps", "ombros"],
  "ai_feedback": "Ótimo treino! Volume adequado para hipertrofia...",
  "exercises": [...]
}
```

> Concede +30 XP (uma vez por dia). Verifica badges de treino (10/50/100 treinos).

---

## 11. Refeições (Meal Logs)

### Registrar Refeição por Texto

```
POST /api/v1/meals/analyze-text
Authorization: Bearer {token}
Content-Type: application/json
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
  "id": "uuid",
  "date": "2026-04-07",
  "meal_type": "lunch",
  "calories_consumed": 650,
  "protein_g": 45.0,
  "carbs_g": 80.0,
  "fat_g": 12.0,
  "ai_feedback": "Refeição equilibrada...",
  "items_json": [
    { "name": "Arroz integral", "quantity": "150g", "calories": 180 },
    { "name": "Frango grelhado", "quantity": "200g", "calories": 330 }
  ]
}
```

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

### Ganho de XP

| Ação              | XP    | Limite              |
|-------------------|-------|---------------------|
| Login diário      | +10   | 1x por dia          |
| Registrar refeição| +20   | 1x por dia          |
| Registrar treino  | +30   | 1x por dia          |
| Limite diário     | 300   | XP máximo por dia   |

### Bônus de Streak

- A cada 7 dias de sequência: +10% de bônus no XP
- Bônus máximo: +50% (streak de 35+ dias)
- Fórmula: `bonus = min((streak_days / 7) * 0.10, 0.50)`

### Safety Day

- 1 por semana (semana ISO)
- Preserva o streak sem atividade
- Sem penalidades aplicadas

### Penalidades

| Situação                        | Penalidade |
|---------------------------------|------------|
| Dia sem atividade (sem safety)  | Streak zerado + penalidades |
| Semana com < 3 treinos          | -50 XP     |

### Níveis

| Nível | XP Necessário |
|-------|---------------|
| 1     | 0             |
| 2     | 200           |
| 3     | 500           |
| 4     | 900           |
| 5     | 1.500         |
| 6     | 2.200         |
| 7     | 3.100         |
| 8     | 4.300         |
| 9     | 5.700         |
| 10    | 8.100         |

### Conquistas (Badges)

| Slug           | Condição                    | Categoria    |
|----------------|-----------------------------|--------------|
| streak_7       | 7 dias de sequência         | consistency  |
| streak_30      | 30 dias de sequência        | consistency  |
| streak_90      | 90 dias de sequência        | consistency  |
| workouts_10    | 10 treinos totais           | workout      |
| workouts_50    | 50 treinos totais           | workout      |
| workouts_100   | 100 treinos totais          | workout      |
| level_5        | Atingir nível 5             | special      |
| level_10       | Atingir nível 10            | special      |

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
