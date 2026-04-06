<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_uuid')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreignUuid('friend_uuid')->references('uuid')->on('users')->cascadeOnDelete();
            $table->string('status')->default('accepted');
            $table->timestamps();

            $table->unique(['user_uuid', 'friend_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
