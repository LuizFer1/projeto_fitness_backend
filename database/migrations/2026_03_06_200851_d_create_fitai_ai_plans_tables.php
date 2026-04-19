<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('type', ['nutritional','workout','complete']);
            $table->smallInteger('version')->default(1);
            $table->enum('status', ['draft','active','replaced','archived'])->default('draft');
            $table->json('content_json')->comment('Complete plan structure');
            $table->text('generation_reason')->nullable()->comment('Why this plan was generated/regenerated');
            $table->text('context_prompt')->nullable()->comment('Prompt sent to AI (for audit)');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
        });

        Schema::create('plan_workouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_plan_id')->references('id')->on('ai_plans')->cascadeOnDelete();
            $table->tinyInteger('day_of_week')->comment('0=Sun, 1=Mon, ..., 6=Sat');
            $table->string('workout_name', 100)->nullable()->comment('Ex: Workout A - Chest and Triceps');
            $table->text('ai_observations')->nullable();

            $table->index('ai_plan_id');
        });

        Schema::create('plan_workout_exercises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_workout_id')->references('id')->on('plan_workouts')->cascadeOnDelete();
            $table->foreignUuid('exercise_id')->references('id')->on('exercises')->restrictOnDelete();
            $table->tinyInteger('order')->default(1);
            $table->tinyInteger('rec_sets')->nullable();
            $table->tinyInteger('rec_reps')->nullable();
            $table->decimal('rec_weight_kg', 5, 2)->nullable();
            $table->smallInteger('rest_sec')->nullable();
            $table->text('ai_notes')->nullable()->comment('Specific AI instruction for this exercise');

            $table->index('plan_workout_id');
            $table->index('exercise_id');
        });

        Schema::create('plan_meals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_plan_id')->references('id')->on('ai_plans')->cascadeOnDelete();
            $table->foreignUuid('meal_id')->references('id')->on('meals')->restrictOnDelete();
            $table->tinyInteger('day_of_week')->nullable()->comment('NULL = all days');
            $table->enum('meal_type', ['breakfast','snack','lunch','dinner','pre_workout','post_workout']);
            $table->time('suggested_time')->nullable();
            $table->text('ai_notes')->nullable();

            $table->index('ai_plan_id');
        });

        Schema::create('ai_feedback_triggers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('event_type', ['exercise_pain','food_allergy','preference','goal_reached','low_adherence','injury','intolerance']);
            $table->uuid('ref_id')->nullable()->comment('ID of the record that triggered the event');
            $table->string('ref_table', 60)->nullable()->comment('Table of ref_id');
            $table->text('raw_note')->comment('Original text from user or system');
            $table->boolean('is_processed')->default(false);
            $table->foreignUuid('generated_plan_id')->nullable()->references('id')->on('ai_plans')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->index(['user_id', 'is_processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback_triggers');
        Schema::dropIfExists('plan_meals');
        Schema::dropIfExists('plan_workout_exercises');
        Schema::dropIfExists('plan_workouts');
        Schema::dropIfExists('ai_plans');
    }
};
