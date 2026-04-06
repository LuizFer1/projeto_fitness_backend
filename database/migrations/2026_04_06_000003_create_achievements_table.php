<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('title', 100);
            $table->string('description', 255);
            $table->string('icon', 100)->default('🏆');
            $table->string('category', 50);
            $table->unsignedInteger('xp_reward');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
