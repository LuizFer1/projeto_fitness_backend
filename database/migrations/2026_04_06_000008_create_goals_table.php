<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('type', 30);
            $table->decimal('target_value', 12, 2);
            $table->decimal('initial_value', 12, 2)->default(0);
            $table->decimal('current_value', 12, 2)->default(0);
            $table->string('unit', 20);
            $table->date('deadline')->nullable();
            $table->string('visibility', 10)->default('private');
            $table->string('status', 15)->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'status']);
            $table->index(['user_uuid', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
