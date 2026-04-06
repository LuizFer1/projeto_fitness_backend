<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('plan_workout_id')->nullable()->references('id')->on('plan_workouts')->nullOnDelete();
            $table->date('date');
            $table->integer('duration_min')->nullable();
            $table->decimal('calories_burned', 7, 2)->nullable();
            $table->integer('steps')->nullable();
            $table->enum('mood', ['great','good','neutral','tired','bad'])->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'date']);
            $table->index('plan_workout_id');
        });

        Schema::create('workout_exercise_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workout_log_id')->references('id')->on('workout_logs')->cascadeOnDelete();
            $table->foreignUuid('exercise_id')->references('id')->on('exercises')->restrictOnDelete();
            $table->tinyInteger('order')->nullable();
            $table->tinyInteger('sets')->nullable();
            $table->tinyInteger('reps')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->tinyInteger('pain_level')->default(0)->comment('0=no pain, 10=intense pain');
            $table->string('pain_location', 100)->nullable()->comment('Ex: shoulder joint, elbow');
            $table->text('user_note')->nullable()->comment('Free text — AI uses to detect problems');
            $table->boolean('is_completed')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('workout_log_id');
            $table->index('exercise_id');
        });

        Schema::create('meal_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('meal_id')->nullable()->references('id')->on('meals')->nullOnDelete();
            $table->date('date');
            $table->time('time')->nullable();
            $table->enum('meal_type', ['breakfast','snack','lunch','dinner','pre_workout','post_workout']);
            $table->decimal('calories_consumed', 7, 2)->default(0);
            $table->decimal('protein_g', 6, 2)->default(0);
            $table->decimal('carbs_g', 6, 2)->default(0);
            $table->decimal('fat_g', 6, 2)->default(0);
            $table->decimal('fiber_g', 6, 2)->nullable()->default(0);
            $table->text('user_note')->nullable()->comment('Free text — AI detects allergies/preferences');
            $table->boolean('is_completed')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'date']);
            $table->index('meal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_logs');
        Schema::dropIfExists('workout_exercise_logs');
        Schema::dropIfExists('workout_logs');
    }
};
