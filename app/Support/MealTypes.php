<?php

namespace App\Support;

/**
 * Single source of truth for the meal_type enum used across the API.
 * Mirrored on the frontend (project_fitness/src/types/...).
 */
final class MealTypes
{
    public const BREAKFAST = 'breakfast';

    public const SNACK = 'snack';

    public const LUNCH = 'lunch';

    public const DINNER = 'dinner';

    public const PRE_WORKOUT = 'pre_workout';

    public const POST_WORKOUT = 'post_workout';

    public const ALL = [
        self::BREAKFAST,
        self::SNACK,
        self::LUNCH,
        self::DINNER,
        self::PRE_WORKOUT,
        self::POST_WORKOUT,
    ];
}
