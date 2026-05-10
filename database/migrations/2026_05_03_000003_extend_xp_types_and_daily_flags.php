<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend xp_transactions.type enum (MySQL-only — SQLite has no ENUM)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE xp_transactions MODIFY COLUMN type ENUM(
                'workout_logged','workout_completed','long_workout',
                'water_goal','weight_logged','streak_bonus',
                'quest_completed','achievement_unlocked','meal_logged','manual_adjustment',
                'daily_login','penalty_workout','penalty_calories',
                'clean_diet_day','protein_goal_met','water_goal_met',
                'cardio_completed','pr_set','asset_shared','progress_photo'
            ) NOT NULL");
        }

        // New daily activity flags for SRS XP events
        Schema::table('daily_activity_limits', function (Blueprint $table) {
            $table->boolean('clean_diet_xp_granted')->default(false)->after('meal_xp_granted');
            $table->boolean('protein_xp_granted')->default(false)->after('clean_diet_xp_granted');
            $table->boolean('water_xp_granted')->default(false)->after('protein_xp_granted');
            $table->boolean('cardio_xp_granted')->default(false)->after('water_xp_granted');
            $table->boolean('photo_xp_granted')->default(false)->after('cardio_xp_granted');
        });
    }

    public function down(): void
    {
        Schema::table('daily_activity_limits', function (Blueprint $table) {
            $table->dropColumn(['clean_diet_xp_granted', 'protein_xp_granted', 'water_xp_granted', 'cardio_xp_granted', 'photo_xp_granted']);
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE xp_transactions MODIFY COLUMN type ENUM(
                'workout_logged','workout_completed','long_workout',
                'water_goal','weight_logged','streak_bonus',
                'quest_completed','achievement_unlocked','meal_logged','manual_adjustment',
                'daily_login','penalty_workout','penalty_calories'
            ) NOT NULL");
        }
    }
};
