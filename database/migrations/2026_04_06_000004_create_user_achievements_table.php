<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_uuid')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->timestamps();

            $table->unique(['user_uuid', 'achievement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }
};
