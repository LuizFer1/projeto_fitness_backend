<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Mission;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        $missions = [
            [
                'code'           => 'train_3_days',
                'title'          => 'Treine 3 dias seguidos',
                'description'    => 'Complete treinos por 3 dias consecutivos.',
                'condition_type' => 'streak_workout_days',
                'target_value'   => 3,
                'period_type'    => 'daily',
                'xp_reward'      => 80,
            ],
            [
                'code'           => 'water_3l_4_days',
                'title'          => 'Beba 3 litros por 4 dias seguidos',
                'description'    => 'Atingir meta de água por 4 dias consecutivos.',
                'condition_type' => 'streak_water_days',
                'target_value'   => 4,
                'period_type'    => 'daily',
                'xp_reward'      => 80,
            ],
        ];

        foreach ($missions as $mission) {
            Mission::updateOrCreate(
                ['code' => $mission['code']],
                array_merge($mission, [
                    'is_active'  => true,
                ])
            );
        }
    }
}
