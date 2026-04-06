<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_uuid')->references('uuid')->on('users')->cascadeOnDelete();
            $table->string('period'); // weekly, monthly, alltime
            $table->string('period_key'); // YYYY-Www, YYYY-MM, global
            $table->unsignedInteger('rank');
            $table->unsignedInteger('xp_points');
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['user_uuid', 'period', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_snapshots');
    }
};
