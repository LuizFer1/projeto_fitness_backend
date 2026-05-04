<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('period', 10)->comment('7d | 30d | 90d');
            $table->string('format', 10)->default('csv')->comment('csv | pdf');
            $table->enum('status', ['pending', 'generating', 'ready', 'failed'])->default('pending');
            $table->string('s3_key')->nullable();
            $table->string('download_url')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_exports');
    }
};
