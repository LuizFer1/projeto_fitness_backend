<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daily XP cap (global ceiling per user per day)
    |--------------------------------------------------------------------------
    */
    'daily_cap' => 300,

    /*
    |--------------------------------------------------------------------------
    | XP events — SRS v1.0 spec (section 6.1)
    |
    | base          — XP before multiplier
    | max_mult      — maximum streak multiplier (1.0 = no bonus)
    | full_at_days  — consecutive streak days required to reach max_mult
    | cap           — max XP this event can grant in a single day
    | unique        — true if it can only fire once ever per user (no daily reset)
    |--------------------------------------------------------------------------
    */
    'events' => [
        'clean_diet_day'   => ['base' => 60,  'max_mult' => 2.0, 'full_at_days' => 15, 'cap' => 120],
        'protein_goal_met' => ['base' => 50,  'max_mult' => 1.5, 'full_at_days' => 7,  'cap' => 75],
        'water_goal_met'   => ['base' => 30,  'max_mult' => 1.3, 'full_at_days' => 5,  'cap' => 39],
        'workout_strength' => ['base' => 100, 'max_mult' => 1.5, 'full_at_days' => 7,  'cap' => 150],
        'workout_cardio'   => ['base' => 80,  'max_mult' => 1.4, 'full_at_days' => 7,  'cap' => 112],
        'progress_photo'   => ['base' => 20,  'max_mult' => 1.0, 'full_at_days' => 1,  'cap' => 20],
        'asset_shared'     => ['base' => 15,  'max_mult' => 1.0, 'full_at_days' => 1,  'cap' => 30],
        'pr_set'           => ['base' => 200, 'max_mult' => 1.0, 'full_at_days' => 1,  'cap' => 200, 'unique' => true],

        // Legacy events (kept for back-compat with existing XP grants)
        'daily_login'      => ['base' => 10,  'max_mult' => 1.5, 'full_at_days' => 35, 'cap' => 15],
        'meal_logged'      => ['base' => 20,  'max_mult' => 1.5, 'full_at_days' => 35, 'cap' => 30],
        'weight_logged'    => ['base' => 15,  'max_mult' => 1.0, 'full_at_days' => 1,  'cap' => 15],
    ],

    /*
    |--------------------------------------------------------------------------
    | Penalties
    |--------------------------------------------------------------------------
    */
    'penalties' => [
        'calories_missed' => 15,
        'weekly_workouts' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Level definitions — SRS v1.0 section 6.2 (8 levels)
    |
    | Used to rebuild level_definitions table via migration and to keep
    | LEVEL_THRESHOLDS in GamificationService in sync.
    |--------------------------------------------------------------------------
    */
    'levels' => [
        1 => ['title' => 'Iniciante',    'min_xp' => 0,      'max_xp' => 499,    'hex_color' => '#94a3b8', 'icon' => '🌱', 'benefit' => 'Acesso básico ao app'],
        2 => ['title' => 'Atleta',       'min_xp' => 500,    'max_xp' => 1499,   'hex_color' => '#22c55e', 'icon' => '⚡', 'benefit' => 'Rankings regionais habilitados'],
        3 => ['title' => 'Guerreiro',    'min_xp' => 1500,   'max_xp' => 3999,   'hex_color' => '#3b82f6', 'icon' => '⚔️', 'benefit' => 'Troféus Bronze habilitados'],
        4 => ['title' => 'Especialista', 'min_xp' => 4000,   'max_xp' => 9999,   'hex_color' => '#8b5cf6', 'icon' => '🎯', 'benefit' => 'Troféus Prata + ranking global'],
        5 => ['title' => 'Elite',        'min_xp' => 10000,  'max_xp' => 24999,  'hex_color' => '#f59e0b', 'icon' => '🔥', 'benefit' => 'Troféus Ouro + badge de perfil'],
        6 => ['title' => 'Lenda',        'min_xp' => 25000,  'max_xp' => 59999,  'hex_color' => '#f97316', 'icon' => '🏅', 'benefit' => 'Troféus Platina + perfil verificado'],
        7 => ['title' => 'Mestre',       'min_xp' => 60000,  'max_xp' => 149999, 'hex_color' => '#ef4444', 'icon' => '🏋️', 'benefit' => 'Acesso a desafios exclusivos'],
        8 => ['title' => 'GOAT',         'min_xp' => 150000, 'max_xp' => null,   'hex_color' => '#f0a500', 'icon' => '👑', 'benefit' => 'Hall of Fame global + cosmético exclusivo'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Achievement tiers — unlocked at level
    |--------------------------------------------------------------------------
    */
    'tier_unlock_level' => [
        'bronze'   => 3,
        'silver'   => 4,
        'gold'     => 5,
        'platinum' => 6,
    ],
];
