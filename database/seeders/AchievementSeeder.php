<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'key' => 'first_goal_created',
                'title' => 'Primeiro Objetivo',
                'description' => 'Criou seu primeiro objetivo',
                'icon' => '🎯',
                'category' => 'goals',
                'xp_reward' => 10,
            ],
            [
                'key' => 'first_checkin',
                'title' => 'Primeiro Check-in',
                'description' => 'Realizou seu primeiro check-in de objetivo',
                'icon' => '✅',
                'category' => 'goals',
                'xp_reward' => 10,
            ],
            [
                'key' => 'goal_completed_1',
                'title' => 'Objetivo Concluído',
                'description' => 'Completou seu primeiro objetivo',
                'icon' => '🏅',
                'category' => 'goals',
                'xp_reward' => 50,
            ],
            [
                'key' => 'goal_completed_5',
                'title' => 'Conquistador de Objetivos',
                'description' => 'Completou 5 objetivos',
                'icon' => '🏆',
                'category' => 'goals',
                'xp_reward' => 150,
            ],
            [
                'key' => 'first_workout_done',
                'title' => 'Primeiro Treino',
                'description' => 'Completou seu primeiro dia de treino',
                'icon' => '💪',
                'category' => 'milestone',
                'xp_reward' => 25,
            ],
            [
                'key' => 'streak_3_days',
                'title' => 'Sequência de 3 Dias',
                'description' => 'Manteve uma sequência de 3 dias consecutivos',
                'icon' => '🔥',
                'category' => 'streak',
                'xp_reward' => 30,
            ],
            [
                'key' => 'streak_7_days',
                'title' => 'Sequência de 7 Dias',
                'description' => 'Manteve uma sequência de 7 dias consecutivos',
                'icon' => '🔥',
                'category' => 'streak',
                'xp_reward' => 75,
            ],
            [
                'key' => 'streak_30_days',
                'title' => 'Sequência de 30 Dias',
                'description' => 'Manteve uma sequência de 30 dias consecutivos',
                'icon' => '🔥',
                'category' => 'streak',
                'xp_reward' => 300,
            ],
            [
                'key' => 'first_friend',
                'title' => 'Primeiro Amigo',
                'description' => 'Fez sua primeira amizade',
                'icon' => '🤝',
                'category' => 'social',
                'xp_reward' => 20,
            ],
            [
                'key' => 'social_butterfly',
                'title' => 'Borboleta Social',
                'description' => 'Fez 10 amizades',
                'icon' => '🦋',
                'category' => 'social',
                'xp_reward' => 100,
            ],
            [
                'key' => 'first_plan_generated',
                'title' => 'Primeiro Plano',
                'description' => 'Gerou seu primeiro plano de treino',
                'icon' => '📋',
                'category' => 'milestone',
                'xp_reward' => 30,
            ],
            [
                'key' => 'plan_refined',
                'title' => 'Plano Refinado',
                'description' => 'Refinou um plano de treino com IA',
                'icon' => '🔧',
                'category' => 'milestone',
                'xp_reward' => 50,
            ],
            [
                'key' => 'leaderboard_top_10',
                'title' => 'Top 10',
                'description' => 'Alcançou o top 10 no leaderboard',
                'icon' => '🥇',
                'category' => 'leaderboard',
                'xp_reward' => 100,
            ],
            [
                'key' => 'leaderboard_top_1',
                'title' => 'Número 1',
                'description' => 'Alcançou o primeiro lugar no leaderboard',
                'icon' => '👑',
                'category' => 'leaderboard',
                'xp_reward' => 500,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(
                ['key' => $achievement['key']],
                $achievement,
            );
        }
    }
}
