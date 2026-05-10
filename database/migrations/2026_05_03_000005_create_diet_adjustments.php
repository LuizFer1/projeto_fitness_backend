<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diet_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('source_meal_log_id')->nullable()->comment('MealLog that triggered this adjustment');
            $table->date('target_date');
            $table->integer('delta_kcal')->comment('Excess calories to compensate on this date');
            $table->integer('delta_protein_g')->default(0);
            $table->integer('delta_carbs_g')->default(0);
            $table->integer('delta_fat_g')->default(0);
            $table->enum('mode', ['same_day', 'dilution'])->default('same_day');
            $table->timestamp('applied_at')->nullable()->comment('NULL = pending, timestamp = already applied');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'target_date']);
            $table->index('applied_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diet_adjustments');
    }
};
