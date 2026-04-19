<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_gamification', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->integer('xp_total')->default(0);
            $table->smallInteger('current_level')->default(1);
            $table->integer('xp_to_next')->default(200)->comment('XP missing for next level');
            $table->integer('current_streak')->default(0);
            $table->integer('max_streak')->default(0);
            $table->date('last_activity')->nullable();
            $table->integer('current_week_xp')->default(0);
            $table->integer('current_month_xp')->default(0);
            $table->integer('total_workouts')->default(0);
            $table->integer('total_water_days')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('user_id');
            $table->index('xp_total');
            $table->index('current_level');
        });

        Schema::create('xp_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('type', [
                'workout_logged', 'workout_completed', 'long_workout',
                'water_goal', 'weight_logged', 'streak_bonus',
                'quest_completed', 'achievement_unlocked', 'meal_logged', 'manual_adjustment',
                'daily_login', 'penalty_workout', 'penalty_calories',
            ]);
            $table->integer('xp_gained');
            $table->string('description', 200)->nullable();
            $table->uuid('ref_id')->nullable()->comment('ID of the record that generated XP');
            $table->string('ref_table', 60)->nullable();
            $table->date('date');
            $table->integer('xp_total_snapshot')->default(0)->comment('Accumulated XP at the moment');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'date']);
            $table->index('type');
        });

        Schema::create('daily_activity_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('date');
            $table->tinyInteger('workout_count')->default(0)->comment('Max 3 per day');
            $table->decimal('water_liters', 4, 2)->default(0.00)->comment('Max 5L per day');
            $table->boolean('water_goal_reached')->default(false)->comment('Water goal already gave XP today');
            $table->boolean('weight_logged')->default(false)->comment('Weight already gave XP today');
            $table->integer('daily_xp_gained')->default(0)->comment('Accumulated XP today');
            $table->integer('daily_xp_limit')->default(300);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('streak_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('date');
            $table->integer('streak_day')->default(1)->comment('Streak day number on this date');
            $table->integer('streak_bonus_xp')->default(0);
            $table->boolean('milestone_reached')->default(false)->comment('1 if reached milestone (3,7,30,90 days)');

            $table->unique(['user_id', 'date']);
        });

        Schema::create('level_definitions', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->smallInteger('level_num')->unique();
            $table->string('title', 60);
            $table->integer('min_xp');
            $table->integer('max_xp')->nullable()->comment('NULL = last level');
            $table->char('hex_color', 7)->nullable()->default('#a855f7');
            $table->string('icon', 10)->nullable();
            $table->text('benefit_desc')->nullable();
        });

        DB::table('level_definitions')->insert([
            ['level_num' => 1, 'title' => 'Beginner', 'min_xp' => 0, 'max_xp' => 199, 'hex_color' => '#94a3b8', 'icon' => '🌱'],
            ['level_num' => 2, 'title' => 'Apprentice', 'min_xp' => 200, 'max_xp' => 499, 'hex_color' => '#22c55e', 'icon' => '⚡'],
            ['level_num' => 3, 'title' => 'Dedicated', 'min_xp' => 500, 'max_xp' => 899, 'hex_color' => '#3b82f6', 'icon' => '💪'],
            ['level_num' => 4, 'title' => 'Consistent', 'min_xp' => 900, 'max_xp' => 1499, 'hex_color' => '#8b5cf6', 'icon' => '🎯'],
            ['level_num' => 5, 'title' => 'Focused', 'min_xp' => 1500, 'max_xp' => 2199, 'hex_color' => '#f59e0b', 'icon' => '🔥'],
            ['level_num' => 6, 'title' => 'Determined', 'min_xp' => 2200, 'max_xp' => 3099, 'hex_color' => '#f97316', 'icon' => '🏅'],
            ['level_num' => 7, 'title' => 'Athlete', 'min_xp' => 3100, 'max_xp' => 4299, 'hex_color' => '#ef4444', 'icon' => '🏋️'],
            ['level_num' => 8, 'title' => 'Warrior', 'min_xp' => 4300, 'max_xp' => 5699, 'hex_color' => '#ec4899', 'icon' => '⚔️'],
            ['level_num' => 9, 'title' => 'Champion', 'min_xp' => 5700, 'max_xp' => 8099, 'hex_color' => '#06b6d4', 'icon' => '🏆'],
            ['level_num' => 10, 'title' => 'Elite', 'min_xp' => 8100, 'max_xp' => null, 'hex_color' => '#f0a500', 'icon' => '👑'],
        ]);

        Schema::create('achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('icon', 10)->nullable();
            $table->enum('category', ['consistency', 'workout', 'water', 'nutrition', 'hardcore', 'special']);
            $table->integer('xp_reward')->default(0);
            $table->enum('condition_type', ['streak_days', 'total_workouts', 'water_days', 'active_days', 'hardcore_weeks']);
            $table->integer('condition_value')->comment('Ex: 7 for 7 days streak');
            $table->boolean('is_hidden')->default(false)->comment('1 = hidden until unlocked');
            $table->boolean('is_active')->default(true);
        });

        DB::table('achievements')->insert([
            ['id' => Str::uuid(), 'slug' => 'streak_7', 'name' => '7 Days Streak', 'description' => 'Active for 7 consecutive days', 'icon' => '🔥', 'category' => 'consistency', 'xp_reward' => 150, 'condition_type' => 'streak_days', 'condition_value' => 7],
            ['id' => Str::uuid(), 'slug' => 'streak_30', 'name' => '30 Days Streak', 'description' => 'Active for 30 consecutive days', 'icon' => '🔥', 'category' => 'consistency', 'xp_reward' => 400, 'condition_type' => 'streak_days', 'condition_value' => 30],
            ['id' => Str::uuid(), 'slug' => 'streak_90', 'name' => '90 Days Streak', 'description' => 'Active for 90 consecutive days', 'icon' => '🌟', 'category' => 'consistency', 'xp_reward' => 1200, 'condition_type' => 'streak_days', 'condition_value' => 90],
            ['id' => Str::uuid(), 'slug' => 'treinos_10', 'name' => '10 Workouts', 'description' => 'Completed 10 workouts', 'icon' => '💪', 'category' => 'workout', 'xp_reward' => 100, 'condition_type' => 'total_workouts', 'condition_value' => 10],
            ['id' => Str::uuid(), 'slug' => 'treinos_50', 'name' => '50 Workouts', 'description' => 'Completed 50 workouts', 'icon' => '💪', 'category' => 'workout', 'xp_reward' => 300, 'condition_type' => 'total_workouts', 'condition_value' => 50],
            ['id' => Str::uuid(), 'slug' => 'treinos_100', 'name' => '100 Workouts', 'description' => 'Completed 100 workouts', 'icon' => '🏋️', 'category' => 'workout', 'xp_reward' => 800, 'condition_type' => 'total_workouts', 'condition_value' => 100],
            ['id' => Str::uuid(), 'slug' => 'agua_5dias', 'name' => 'Hydrated', 'description' => 'Reached water goal for 5 days', 'icon' => '💧', 'category' => 'water', 'xp_reward' => 50, 'condition_type' => 'water_days', 'condition_value' => 5],
            ['id' => Str::uuid(), 'slug' => 'agua_20dias', 'name' => 'Always Hydrated', 'description' => 'Reached water goal for 20 days', 'icon' => '💧', 'category' => 'water', 'xp_reward' => 150, 'condition_type' => 'water_days', 'condition_value' => 20],
            ['id' => Str::uuid(), 'slug' => 'hardcore_semana', 'name' => 'Hardcore Week', 'description' => 'Worked out 6x in a week', 'icon' => '🦾', 'category' => 'hardcore', 'xp_reward' => 250, 'condition_type' => 'hardcore_weeks', 'condition_value' => 1],
            ['id' => Str::uuid(), 'slug' => 'ativo_3meses', 'name' => '3 Active Months', 'description' => 'Active for 3 months', 'icon' => '🌎', 'category' => 'hardcore', 'xp_reward' => 700, 'condition_type' => 'active_days', 'condition_value' => 90],
        ]);

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('achievement_id')->references('id')->on('achievements')->restrictOnDelete();
            $table->timestamp('unlocked_at')->useCurrent();
            $table->integer('xp_received')->default(0);
            $table->boolean('is_notified')->default(false)->comment('0 = show popup on UI');

            $table->unique(['user_id', 'achievement_id']);
        });

        Schema::create('quests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('icon', 10)->nullable();
            $table->enum('type', ['basic', 'special', 'event'])->default('basic');
            $table->enum('periodicity', ['once', 'weekly', 'monthly', 'recurring'])->default('once');
            $table->enum('condition_type', ['streak_days', 'workouts_period', 'water_days', 'meals_logged', 'weight_logged']);
            $table->integer('condition_value');
            $table->integer('xp_reward')->default(0);
            $table->boolean('is_active')->default(true);
        });

        DB::table('quests')->insert([
            ['id' => Str::uuid(), 'slug' => 'treinar_3_seguidos', 'name' => 'Iron Trio', 'description' => 'Workout 3 days in a row', 'icon' => '🏋️', 'type' => 'basic', 'periodicity' => 'once', 'condition_type' => 'streak_days', 'condition_value' => 3, 'xp_reward' => 100],
            ['id' => Str::uuid(), 'slug' => 'agua_5_seguidos', 'name' => 'Health Tide', 'description' => 'Drink water 5 days in a row', 'icon' => '💧', 'type' => 'basic', 'periodicity' => 'once', 'condition_type' => 'water_days', 'condition_value' => 5, 'xp_reward' => 80],
            ['id' => Str::uuid(), 'slug' => 'treinar_10_mes', 'name' => 'Athlete of the Month', 'description' => 'Workout 10 times in a month', 'icon' => '📅', 'type' => 'basic', 'periodicity' => 'monthly', 'condition_type' => 'workouts_period', 'condition_value' => 10, 'xp_reward' => 200],
            ['id' => Str::uuid(), 'slug' => 'refeicoes_semana', 'name' => 'Nutrition on Track', 'description' => 'Log 7 meals in a week', 'icon' => '🍽️', 'type' => 'basic', 'periodicity' => 'weekly', 'condition_type' => 'meals_logged', 'condition_value' => 7, 'xp_reward' => 60],
            ['id' => Str::uuid(), 'slug' => 'peso_4x_mes', 'name' => 'Total Tracking', 'description' => 'Log weight 4x in a month', 'icon' => '⚖️', 'type' => 'basic', 'periodicity' => 'monthly', 'condition_type' => 'weight_logged', 'condition_value' => 4, 'xp_reward' => 40],
        ]);

        Schema::create('user_quests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('quest_id')->references('id')->on('quests')->restrictOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'expired'])->default('not_started');
            $table->integer('current_progress')->default(0);
            $table->integer('target_progress');
            $table->date('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('xp_received')->default(0);
            $table->string('ref_period', 10)->nullable()->comment('"2025-06" for monthly, "2025-W22" for weekly, NULL for once');
            $table->boolean('is_notified')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'quest_id', 'ref_period']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('ranking_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('type', ['weekly', 'monthly', 'all_time']);
            $table->string('ref_period', 10)->comment('"2025-W22" | "2025-06" | "all"');
            $table->integer('period_xp')->default(0);
            $table->integer('position')->nullable();
            $table->integer('previous_position')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'type', 'ref_period']);
            $table->index(['type', 'ref_period', 'period_xp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_snapshots');
        Schema::dropIfExists('user_quests');
        Schema::dropIfExists('quests');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('level_definitions');
        Schema::dropIfExists('streak_history');
        Schema::dropIfExists('daily_activity_limits');
        Schema::dropIfExists('xp_transactions');
        Schema::dropIfExists('user_gamification');
    }
};
