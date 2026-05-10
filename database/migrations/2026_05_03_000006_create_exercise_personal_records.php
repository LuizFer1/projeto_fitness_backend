<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_personal_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('exercise_id')->references('id')->on('exercises')->cascadeOnDelete();
            $table->uuid('workout_log_id')->nullable()->comment('Log that produced this PR');
            $table->decimal('one_rm_kg', 6, 2)->comment('Epley 1RM = w * (1 + reps/30)');
            $table->decimal('weight_kg', 6, 2)->comment('Actual weight lifted');
            $table->unsignedTinyInteger('reps')->comment('Reps used in Epley calculation');
            $table->date('achieved_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'exercise_id', 'achieved_at']);
            $table->index(['user_id', 'one_rm_kg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_personal_records');
    }
};
