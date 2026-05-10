<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GamificationSeeder::class,
            AchievementSeeder::class,
            MissionSeeder::class,
            BadgeSeeder::class,
            PlanSeeder::class,
        ]);
    }
}
