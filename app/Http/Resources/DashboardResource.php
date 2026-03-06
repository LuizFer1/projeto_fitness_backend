<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nutrition = $this['nutrition'] ?? null;
        $exercise  = $this['exercise'] ?? null;
        $weightLog = $this['weight'] ?? null;
        
        $consumedCarbs_g   = 0;
        $consumedProtein_g = 0;
        $consumedFat_g     = 0;

        $carbsGoal   = 250;
        $proteinGoal = 150;
        $fatGoal     = 70;

        if ($nutrition && $nutrition->relationLoaded('macros')) {
            $carbsMacro   = $nutrition->macros->firstWhere('label', 'Carbo');
            $proteinMacro = $nutrition->macros->firstWhere('label', 'Proteína');
            $fatMacro     = $nutrition->macros->firstWhere('label', 'Gordura');

            $consumedCarbs_g   = $carbsMacro ? $carbsMacro->current_value : 0;
            $consumedProtein_g = $proteinMacro ? $proteinMacro->current_value : 0;
            $consumedFat_g     = $fatMacro ? $fatMacro->current_value : 0;

            $carbsGoal   = $carbsMacro && $carbsMacro->goal_value > 0 ? $carbsMacro->goal_value : $carbsGoal;
            $proteinGoal = $proteinMacro && $proteinMacro->goal_value > 0 ? $proteinMacro->goal_value : $proteinGoal;
            $fatGoal     = $fatMacro && $fatMacro->goal_value > 0 ? $fatMacro->goal_value : $fatGoal;
        }

        // 2. Convertendo o que foi comido em Calorias
        $kcalFromCarbs   = $consumedCarbs_g * 4;
        $kcalFromProtein = $consumedProtein_g * 4;
        $kcalFromFat     = $consumedFat_g * 9;

        // 3. Descobrindo o TOTAL real de calorias consumidas com base nos macros
        $totalKcalConsumed = $kcalFromCarbs + $kcalFromProtein + $kcalFromFat;

        // 4. Calculando a fatia/porcentagem de cada um na dieta de hoje
        $carbsPercentage   = $totalKcalConsumed > 0 ? ($kcalFromCarbs / $totalKcalConsumed) * 100 : 0;
        $proteinPercentage = $totalKcalConsumed > 0 ? ($kcalFromProtein / $totalKcalConsumed) * 100 : 0;
        $fatPercentage     = $totalKcalConsumed > 0 ? ($kcalFromFat / $totalKcalConsumed) * 100 : 0;

        // 5. Hidratação (Convertendo mililitros em array de copos de 250ml)
        $waterGoal_ml = $nutrition && $nutrition->water_goal > 0 ? $nutrition->water_goal : 2000;
        $waterCurrent_ml = $nutrition ? $nutrition->water_current : 0;
        $glassSize_ml = 250;
        
        $totalGlasses = ceil($waterGoal_ml / $glassSize_ml);
        $drunkGlasses = floor($waterCurrent_ml / $glassSize_ml);
        
        $glassesArray = [];
        for ($i = 0; $i < max($totalGlasses, $drunkGlasses); $i++) {
            $glassesArray[] = $i < $drunkGlasses;
        }

        $meals             = $this['meals'] ?? collect();
        $recentNutrition   = $this['recentNutrition'] ?? collect();
        $recentWorkouts    = $this['recentWorkouts'] ?? collect();
        $suggestedWorkouts = $this['suggestedWorkouts'] ?? collect();
        $goals             = $this['goals'] ?? collect();

        // 6. Atividade Semanal (Últimos 7 dias)
        $activityChart = [];
        $trainingDays = [];
        $activeDays = [];
        $weekDays = [];
        
        $todayStr = now()->toDateString();
        $startOfWeek = now()->startOfWeek();

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->toDateString();
            
            $dayNutrition = $recentNutrition->firstWhere('day', $dateStr);
            $dayWorkout   = $recentWorkouts->firstWhere('workout_date', $dateStr);

            // Cálculo do "fg" (foreground): Se a meta de kcal foi batida (50%), ou bebeu água (+25%) ou treinou (+25%)
            $fg = 0;
            if ($dayNutrition) {
                if ($dayNutrition->calories_goal > 0 && $dayNutrition->calories_total >= $dayNutrition->calories_goal * 0.8) {
                    $fg += 50;
                } elseif ($dayNutrition->calories_total > 0) {
                    $fg += 25;
                }

                if ($dayNutrition->water_goal > 0 && $dayNutrition->water_current >= $dayNutrition->water_goal) {
                    $fg += 25;
                }
            }

            if ($dayWorkout) {
                $fg += 25;
                $trainingDays[] = substr($date->locale('pt_BR')->dayName, 0, 1); // ex 'S', 'T'
            }

            if ($fg > 100) $fg = 100;
            
            $activityChart[] = ['bg' => 100, 'fg' => $fg];
            $weekDays[] = ucfirst(substr($date->locale('pt_BR')->dayName, 0, 3)); // ex 'Seg'
            
            // Tratando dia ativo (qualquer pontuação de atividade marca como dia ativo)
            if ($fg >= 25) {
                $activeDays[] = 6 - $i; // Mapeia o índice do array de 0 a 6
            }
        }

        // Mapear Meals
        $formattedMeals = $meals->map(function ($meal) {
            return [
                'id'       => (string) $meal->id,
                'name'     => $meal->name,
                'detail'   => $meal->detail,
                'calories' => $meal->kcal,
                'image'    => $meal->img_url
            ];
        })->values()->all();

        // Mapear Treinos Sugeridos
        $formattedSuggested = $suggestedWorkouts->map(function ($wk) {
            return [
                'id'       => (string) $wk->id,
                'name'     => $wk->name,
                'duration' => $wk->duration_min,
                'level'    => $wk->level,
                'category' => $wk->category,
                'image'    => $wk->img_url
            ];
        })->values()->all();

        return [
            'dailyCalories' => [
                'goal'     => $nutrition && $nutrition->calories_goal ? $nutrition->calories_goal : 2500,
                'consumed' => $totalKcalConsumed, // Ou $nutrition->calories_total
            ],
            'protein' => [
                'goal'    => $proteinGoal,
                'current' => $consumedProtein_g,
            ],
            'weeklyWorkouts' => [
                'goal' => $exercise ? $exercise->goal_sessions : 5,
                'done' => $exercise ? $exercise->done_sessions : 0,
            ],
            'hydration' => [
                'current' => $waterCurrent_ml / 1000, // Enviando em Litros pro Front
                'glasses' => $glassesArray
            ],
            'activityChart' => $activityChart,
            'trainingDays'  => $trainingDays,
            'activeDays'    => $activeDays,
            'weekDays'      => $weekDays,
            'currentWeight' => [
                'value'        => $weightLog ? (float) $weightLog->weight_kg : 0,
                'weeklyChange' => 0, // Poderia ser (PesoAtual - PesoSemanaPassada)
            ],
            'meals'             => $formattedMeals,
            'suggestedWorkouts' => $formattedSuggested,
            'userGoals'         => $goals,
            'macros' => [
                [
                    'name' => 'Carbo', 
                    'current' => $consumedCarbs_g, 
                    'goal' => $carbsGoal, 
                    'percentage' => round($carbsPercentage, 1), 
                    'color' => '#3b82f6', 
                    'unit' => 'g'
                ],
                [
                    'name' => 'Proteína', 
                    'current' => $consumedProtein_g, 
                    'goal' => $proteinGoal, 
                    'percentage' => round($proteinPercentage, 1), 
                    'color' => '#ef4444', 
                    'unit' => 'g'
                ],
                [
                    'name' => 'Gordura', 
                    'current' => $consumedFat_g, 
                    'goal' => $fatGoal, 
                    'percentage' => round($fatPercentage, 1), 
                    'color' => '#eab308', 
                    'unit' => 'g'
                ],
            ],
        ];
    }
}
