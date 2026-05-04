<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biweekly_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['pending', 'generating', 'ready', 'failed'])->default('pending');
            $table->string('s3_key')->nullable()->comment('S3 key for the generated PDF');
            $table->string('public_url')->nullable();
            $table->json('summary_data')->nullable()->comment('Snapshot of metrics at generation time');
            $table->string('error_message', 500)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biweekly_reports');
    }
};
