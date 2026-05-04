<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->boolean('streak_at_risk')->default(true);
            $table->boolean('rank_drop')->default(true);
            $table->boolean('achievement_close')->default(true);
            $table->boolean('achievement_unlocked')->default(true);
            $table->boolean('daily_summary')->default(false);
            // Quiet hours stored in user's local time (HH:MM)
            $table->time('quiet_hours_start')->default('23:00');
            $table->time('quiet_hours_end')->default('07:00');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
