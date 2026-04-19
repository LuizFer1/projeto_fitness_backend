<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'meal_logs',
            'workout_logs',
            'workout_exercise_logs',
            'body_measurements',
            'water_logs',
            'ai_plans',
            'ai_feedback_triggers',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'meal_logs',
            'workout_logs',
            'workout_exercise_logs',
            'body_measurements',
            'water_logs',
            'ai_plans',
            'ai_feedback_triggers',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('updated_at');
                });
            }
        }
    }
};
