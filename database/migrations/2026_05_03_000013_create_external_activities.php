<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('provider', ['healthkit', 'googlefit', 'garmin']);
            $table->string('provider_activity_id', 200);
            $table->uuid('workout_log_id')->nullable()->comment('Set after converting to internal log');
            $table->json('payload_json')->nullable()->comment('Raw payload from provider');
            $table->timestamp('synced_at')->useCurrent();

            // Dedup key (RNF-08 idempotência)
            $table->unique(['provider', 'provider_activity_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_activities');
    }
};
