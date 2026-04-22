<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class AlimentationResource extends JsonResource
{
    private const GLASS_SIZE_ML = 250;
    private const DEFAULT_WATER_GOAL_ML = 2000;
    private const DEFAULT_CALORIES_GOAL = 2500;
    private const MACRO_ICON_MAP = [
        'Proteína'    => 'beef',
        'Carboidrato' => 'wheat',
        'Gordura'     => 'droplets',
    ];

    public function toArray(Request $request): array
    {
        $nutrition = $this['nutrition'] ?? null;

        return [
            'dailyGoal'    => $nutrition && $nutrition->calories_goal ? $nutrition->calories_goal : self::DEFAULT_CALORIES_GOAL,
            'consumed'     => $nutrition ? $nutrition->calories_total : 0,
            'waterGoal'    => $this->waterGoalInGlasses($nutrition),
            'waterCurrent' => $this->waterCurrentInGlasses($nutrition),
            'macros'       => $this->formatMacros($nutrition),
            'mealGroups'   => $this->formatMealGroups($nutrition),
            'suggestions'  => $this->formatSuggestions($this['suggestions'] ?? collect()),
            'userGoals'    => $this['goals'] ?? collect(),
        ];
    }

    private function waterGoalInGlasses($nutrition): int
    {
        $goalMl = $nutrition && $nutrition->water_goal > 0 ? $nutrition->water_goal : self::DEFAULT_WATER_GOAL_ML;

        return (int) ceil($goalMl / self::GLASS_SIZE_ML);
    }

    private function waterCurrentInGlasses($nutrition): int
    {
        $currentMl = $nutrition ? $nutrition->water_current : 0;

        return (int) floor($currentMl / self::GLASS_SIZE_ML);
    }

    private function formatMacros($nutrition): array
    {
        if (!$nutrition || !$nutrition->relationLoaded('macros')) {
            return [];
        }

        return $nutrition->macros->map(fn ($macro) => [
            'label'    => $macro->label,
            'current'  => (float) $macro->current_value,
            'goal'     => (float) $macro->goal_value,
            'unit'     => $macro->unit,
            'color'    => $macro->color_hex,
            'iconName' => self::MACRO_ICON_MAP[$macro->label] ?? 'circle',
        ])->values()->all();
    }

    private function formatMealGroups($nutrition): array
    {
        if (!$nutrition || !$nutrition->relationLoaded('mealGroups')) {
            return [];
        }

        return $nutrition->mealGroups->map(fn ($group) => [
            'label' => $group->label,
            'emoji' => $group->emoji,
            'meals' => $group->meals->map(fn ($meal) => $this->formatMeal($meal))->values()->all(),
        ])->values()->all();
    }

    private function formatMeal($meal): array
    {
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
    }

    private function formatSuggestions(Collection $suggestions): array
    {
        return $suggestions->map(fn ($s) => [
            'id'   => (string) $s->id,
            'name' => $s->name,
            'kcal' => $s->kcal,
            'img'  => $s->img_url,
            'tags' => $s->tags->pluck('tag')->values()->all(),
        ])->values()->all();
    }
}
