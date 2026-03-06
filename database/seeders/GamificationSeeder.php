<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GamificationSeeder extends Seeder
{
    public function run(): void
    {
        // XP Rules
        $rules = [
            ['action' => 'checkin',          'xp_value' => 10,  'daily_cap' => 1],
            ['action' => 'workout',          'xp_value' => 50,  'daily_cap' => 3],
            ['action' => 'water_goal',       'xp_value' => 20,  'daily_cap' => 1],
            ['action' => 'sleep_goal',       'xp_value' => 20,  'daily_cap' => 1],
            ['action' => 'weight',           'xp_value' => 15,  'daily_cap' => 1],
            ['action' => 'mission_complete', 'xp_value' => 80,  'daily_cap' => null],
            ['action' => 'badge_unlock',     'xp_value' => 100, 'daily_cap' => null],
            ['action' => 'bonus_active',     'xp_value' => 0,   'daily_cap' => null],
        ];

        foreach ($rules as $rule) {
            DB::table('xp_rules')->updateOrInsert(
                ['action' => $rule['action']],
                array_merge($rule, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Active bonus rules
        $bonuses = [
            ['days_threshold' => 3,   'bonus_xp' => 5],
            ['days_threshold' => 7,   'bonus_xp' => 10],
            ['days_threshold' => 15,  'bonus_xp' => 20],
            ['days_threshold' => 30,  'bonus_xp' => 40],
            ['days_threshold' => 60,  'bonus_xp' => 80],
            ['days_threshold' => 90,  'bonus_xp' => 160],
            ['days_threshold' => 120, 'bonus_xp' => 320],
        ];

        foreach ($bonuses as $bonus) {
            DB::table('active_bonus_rules')->updateOrInsert(
                ['days_threshold' => $bonus['days_threshold']],
                $bonus
            );
        }

        // Antifraud limits (single row)
        DB::table('antifraud_limits')->updateOrInsert(
            ['id' => 1],
            [
                'max_water_units'  => 20000,
                'max_workouts_day' => 3,
                'max_weight_day'   => 1,
                'updated_at'       => now(),
            ]
        );
    }
}
