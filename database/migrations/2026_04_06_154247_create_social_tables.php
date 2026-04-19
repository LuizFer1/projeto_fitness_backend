<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('requester_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('addressee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'blocked']);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->unique(['requester_id', 'addressee_id']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('type', ['text', 'achievement', 'goal_completed', 'workout_completed', 'level_up']);
            $table->text('content')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('visibility', ['public', 'friends_only']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_likes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('friendships');
    }
};
