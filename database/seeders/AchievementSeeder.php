<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            // ── Bronze ────────────────────────────────────────────────────
            [
                'slug'            => 'clean_week',
                'name'            => 'Primeira Semana Limpa',
                'description'     => '7 dias consecutivos sem furar a dieta',
                'icon'            => '🥗',
                'category'        => 'consistency',
                'tier'            => 'bronze',
                'xp_reward'       => 300,
                'condition_type'  => 'streak_days',
                'condition_value' => 7,
            ],
            [
                'slug'            => 'protein_machine',
                'name'            => 'Máquina de Proteína',
                'description'     => 'Meta de proteína atingida 10 vezes seguidas',
                'icon'            => '🥩',
                'category'        => 'nutrition',
                'tier'            => 'bronze',
                'xp_reward'       => 250,
                'condition_type'  => 'streak_days',
                'condition_value' => 10,
            ],

            // ── Silver ────────────────────────────────────────────────────
            [
                'slug'            => 'armored_month',
                'name'            => 'Mês Blindado',
                'description'     => '30 dias consecutivos sem furar a dieta',
                'icon'            => '🛡️',
                'category'        => 'consistency',
                'tier'            => 'silver',
                'xp_reward'       => 1000,
                'condition_type'  => 'streak_days',
                'condition_value' => 30,
            ],
            [
                'slug'            => 'pr_broken',
                'name'            => 'PR Quebrado',
                'description'     => 'Novo recorde pessoal de 1RM registrado',
                'icon'            => '🏋️',
                'category'        => 'workout',
                'tier'            => 'silver',
                'xp_reward'       => 500,
                'condition_type'  => 'streak_days',
                'condition_value' => 1,
            ],

            // ── Gold ──────────────────────────────────────────────────────
            [
                'slug'            => 'gold_trident',
                'name'            => 'Tridente de Ouro',
                'description'     => 'Treino + Dieta + Água por 60 dias seguidos',
                'icon'            => '🔱',
                'category'        => 'hardcore',
                'tier'            => 'gold',
                'xp_reward'       => 5000,
                'condition_type'  => 'active_days',
                'condition_value' => 60,
                'is_hidden'       => true,
            ],
            [
                'slug'            => 'top_1_percent_global',
                'name'            => 'Top 1% Global',
                'description'     => 'Entrou no top 1% do ranking global',
                'icon'            => '🌍',
                'category'        => 'special',
                'tier'            => 'gold',
                'xp_reward'       => 3000,
                'condition_type'  => 'active_days',
                'condition_value' => 1,
                'is_hidden'       => true,
            ],

            // ── Platinum ─────────────────────────────────────────────────
            [
                'slug'            => 'evofit_legendary',
                'name'            => 'EvoFit Lendário',
                'description'     => '365 dias consecutivos de compliance total',
                'icon'            => '✨',
                'category'        => 'hardcore',
                'tier'            => 'platinum',
                'xp_reward'       => 20000,
                'condition_type'  => 'streak_days',
                'condition_value' => 365,
                'is_hidden'       => true,
            ],
            [
                'slug'            => 'the_immortal',
                'name'            => 'O Imortal',
                'description'     => 'Nível 8 (GOAT) atingido',
                'icon'            => '💎',
                'category'        => 'special',
                'tier'            => 'platinum',
                'xp_reward'       => 50000,
                'condition_type'  => 'active_days',
                'condition_value' => 1,
                'is_hidden'       => true,
            ],

            // ── Legacy (keep active, backfill tier=bronze) ────────────────
            ['slug' => 'streak_7',        'name' => '7 Days Streak',       'category' => 'consistency', 'tier' => 'bronze', 'xp_reward' => 150, 'condition_type' => 'streak_days',    'condition_value' => 7],
            ['slug' => 'streak_30',       'name' => '30 Days Streak',      'category' => 'consistency', 'tier' => 'silver', 'xp_reward' => 400, 'condition_type' => 'streak_days',    'condition_value' => 30],
            ['slug' => 'streak_90',       'name' => '90 Days Streak',      'category' => 'consistency', 'tier' => 'gold',   'xp_reward' => 1200,'condition_type' => 'streak_days',    'condition_value' => 90],
            ['slug' => 'treinos_10',      'name' => '10 Workouts',         'category' => 'workout',     'tier' => 'bronze', 'xp_reward' => 100, 'condition_type' => 'total_workouts',  'condition_value' => 10],
            ['slug' => 'treinos_50',      'name' => '50 Workouts',         'category' => 'workout',     'tier' => 'silver', 'xp_reward' => 300, 'condition_type' => 'total_workouts',  'condition_value' => 50],
            ['slug' => 'treinos_100',     'name' => '100 Workouts',        'category' => 'workout',     'tier' => 'gold',   'xp_reward' => 800, 'condition_type' => 'total_workouts',  'condition_value' => 100],
            ['slug' => 'agua_5dias',      'name' => 'Hydrated',            'category' => 'water',       'tier' => 'bronze', 'xp_reward' => 50,  'condition_type' => 'water_days',      'condition_value' => 5],
            ['slug' => 'agua_20dias',     'name' => 'Always Hydrated',     'category' => 'water',       'tier' => 'silver', 'xp_reward' => 150, 'condition_type' => 'water_days',      'condition_value' => 20],
            ['slug' => 'hardcore_semana', 'name' => 'Hardcore Week',       'category' => 'hardcore',    'tier' => 'silver', 'xp_reward' => 250, 'condition_type' => 'hardcore_weeks',  'condition_value' => 1],
            ['slug' => 'ativo_3meses',    'name' => '3 Active Months',     'category' => 'hardcore',    'tier' => 'gold',   'xp_reward' => 700, 'condition_type' => 'active_days',     'condition_value' => 90],
        ];

        foreach ($achievements as $data) {
            $defaults = [
                'id'              => (string) Str::uuid(),
                'description'     => null,
                'icon'            => null,
                'is_hidden'       => false,
                'is_active'       => true,
            ];

            DB::table('achievements')->updateOrInsert(
                ['slug' => $data['slug']],
                array_merge($defaults, $data)
            );
        }
    }
}
