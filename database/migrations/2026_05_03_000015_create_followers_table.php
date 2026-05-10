<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('follower_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('followee_id')->references('id')->on('users')->cascadeOnDelete();
            // pending = awaiting approval (private profiles), accepted = active follow
            $table->enum('status', ['pending', 'accepted'])->default('accepted');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['follower_id', 'followee_id']);
            $table->index('followee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followers');
    }
};
