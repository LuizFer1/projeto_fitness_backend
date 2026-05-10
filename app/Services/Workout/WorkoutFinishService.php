<?php

namespace App\Services\Workout;

use App\Models\User;
use App\Models\WorkoutExerciseLog;
use App\Models\WorkoutLog;
use App\Services\GamificationService;
use App\Services\GroqService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persists a completed strength workout, runs the AI analysis and triggers all
 * gamification side-effects (XP, badges, PR detection).
 *
 * Centralises:
 *   • prompt building for the workout-analysis LLM call
 *   • duration computation
 *   • child rows (workout_exercise_logs)
 *   • gamification XP grants and PR checks
 *
 * Wraps everything in a single DB transaction so the AI call's failure
 * (after the LLM returns) cannot leave partial workout data behind.
 */
class WorkoutFinishService
{
    public function __construct(
        private GroqService $groq,
        private GamificationService $gamification,
        private ProgressiveOverloadService $overload,
    ) {}

    public function finish(User $user, array $validated): WorkoutLog
    {
        $durationMin = $this->computeDurationMin($validated['time_start'], $validated['time_end']);
        $aiResponse = $this->groq->generateTextResponse(null, $this->buildAnalysisPrompt($validated));

        return DB::transaction(function () use ($user, $validated, $aiResponse, $durationMin) {
            $log = WorkoutLog::create([
                'user_id' => $user->id,
                'plan_workout_id' => $validated['plan_workout_id'] ?? null,
                'date' => $validated['date'],
                'duration_min' => $durationMin,
                'calories_burned' => $aiResponse['calorias_gastas_estimadas'] ?? null,
                'observations' => $validated['observations'] ?? null,
                'ai_feedback' => $aiResponse['informacoes_treino'] ?? null,
                'muscles_trained' => $aiResponse['musculos_treinados'] ?? [],
            ]);

            foreach ($validated['exercises'] as $ex) {
                WorkoutExerciseLog::create([
                    'workout_log_id' => $log->id,
                    'exercise_id' => $ex['exercise_id'],
                    'sets' => $ex['sets'],
                    'reps' => $ex['reps'],
                    'weight_kg' => $ex['weight_kg'],
                ]);

                $this->overload->recordPotentialPR(
                    $user,
                    $ex['exercise_id'],
                    (float) $ex['weight_kg'],
                    (int) $ex['reps'],
                    $log->id,
                );
            }

            $this->gamification->grantWorkoutCompletedXp($user, $log->id);
            $this->gamification->checkWorkoutBadges($user);

            return $log;
        });
    }

    private function computeDurationMin(string $start, string $end): int
    {
        return (int) Carbon::createFromFormat('H:i:s', $end)
            ->diffInMinutes(Carbon::createFromFormat('H:i:s', $start));
    }

    private function buildAnalysisPrompt(array $v): string
    {
        $exercisesJson = json_encode($v['exercises']);
        $observations = $v['observations'] ?? '';

        return "Você é um Personal Trainer especialista e analista de dados esportivos. O usuário acabou de finalizar um treino.\n"
            ."Analise os seguintes dados fornecidos:\n"
            ."- Horário de início: {$v['time_start']}\n"
            ."- Horário de término: {$v['time_end']}\n"
            ."- Exercícios realizados (lista em json): {$exercisesJson}\n"
            ."- Comentários do usuário sobre a execução/dificuldade: {$observations}\n\n"
            ."Sua tarefa é calcular e estimar as métricas deste treino.\n"
            ."Você DEVE retornar a resposta EXCLUSIVAMENTE em um formato JSON válido, com nenhuma marcação markdown. Estrutura exigida:\n"
            .'{"informacoes_treino": "paragrafo motivacional/analise", "musculos_treinados": ["Peito"], "calorias_gastas_estimadas": 450, "tempo_medio_por_exercicio_minutos": 4.5}';
    }
}
