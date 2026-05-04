<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_daily', function (Blueprint $table) {
            // Goals (copied from user_goals at day creation for immutability)
            $table->integer('protein_goal_g')->default(0)->after('calories_goal');
            $table->integer('carbs_goal_g')->default(0)->after('protein_goal_g');
            $table->integer('fat_goal_g')->default(0)->after('carbs_goal_g');

            // Actual consumed (aggregated from meal_logs)
            $table->integer('calories_consumed')->default(0)->after('fat_goal_g');
            $table->integer('protein_consumed_g')->default(0)->after('calories_consumed');
            $table->integer('carbs_consumed_g')->default(0)->after('protein_consumed_g');
            $table->integer('fat_consumed_g')->default(0)->after('carbs_consumed_g');

            // Delta and adjustment state
            $table->integer('delta_kcal')->default(0)->comment('consumed - goal (positive = excess)')->after('fat_consumed_g');
            $table->decimal('adjustment_ratio', 5, 4)->default(0)->after('delta_kcal');
            $table->boolean('dilution_active')->default(false)->after('adjustment_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_daily', function (Blueprint $table) {
            $table->dropColumn([
                'protein_goal_g', 'carbs_goal_g', 'fat_goal_g',
                'calories_consumed', 'protein_consumed_g', 'carbs_consumed_g', 'fat_consumed_g',
                'delta_kcal', 'adjustment_ratio', 'dilution_active',
            ]);
        });
    }
};
