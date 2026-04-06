<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 60)->default('UTC')->after('bio');
        });

        Schema::table('user_gamification', function (Blueprint $table) {
            $table->string('last_week_safety_day_used', 10)->nullable()->comment('ISO Week ex: 2026-W12');
            $table->date('last_processed_date')->nullable()->comment('Last date processed by timezone cron');
        });

        // Modify the enum (MySQL-only, SQLite has no ENUM type)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE xp_transactions MODIFY COLUMN type ENUM('workout_logged', 'workout_completed', 'long_workout', 'water_goal', 'weight_logged', 'streak_bonus', 'quest_completed', 'achievement_unlocked', 'meal_logged', 'manual_adjustment', 'daily_login', 'penalty_workout', 'penalty_calories') NOT NULL");
        }

        Schema::table('daily_activity_limits', function (Blueprint $table) {
            $table->boolean('login_xp_granted')->default(false);
            $table->boolean('meal_xp_granted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('daily_activity_limits', function (Blueprint $table) {
            $table->dropColumn(['login_xp_granted', 'meal_xp_granted']);
        });

        // Reverting ENUM might drop data if new ones were used, but standard down method:
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE xp_transactions MODIFY COLUMN type ENUM('workout_logged', 'workout_completed', 'long_workout', 'water_goal', 'weight_logged', 'streak_bonus', 'quest_completed', 'achievement_unlocked', 'meal_logged', 'manual_adjustment') NOT NULL");
        }

        Schema::table('user_gamification', function (Blueprint $table) {
            $table->dropColumn(['last_week_safety_day_used', 'last_processed_date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
