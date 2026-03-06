<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlimentationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nutrition   = $this['nutrition'] ?? null;
        $suggestions = $this['suggestions'] ?? collect();
        $goals       = $this['goals'] ?? collect();

        // ── Macro icon mapping ─────────────────────────────────────────
        $iconMap = [
            'Proteína'    => 'beef',
            'Carboidrato' => 'wheat',
            'Gordura'     => 'droplets',
        ];

        // ── Macros ────────────────────────────────────────────────────
        $macros = [];
        if ($nutrition && $nutrition->relationLoaded('macros')) {
            $macros = $nutrition->macros->map(function ($macro) use ($iconMap) {
                return [
                    'label'    => $macro->label,
                    'current'  => (float) $macro->current_value,
                    'goal'     => (float) $macro->goal_value,
                    'unit'     => $macro->unit,
                    'color'    => $macro->color_hex,
                    'iconName' => $iconMap[$macro->label] ?? 'circle',
                ];
            })->values()->all();
        }

        // ── Water (ml → glasses of 250ml) ─────────────────────────────
        $glassSize    = 250;
        $waterGoal_ml = $nutrition && $nutrition->water_goal > 0 ? $nutrition->water_goal : 2000;
        $waterCur_ml  = $nutrition ? $nutrition->water_current : 0;

        $waterGoalGlasses = (int) ceil($waterGoal_ml / $glassSize);
        $waterCurGlasses  = (int) floor($waterCur_ml / $glassSize);

        // ── Meal Groups ───────────────────────────────────────────────
        $mealGroups = [];
        if ($nutrition && $nutrition->relationLoaded('mealGroups')) {
            $mealGroups = $nutrition->mealGroups->map(function ($group) {
                return [
                    'label' => $group->label,
                    'emoji' => $group->emoji,
                    'meals' => $group->meals->map(function ($meal) {
                        return [
                            'id'      => (string) $meal->id,
                            'name'    => $meal->name,
                            'detail'  => $meal->detail,
                            'kcal'    => $meal->kcal,
                            'protein' => (float) $meal->protein_g,
                            'carbs'   => (float) $meal->carbs_g,
                            'fat'     => (float) $meal->fat_g,
                            'time'    => $meal->time_hhmm,
                            'img'     => $meal->img_url,
                        ];
                    })->values()->all(),
                ];
            })->values()->all();
        }

        // ── Suggestions ───────────────────────────────────────────────
        $formattedSuggestions = $suggestions->map(function ($s) {
            return [
                'id'   => (string) $s->id,
                'name' => $s->name,
                'kcal' => $s->kcal,
                'img'  => $s->img_url,
                'tags' => $s->tags->pluck('tag')->values()->all(),
            ];
        })->values()->all();

        return [
            'dailyGoal'    => $nutrition && $nutrition->calories_goal ? $nutrition->calories_goal : 2500,
            'consumed'     => $nutrition ? $nutrition->calories_total : 0,
            'waterGoal'    => $waterGoalGlasses,
            'waterCurrent' => $waterCurGlasses,
            'macros'       => $macros,
            'mealGroups'   => $mealGroups,
            'suggestions'  => $formattedSuggestions,
            'userGoals'    => $goals,
        ];
    }
}
