<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkoutLog;
use App\Models\WorkoutExerciseLog;
use App\Services\GeminiService;
use App\Services\GamificationService;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class WorkoutLogController extends Controller
{
    private $geminiService;
    private $gamificationService;

    public function __construct(GeminiService $geminiService, GamificationService $gamificationService)
    {
        $this->geminiService = $geminiService;
        $this->gamificationService = $gamificationService;
    }

    #[OA\Post(
        path: '/api/v1/workouts/finish',
        summary: 'Salva e analisa um treino finalizado',
        description: 'Salva o log do treino, os exercícios realizados, e envia os dados para a IA (Gemini) calcular calorias gastas, músculos treinados e gerar um feedback motivacional.',
        tags: ['Workouts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-03-15'),
                    new OA\Property(property: 'time_start', type: 'string', format: 'time', example: '14:00:00'),
                    new OA\Property(property: 'time_end', type: 'string', format: 'time', example: '15:30:00'),
                    new OA\Property(property: 'plan_workout_id', type: 'string', format: 'uuid', example: 'uuid_here'),
                    new OA\Property(property: 'observations', type: 'string', example: 'Treino muito focado, mas ombro doeu um pouco.'),
                    new OA\Property(
                        property: 'exercises',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'exercise_id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'sets', type: 'integer', example: 3),
                                new OA\Property(property: 'reps', type: 'integer', example: 12),
                                new OA\Property(property: 'weight_kg', type: 'number', example: 20.5),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Treino salvo e analisado com sucesso'),
            new OA\Response(response: 422, description: 'Erro de validação ou erro na integração com a IA'),
        ]
    )]
    public function finish(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'time_start' => 'required|date_format:H:i:s',
            'time_end' => 'required|date_format:H:i:s',
            'plan_workout_id' => 'nullable|uuid|exists:plan_workouts,id',
            'observations' => 'nullable|string',
            'exercises' => 'required|array|min:1',
            'exercises.*.exercise_id' => 'required|uuid|exists:exercises,id',
            'exercises.*.sets' => 'required|integer',
            'exercises.*.reps' => 'required|integer',
            'exercises.*.weight_kg' => 'required|numeric',
        ]);

        $user = $request->user() ?? \App\Models\User::first(); // Fallback if auth is ignored for now

        $timeStart = Carbon::createFromFormat('H:i:s', $validated['time_start']);
        $timeEnd = Carbon::createFromFormat('H:i:s', $validated['time_end']);
        $durationMin = $timeEnd->diffInMinutes($timeStart);

        // Prepare data for Gemini
        $exercisesJson = json_encode($validated['exercises']);
        $prompt = "Você é um Personal Trainer especialista e analista de dados esportivos. O usuário acabou de finalizar um treino.\n"
            . "Analise os seguintes dados fornecidos:\n"
            . "- Horário de início: {$validated['time_start']}\n"
            . "- Horário de término: {$validated['time_end']}\n"
            . "- Exercícios realizados (lista em json): {$exercisesJson}\n"
            . "- Comentários do usuário sobre a execução/dificuldade: {$validated['observations']}\n\n"
            . "Sua tarefa é calcular e estimar as métricas deste treino.\n"
            . "Você DEVE retornar a resposta EXCLUSIVAMENTE em um formato JSON válido, com nenhuma marcação markdown. Estrutura exigida:\n"
            . "{\"informacoes_treino\": \"paragrafo motivacional/analise\", \"musculos_treinados\": [\"Peito\"], \"calorias_gastas_estimadas\": 450, \"tempo_medio_por_exercicio_minutos\": 4.5}";

        try {
            $aiResponse = $this->geminiService->generateTextResponse(null, $prompt);
            
            $workoutLog = WorkoutLog::create([
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
                    'workout_log_id' => $workoutLog->id,
                    'exercise_id' => $ex['exercise_id'],
                    'sets' => $ex['sets'],
                    'reps' => $ex['reps'],
                    'weight_kg' => $ex['weight_kg'],
                ]);
            }

            // RF-01: Grant workout XP + RF-08: Check workout badges
            $this->gamificationService->grantWorkoutCompletedXp($user, $workoutLog->id);
            $this->gamificationService->checkWorkoutBadges($user);

            return response()->json([
                'message' => 'Treino finalizado com sucesso!',
                'log' => $workoutLog->load('workoutLogExercises') ?? $workoutLog
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Falha ao processar treino com IA: ' . $e->getMessage()], 422);
        }
    }
}
