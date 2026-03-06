<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Badge;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // Consistency
            ['code' => 'consistency_7',      'name' => 'Consistência 7',     'description' => '7 dias seguidos ativo.',                                      'category' => 'consistency', 'tier' => 'bronze', 'xp_reward' => 100],
            ['code' => 'consistency_30',     'name' => 'Consistência 30',    'description' => '30 dias seguidos ativo.',                                     'category' => 'consistency', 'tier' => 'silver', 'xp_reward' => 300],
            ['code' => 'consistency_90',     'name' => 'Consistência 90',    'description' => '90 dias seguidos ativo.',                                     'category' => 'consistency', 'tier' => 'gold',   'xp_reward' => 800],

            // Training
            ['code' => 'workouts_10_month',  'name' => 'Treinador do Mês',   'description' => '10 treinos no mês.',                                          'category' => 'training',    'tier' => 'bronze', 'xp_reward' => 150],
            ['code' => 'workouts_50_total',  'name' => '50 Treinos',         'description' => '50 treinos totais.',                                           'category' => 'training',    'tier' => 'silver', 'xp_reward' => 400],
            ['code' => 'workouts_100_total', 'name' => '100 Treinos',        'description' => '100 treinos totais.',                                          'category' => 'training',    'tier' => 'gold',   'xp_reward' => 900],

            // Water
            ['code' => 'water_5_days',       'name' => 'Hidratado 5',        'description' => '5 dias com meta de água atingida.',                            'category' => 'water',       'tier' => 'bronze', 'xp_reward' => 120],
            ['code' => 'water_20_days',      'name' => 'Hidratado 20',       'description' => '20 dias com meta de água atingida.',                           'category' => 'water',       'tier' => 'silver', 'xp_reward' => 350],

            // Hardcore
            ['code' => 'hardcore_6_week',    'name' => 'Hardcore Semana',     'description' => '6 dias de treino na semana.',                                  'category' => 'hardcore',    'tier' => 'silver', 'xp_reward' => 500],
            ['code' => 'hardcore_3_months',  'name' => 'Hardcore 3 Meses',   'description' => '3 meses treinando 6x/semana (com 2 escudos mensais).',        'category' => 'hardcore',    'tier' => 'legend', 'xp_reward' => 1500],

            // Social
            ['code' => 'rank_week_1',        'name' => 'Campeão Semanal',    'description' => '1º lugar no ranking semanal.',                                 'category' => 'social',      'tier' => 'gold',   'xp_reward' => 600],
            ['code' => 'rank_month_1',       'name' => 'Campeão Mensal',     'description' => '1º lugar no ranking mensal.',                                  'category' => 'social',      'tier' => 'legend', 'xp_reward' => 1200],
            ['code' => 'rank_top3',          'name' => 'Top 3',              'description' => 'Top 3 em algum ranking.',                                      'category' => 'social',      'tier' => 'silver', 'xp_reward' => 300],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(
                ['code' => $badge['code']],
                array_merge($badge, [
                    'is_active'  => true,
                ])
            );
        }
    }
}
