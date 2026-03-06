<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_onboarding', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('user_uuid')->references('uuid')->on('users')->cascadeOnDelete();
            
            $table->boolean('completed')->default(false);
            $table->smallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('body_fat_percent', 5, 2)->nullable();
            $table->smallInteger('workouts_per_week')->nullable();
            $table->string('work_style', 30)->nullable();
            $table->timestamps();

            $table->unique('user_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboarding');
    }
};
