<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('level_definitions')->truncate();

        $levels = config('gamification.levels');

        DB::table('level_definitions')->insert(
            collect($levels)->map(fn ($lvl, $num) => [
                'level_num' => $num,
                'title' => $lvl['title'],
                'min_xp' => $lvl['min_xp'],
                'max_xp' => $lvl['max_xp'],
                'hex_color' => $lvl['hex_color'],
                'icon' => $lvl['icon'],
                'benefit_desc' => $lvl['benefit'],
            ])->values()->all()
        );

        // Cap any user currently above level 8 (old 10-level system)
        DB::table('user_gamification')->where('current_level', '>', 8)->update(['current_level' => 8]);
    }

    public function down(): void
    {
        DB::table('level_definitions')->truncate();

        DB::table('level_definitions')->insert([
            ['level_num' => 1,  'title' => 'Beginner',    'min_xp' => 0,    'max_xp' => 199,  'hex_color' => '#94a3b8', 'icon' => '🌱'],
            ['level_num' => 2,  'title' => 'Apprentice',  'min_xp' => 200,  'max_xp' => 499,  'hex_color' => '#22c55e', 'icon' => '⚡'],
            ['level_num' => 3,  'title' => 'Dedicated',   'min_xp' => 500,  'max_xp' => 899,  'hex_color' => '#3b82f6', 'icon' => '💪'],
            ['level_num' => 4,  'title' => 'Consistent',  'min_xp' => 900,  'max_xp' => 1499, 'hex_color' => '#8b5cf6', 'icon' => '🎯'],
            ['level_num' => 5,  'title' => 'Focused',     'min_xp' => 1500, 'max_xp' => 2199, 'hex_color' => '#f59e0b', 'icon' => '🔥'],
            ['level_num' => 6,  'title' => 'Determined',  'min_xp' => 2200, 'max_xp' => 3099, 'hex_color' => '#f97316', 'icon' => '🏅'],
            ['level_num' => 7,  'title' => 'Athlete',     'min_xp' => 3100, 'max_xp' => 4299, 'hex_color' => '#ef4444', 'icon' => '🏋️'],
            ['level_num' => 8,  'title' => 'Warrior',     'min_xp' => 4300, 'max_xp' => 5699, 'hex_color' => '#ec4899', 'icon' => '⚔️'],
            ['level_num' => 9,  'title' => 'Champion',    'min_xp' => 5700, 'max_xp' => 8099, 'hex_color' => '#06b6d4', 'icon' => '🏆'],
            ['level_num' => 10, 'title' => 'Elite',       'min_xp' => 8100, 'max_xp' => null, 'hex_color' => '#f0a500', 'icon' => '👑'],
        ]);
    }
};
