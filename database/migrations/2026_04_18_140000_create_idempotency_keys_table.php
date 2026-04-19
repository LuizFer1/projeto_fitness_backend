<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 100);
            $table->foreignUuid('user_id')->nullable()->references('id')->on('users')->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->string('request_hash', 64);
            $table->enum('status', ['processing', 'completed'])->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->unique(['key', 'user_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
