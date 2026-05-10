<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('taken_at');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->enum('category', ['front', 'side', 'back', 'other'])->default('front');
            $table->string('s3_key', 500)->comment('S3 object key (encrypted file)');
            $table->binary('encryption_iv')->comment('AES-256-GCM IV (16 bytes, stored as hex)');
            $table->text('encryption_key_wrapped')->comment('DEK wrapped by user master key (base64)');
            $table->text('caption')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_photos');
    }
};
