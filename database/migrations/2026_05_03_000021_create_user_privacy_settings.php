<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_privacy_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->boolean('share_weight')->default(false)
                  ->comment('Show weight/body measurements on public profile');
            $table->boolean('share_macros')->default(false)
                  ->comment('Show daily macro targets on public profile');
            $table->boolean('share_one_rm')->default(true)
                  ->comment('Show personal records on public profile');
            $table->boolean('share_streak')->default(true)
                  ->comment('Show current streak on public profile');
            $table->boolean('share_achievements')->default(true)
                  ->comment('Show achievements/badges on public profile');
            $table->boolean('share_workouts')->default(true)
                  ->comment('Show workout history summary on public profile');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_privacy_settings');
    }
};
