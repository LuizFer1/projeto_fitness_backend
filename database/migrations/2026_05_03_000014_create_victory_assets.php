<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('victory_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workout_log_id');
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('type', ['strength', 'cardio']);
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->string('s3_key')->nullable()->comment('S3 object key for the generated PNG');
            $table->string('public_url')->nullable()->comment('CDN-friendly URL, cached 30 days');
            $table->string('error_message', 500)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('workout_log_id');
            $table->index(['workout_log_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('victory_assets');
    }
};
