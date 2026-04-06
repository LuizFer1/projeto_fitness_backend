# 🏋️ API de Log de Treinos — Documentação Frontend

> **Base URL:** `/api`
> **Autenticação:** Bearer Token (Sanctum) obrigatório.

---

## Índice

1. [Visão Geral — Registro de Treinos](#1-visão-geral)
2. [POST /api/v1/workouts/finish — Finalizar Treino](#2-finalizar-treino)
3. [Gamificação Integrada](#3-gamificação-integrada)
4. [Estrutura de Dados](#4-estrutura-de-dados)
5. [Exemplos de Integração](#5-exemplos-de-integração)

---

## 1. Visão Geral

O endpoint de log de treinos permite que o usuário registre uma sessão concluída. O sistema utiliza a **IA (Gemini)** para analisar os exercícios realizados e estimar calorias gastas, identificar músculos treinados e gerar um feedback motivacional personalizado.

---

## 2. Finalizar Treino

### `POST /api/v1/workouts/finish`

Salva os detalhes do treino e processa a análise via IA.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `date` | string (YYYY-MM-DD) | Sim | Data da realização do treino |
| `time_start` | string (HH:mm:ss) | Sim | Horário de início |
| `time_end` | string (HH:mm:ss) | Sim | Horário de término |
| `plan_workout_id` | uuid | Não | ID do treino do plano (se vier de um plano gerado) |
| `observations` | string | Não | Comentários do usuário sobre o treino |
| `exercises` | array | Sim | Lista de exercícios realizados (min: 1) |

**Estrutura de cada item em `exercises`:**

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `exercise_id` | uuid | ID do exercício no banco de dados |
| `sets` | integer | Número de séries realizadas |
| `reps` | integer | Número de repetições por série |
| `weight_kg` | number | Carga utilizada em kg |

**Exemplo de Payload:**
```json
{
  "date": "2026-03-20",
  "time_start": "14:15:00",
  "time_end": "15:30:00",
  "observations": "Foquei bem na descida, senti bastante o peitoral.",
  "exercises": [
    {
      "exercise_id": "8f8e8e8e-8e8e-8e8e-8e8e-8e8e8e8e8e8e",
      "sets": 4,
      "reps": 12,
      "weight_kg": 60
    },
    {
      "exercise_id": "7a7a7a7a-7a7a-7a7a-7a7a-7a7a7a7a7a7a",
      "sets": 3,
      "reps": 15,
      "weight_kg": 15
    }
  ]
}
```

**Response `201 Created`:**
```json
{
  "message": "Treino finalizado com sucesso!",
  "log": {
    "id": "uuid",
    "date": "2026-03-20",
    "duration_min": 75,
    "calories_burned": 420,
    "observations": "Foquei bem na descida...",
    "ai_feedback": "Excelente trabalho hoje! Sua constância no supino mostra grande evolução...",
    "muscles_trained": ["Peito", "Tríceps", "Ombro"],
    "workout_log_exercises": [...]
  }
}
```

**Response `422 Unprocessable Entity`:**
Ocorre em caso de erro de validação (ex: exercícios vazios) ou falha na análise da IA.

---

## 3. Gamificação Integrada

Ao chamar este endpoint com sucesso, o backend dispara automaticamente:
1. **Concessão de XP:** O usuário recebe **+30 XP** (aplicável apenas 1x ao dia).
2. **Bônus de Streak:** Se o usuário estiver em uma sequência, o bônus de XP (ex: +10%) é aplicado sobre os 30 XP.
3. **Verificação de Badges:** O sistema verifica se o usuário atingiu marcas históricas (ex: 10, 50 ou 100 treinos totais) e concede as badges correspondentes.

> 💡 **Dica Frontend:** Após o sucesso desta requisição, é recomendado atualizar os dados de XP e Badges chamando `GET /api/v1/rankings/profile` para mostrar o feedback visual de ganho de nível ou novas conquistas.

---

## 4. Estrutura de Dados (Sugestão TypeScript)

```typescript
export interface ExerciseLogItem {
  exercise_id: string;
  sets: number;
  reps: number;
  weight_kg: number;
}

export interface WorkoutFinishPayload {
  date: string; // YYYY-MM-DD
  time_start: string; // HH:mm:ss
  time_end: string; // HH:mm:ss
  plan_workout_id?: string;
  observations?: string;
  exercises: ExerciseLogItem[];
}

export interface WorkoutLogResponse {
  id: string;
  duration_min: number;
  calories_burned: number;
  ai_feedback: string;
  muscles_trained: string[];
}
```

---

## 5. Exemplos de Integração

### Formulário de Finalização de Treino (React + Axios)
```typescript
const handleFinishWorkout = async (data: WorkoutFinishPayload) => {
  try {
    const response = await api.post('/v1/workouts/finish', data);
    
    // 1. Mostrar feedback da IA
    showModal({
      title: "Treino Concluído! 🔥",
      content: response.data.log.ai_feedback,
      calories: response.data.log.calories_burned
    });

    // 2. Atualizar estado global de gamificação para capturar o +30 XP
    await refreshGamificationProfile();

  } catch (error) {
    showToast("Erro ao salvar treino. Tente novamente.", "error");
  }
};
```

### Cálculo de Duração no Frontend
```typescript
const timeStart = "14:00:00";
const timeEnd = "15:30:00";

// O backend calcula a duração automaticamente baseado nos campos time_start e time_end,
// então o frontend não precisa enviar o campo duration_min.
```
