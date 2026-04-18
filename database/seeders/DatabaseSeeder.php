<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GamificationSeeder::class,
            MissionSeeder::class,
            BadgeSeeder::class,
        ]);
    }
}
