<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_workout_exercise_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('plan_workout_exercise_id')->references('id')->on('plan_workout_exercises')->cascadeOnDelete();
            // type values: pain (dor/lesão), broken (equipamento quebrado),
            // heavy (carga pesada — diminuir), custom (texto livre)
            $table->enum('type', ['pain', 'broken', 'heavy', 'custom']);
            $table->text('text')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('plan_workout_exercise_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_workout_exercise_comments');
    }
};
