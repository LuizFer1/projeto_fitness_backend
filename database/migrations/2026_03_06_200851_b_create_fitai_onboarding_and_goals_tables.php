<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('gender', ['male','female','other','prefer_not_to_say']);
            $table->unsignedTinyInteger('age');
            $table->decimal('height_cm', 5, 2);
            $table->decimal('weight_kg', 5, 2);
            $table->unsignedTinyInteger('exercise_frequency')->default(0)->comment('0-7 times per week');
            $table->enum('work_style', ['sedentary','light','moderate','active','very_active']);
            $table->decimal('body_fat_pct', 4, 1)->nullable();
            $table->decimal('bmr', 7, 2)->nullable()->comment('Basal metabolic rate');
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('user_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('main_goal', ['weight_loss','hypertrophy','maintenance','health','conditioning']);
            $table->unsignedInteger('goal_calories_day')->nullable();
            $table->unsignedInteger('goal_steps_day')->nullable();
            $table->decimal('goal_weight_kg', 5, 2)->nullable();
            $table->decimal('goal_protein_g', 6, 2)->nullable();
            $table->decimal('goal_carbs_g', 6, 2)->nullable();
            $table->decimal('goal_fat_g', 6, 2)->nullable();
            $table->unsignedTinyInteger('goal_workouts_week')->nullable();
            $table->decimal('goal_water_liters', 4, 2)->nullable()->default(2.00);
            $table->date('deadline')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_goals');
        Schema::dropIfExists('onboarding');
    }
};
