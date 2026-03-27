# 🎮 API de Gamificação — Documentação Frontend

> **Base URL:** `/api`
> **Autenticação:** Bearer Token (Sanctum) em todas as rotas.

---

## Índice

1. [Visão Geral — Como o XP funciona](#1-visão-geral)
2. [XP Automático (sem chamadas extras)](#2-xp-automático)
3. [GET /api/v1/rankings — Ranking](#3-ranking)
4. [GET /api/v1/rankings/profile — Perfil de Gamificação](#4-perfil-de-gamificação)
5. [GET /api/me — Dados do Usuário (inclui gamification)](#5-me-endpoint)
6. [Tabela de Níveis](#6-tabela-de-níveis)
7. [Badges Disponíveis](#7-badges-disponíveis)
8. [Regras de Negócio (resumo para UI)](#8-regras-de-negócio)
9. [Exemplos de Integração](#9-exemplos-de-integração)

---

## 1. Visão Geral

O sistema de gamificação funciona de forma **transparente** para o frontend. O backend concede XP automaticamente quando o usuário realiza ações. **O frontend NÃO precisa chamar nenhum endpoint especial para dar XP** — basta continuar usando os endpoints existentes normalmente.

### Ações que geram XP automaticamente

| Ação | XP Base | Limite | Endpoint que dispara |
|------|---------|--------|---------------------|
| Login / acessar perfil | +10 | 1x/dia | `POST /api/login` ou `GET /api/me` |
| Registrar refeição | +20 | 1x/dia | `POST /api/v1/meals/analyze-text` ou `analyze-image` |
| Finalizar treino | +30 | 1x/dia | `POST /api/v1/workouts/finish` |

> **Bônus de streak:** A cada 7 dias consecutivos ativos, o XP ganho recebe +10% de bônus (acumulável até +50% máximo).

---

## 2. XP Automático

O frontend **não precisa fazer nada diferente**. As respostas dos endpoints existentes continuam iguais. O XP é creditado internamente.

**O que muda para o frontend:**
- Após qualquer ação que dá XP, o campo `gamification` do usuário (via `/api/me`) estará atualizado.
- Recomenda-se chamar `/api/v1/rankings/profile` para mostrar o estado atualizado de XP, nível, streak e badges.

---

## 3. Ranking

### `GET /api/v1/rankings`

Retorna o ranking de usuários por período, com a posição do usuário logado sempre incluída.

**Query Parameters:**

| Param | Tipo | Obrigatório | Default | Valores |
|-------|------|-------------|---------|---------|
| `period` | string | Não | `weekly` | `weekly`, `monthly`, `all_time` |
| `limit` | integer | Não | `20` | 1–100 |

**Headers:**
```
Authorization: Bearer {token}
```

**Response `200 OK`:**
```json
{
  "period": "weekly",
  "rankings": [
    {
      "position": 1,
      "user_id": "uuid",
      "name": "João Silva",
      "avatar_url": "https://...",
      "period_xp": 450,
      "level": 3,
      "total_xp": 1280
    },
    {
      "position": 2,
      "user_id": "uuid",
      "name": "Maria",
      "avatar_url": null,
      "period_xp": 320,
      "level": 2,
      "total_xp": 890
    }
  ],
  "my_position": {
    "position": 5,
    "period_xp": 180,
    "level": 2,
    "total_xp": 620
  }
}
```

**Campos do ranking:**

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `position` | int | Posição no ranking |
| `user_id` | uuid | ID do usuário |
| `name` | string | Nickname (se tiver) ou "Nome Sobrenome" |
| `avatar_url` | string\|null | URL do avatar |
| `period_xp` | int | XP ganho no período selecionado |
| `level` | int | Nível atual (1–10) |
| `total_xp` | int | XP total acumulado desde o cadastro |

**`my_position`** — Sempre presente, mesmo que o usuário não esteja no top. Mostra a posição exata do usuário logado.

**Response `422`:**
```json
{
  "error": "Período inválido. Use: weekly, monthly, all_time"
}
```

> ⚠️ O ranking tem cache de 5 minutos. A posição do próprio usuário (`my_position`) é sempre calculada em tempo real.

---

## 4. Perfil de Gamificação

### `GET /api/v1/rankings/profile`

Retorna o perfil completo de gamificação do usuário logado, incluindo badges e notificações de novas conquistas.

**Headers:**
```
Authorization: Bearer {token}
```

**Response `200 OK`:**
```json
{
  "xp_total": 1280,
  "current_level": 3,
  "xp_to_next": 220,
  "current_streak": 12,
  "max_streak": 18,
  "current_week_xp": 180,
  "current_month_xp": 650,
  "total_workouts": 34,
  "badges": [
    {
      "slug": "streak_7",
      "name": "7 Days Streak",
      "description": "Active for 7 consecutive days",
      "icon": "🔥",
      "category": "consistency",
      "xp_received": 150,
      "unlocked_at": "2026-03-18T14:32:00.000000Z"
    },
    {
      "slug": "treinos_10",
      "name": "10 Workouts",
      "description": "Completed 10 workouts",
      "icon": "💪",
      "category": "workout",
      "xp_received": 100,
      "unlocked_at": "2026-03-10T09:15:00.000000Z"
    }
  ],
  "new_badges": [
    {
      "name": "7 Days Streak",
      "icon": "🔥",
      "xp": 150
    }
  ]
}
```

**Campos:**

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `xp_total` | int | XP total acumulado desde o cadastro |
| `current_level` | int | Nível atual (1–10) |
| `xp_to_next` | int | XP faltando para o próximo nível (0 se nível max) |
| `current_streak` | int | Dias consecutivos ativos agora |
| `max_streak` | int | Maior streak já alcançada |
| `current_week_xp` | int | XP ganho na semana corrente (seg–dom) |
| `current_month_xp` | int | XP ganho no mês corrente |
| `total_workouts` | int | Total de treinos registrados |
| `badges` | array | Todas as badges conquistadas (ordem: mais recente primeiro) |
| `new_badges` | array | Badges novas que o usuário **ainda não viu** |

### 🔔 Comportamento de `new_badges`

- `new_badges` contém badges que foram desbloqueadas mas **nunca foram exibidas ao usuário**.
- **Ao chamar este endpoint**, o backend automaticamente marca essas badges como "notificadas".
- Na **próxima chamada**, `new_badges` estará vazio (a menos que novas badges sejam desbloqueadas).
- **Use `new_badges` para exibir popups/toast de conquista no frontend.**

**Fluxo recomendado:**
1. Após login ou ação importante → chamar `GET /api/v1/rankings/profile`
2. Se `new_badges.length > 0` → exibir modal/toast de conquista
3. O array se limpa sozinho na próxima chamada

---

## 5. ME Endpoint

### `GET /api/me`

O endpoint `/api/me` já existente agora **também concede +10 XP de login diário** automaticamente, além de retornar os dados de gamificação no campo `gamification`.

**Response (trecho relevante):**
```json
{
  "id": "uuid",
  "name": "João",
  "last_name": "Silva",
  "email": "joao@email.com",
  "timezone": "America/Sao_Paulo",
  "gamification": {
    "id": "uuid",
    "user_id": "uuid",
    "xp_total": 1280,
    "current_level": 3,
    "xp_to_next": 220,
    "current_streak": 12,
    "max_streak": 18,
    "current_week_xp": 180,
    "current_month_xp": 650,
    "total_workouts": 34,
    "total_water_days": 10,
    "last_activity": "2026-03-20",
    "last_week_safety_day_used": null,
    "last_processed_date": "2026-03-19",
    "updated_at": "2026-03-20T13:45:00.000000Z"
  },
  "onboarding": { ... }
}
```

> 💡 Para dados resumidos no header/sidebar, use `gamification` do `/api/me`.
> Para tela completa de gamificação com badges, use `/api/v1/rankings/profile`.

---

## 6. Tabela de Níveis

| Nível | Título | XP Mínimo | XP Máximo | Cor | Ícone |
|-------|--------|-----------|-----------|-----|-------|
| 1 | Beginner | 0 | 199 | `#94a3b8` | 🌱 |
| 2 | Apprentice | 200 | 499 | `#22c55e` | ⚡ |
| 3 | Dedicated | 500 | 899 | `#3b82f6` | 💪 |
| 4 | Consistent | 900 | 1499 | `#8b5cf6` | 🎯 |
| 5 | Focused | 1500 | 2199 | `#f59e0b` | 🔥 |
| 6 | Determined | 2200 | 3099 | `#f97316` | 🏅 |
| 7 | Athlete | 3100 | 4299 | `#ef4444` | 🏋️ |
| 8 | Warrior | 4300 | 5699 | `#ec4899` | ⚔️ |
| 9 | Champion | 5700 | 8099 | `#06b6d4` | 🏆 |
| 10 | Elite | 8100 | ∞ | `#f0a500` | 👑 |

**Fórmula da barra de progresso:**
```typescript
const progress = ((xp_total - level_min_xp) / (level_max_xp - level_min_xp)) * 100;
// Ou alternativamente:
// xp_to_next é fornecido pela API, então:
const xpInCurrentLevel = (level_max_xp - level_min_xp) - xp_to_next;
const progress = (xpInCurrentLevel / (level_max_xp - level_min_xp)) * 100;
```

---

## 7. Badges Disponíveis

### Badges pré-cadastradas no banco

| Slug | Nome | Ícone | Categoria | XP Reward | Gatilho |
|------|------|-------|-----------|-----------|---------|
| `streak_7` | 7 Days Streak | 🔥 | consistency | 150 | 7 dias consecutivos |
| `streak_30` | 30 Days Streak | 🔥 | consistency | 400 | 30 dias consecutivos |
| `streak_90` | 90 Days Streak | 🌟 | consistency | 1200 | 90 dias consecutivos |
| `treinos_10` | 10 Workouts | 💪 | workout | 100 | 10 treinos totais |
| `treinos_50` | 50 Workouts | 💪 | workout | 300 | 50 treinos totais |
| `treinos_100` | 100 Workouts | 🏋️ | workout | 800 | 100 treinos totais |
| `agua_5dias` | Hydrated | 💧 | water | 50 | Meta de água 5 dias |
| `agua_20dias` | Always Hydrated | 💧 | water | 150 | Meta de água 20 dias |
| `hardcore_semana` | Hardcore Week | 🦾 | hardcore | 250 | 6 treinos em 1 semana |
| `ativo_3meses` | 3 Active Months | 🌎 | hardcore | 700 | 90 dias ativos |

### Categorias de badge

| Categoria | Cor sugerida |
|-----------|-------------|
| `consistency` | Laranja/Âmbar |
| `workout` | Azul |
| `water` | Ciano |
| `nutrition` | Verde |
| `hardcore` | Vermelho |
| `special` | Dourado |

---

## 8. Regras de Negócio (Resumo para UI)

### Streak
- A streak aumenta a cada dia em que o usuário realiza **pelo menos 1 ação** (login, refeição ou treino).
- Se o usuário não fizer nada no dia, a streak zera — **exceto** se o escudo de imprevisto estiver disponível.

### Escudo de Imprevisto (Safety Day)
- **1 por semana** (segunda a domingo, semana ISO).
- Consumido automaticamente no primeiro dia de ausência da semana.
- **Preserva a streak** e **isenta a penalidade de calorias** do dia.
- **NÃO isenta** a penalidade semanal de treinos.
- Não acumula entre semanas.
- O campo `last_week_safety_day_used` no `/api/me` mostra se já foi usado na semana (ex: `"2026-W12"` ou `null`).

### Penalidades
- **-15 XP/dia** se a meta calórica diária não for atingida (verificação automática à meia-noite do fuso do usuário).
- **-50 XP/semana** se menos de 3 treinos foram registrados na semana (verificação no domingo à noite).
- XP nunca fica negativo.

### Bônus de Streak
| Streak | Bônus |
|--------|-------|
| 0–6 dias | 0% |
| 7–13 dias | +10% |
| 14–20 dias | +20% |
| 21–27 dias | +30% |
| 28–34 dias | +40% |
| 35+ dias | +50% (máximo) |

---

## 9. Exemplos de Integração

### Header/Sidebar — Exibir nível e XP
```typescript
// Ao carregar o app, chamar /api/me
const user = await api.get('/api/me');
const { xp_total, current_level, xp_to_next, current_streak } = user.gamification;

// Exibir: "⚡ Nível 2 · 180 XP · 🔥 5 dias"
```

### Tela de Ranking
```typescript
// Tabs: Semanal | Mensal | Geral
const { rankings, my_position } = await api.get('/api/v1/rankings', {
  params: { period: 'weekly', limit: 20 }
});

// my_position.position = posição do usuário (ex: 5º lugar)
// rankings = top 20 usuários
```

### Popup de Badge Nova
```typescript
// Após login ou ação importante
const profile = await api.get('/api/v1/rankings/profile');

if (profile.new_badges.length > 0) {
  profile.new_badges.forEach(badge => {
    showToast(`🎉 Nova conquista: ${badge.icon} ${badge.name} (+${badge.xp} XP)`);
  });
}
// Na próxima chamada, new_badges estará vazio
```

### Progresso semanal de treinos
```typescript
// O backend não tem um endpoint específico de "progresso semanal de treinos"
// Mas o frontend pode contar pela lista de workout_logs da semana
// ou exibir o total_workouts do perfil comparado com a meta de 3/semana
const profile = await api.get('/api/v1/rankings/profile');
// profile.total_workouts = total geral (para badges)
// Para progresso semanal, contar os workout_logs da semana no frontend
```

### Atualizar timezone do usuário
```typescript
// Incluir timezone ao atualizar perfil (necessário para cálculos corretos)
await api.put('/api/profile', {
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
  // Ex: "America/Sao_Paulo"
});
```

> 💡 **Dica:** Detectar o timezone automaticamente no frontend com `Intl.DateTimeFormat().resolvedOptions().timeZone` e enviar no cadastro/login.
