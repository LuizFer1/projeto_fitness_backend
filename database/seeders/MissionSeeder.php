<?php

namespace Database\Seeders;

use App\Models\Quest;
use Illuminate\Database\Seeder;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        $missions = [
            [
                'slug'            => 'train_3_days',
                'name'            => 'Treine 3 dias seguidos',
                'description'     => 'Complete treinos por 3 dias consecutivos.',
                'icon'            => '🔥',
                'type'            => 'basic',
                'periodicity'     => 'weekly',
                'condition_type'  => 'streak_days',
                'condition_value' => 3,
                'xp_reward'       => 80,
                'is_active'       => true,
            ],
            [
                'slug'            => 'water_4_days',
                'name'            => 'Hidratação 4 dias',
                'description'     => 'Atingir meta de água por 4 dias consecutivos.',
                'icon'            => '💧',
                'type'            => 'basic',
                'periodicity'     => 'weekly',
                'condition_type'  => 'water_days',
                'condition_value' => 4,
                'xp_reward'       => 80,
                'is_active'       => true,
            ],
        ];

        foreach ($missions as $mission) {
            Quest::updateOrCreate(['slug' => $mission['slug']], $mission);
        }
    }
}
