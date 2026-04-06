<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('muscle_group', 80)->nullable()->comment('Ex: shoulder, chest, back, legs');
            $table->enum('category', ['strength','cardio','mobility','hiit','stretching','functional'])->default('strength');
            $table->enum('difficulty', ['beginner','intermediate','advanced'])->default('beginner');
            $table->string('equipment', 100)->nullable()->comment('Ex: dumbbells, barbell, bodyweight');
            $table->text('description')->nullable();
            $table->decimal('calories_per_min', 5, 2)->nullable()->comment('Estimated calories per minute');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('muscle_group');
            $table->index('category');
        });

        Schema::create('foods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('category', 80)->nullable()->comment('Ex: protein, carbs, fruit, dairy');
            $table->decimal('calories_100g', 7, 2)->default(0);
            $table->decimal('protein_g', 6, 2)->default(0)->comment('Per 100g');
            $table->decimal('carbs_g', 6, 2)->default(0)->comment('Per 100g');
            $table->decimal('fat_g', 6, 2)->default(0)->comment('Per 100g');
            $table->decimal('fiber_g', 6, 2)->nullable()->default(0);
            $table->decimal('sodium_mg', 7, 2)->nullable()->default(0);
            $table->decimal('standard_portion_g', 6, 2)->nullable()->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('name');
            $table->index('category');
        });

        Schema::create('meals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->enum('type', ['breakfast','snack','lunch','dinner','pre_workout','post_workout']);
            $table->text('description')->nullable();
            $table->decimal('total_calories', 7, 2)->default(0);
            $table->decimal('total_protein_g', 6, 2)->default(0);
            $table->decimal('total_carbs_g', 6, 2)->default(0);
            $table->decimal('total_fat_g', 6, 2)->default(0);
            $table->decimal('total_fiber_g', 6, 2)->nullable()->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('type');
        });

        Schema::create('meal_foods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meal_id')->references('id')->on('meals')->cascadeOnDelete();
            $table->foreignUuid('food_id')->references('id')->on('foods')->restrictOnDelete();
            $table->decimal('quantity_g', 6, 2)->default(100);

            $table->unique(['meal_id', 'food_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_foods');
        Schema::dropIfExists('meals');
        Schema::dropIfExists('foods');
        Schema::dropIfExists('exercises');
    }
};
