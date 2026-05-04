<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_exercise_logs', function (Blueprint $table) {
            // Granular per-set data: [{set: 1, reps: 10, weight_kg: 80.0, completed: true}]
            // Aggregate columns (sets/reps/weight_kg) kept for back-compat.
            $table->json('sets_json')->nullable()->after('weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercise_logs', function (Blueprint $table) {
            $table->dropColumn('sets_json');
        });
    }
};
