<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            GamificationSeeder::class,
            MissionSeeder::class,
            BadgeSeeder::class,
            AchievementSeeder::class,
        ]);
    }
}
