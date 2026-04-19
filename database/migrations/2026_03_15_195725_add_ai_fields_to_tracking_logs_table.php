<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workout_logs', function (Blueprint $table) {
            $table->text('ai_feedback')->nullable()->after('observations');
            $table->json('muscles_trained')->nullable()->after('ai_feedback');
        });

        Schema::table('meal_logs', function (Blueprint $table) {
            $table->text('ai_feedback')->nullable()->after('user_note');
            $table->json('items_json')->nullable()->after('ai_feedback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_logs', function (Blueprint $table) {
            $table->dropColumn(['ai_feedback', 'items_json']);
        });

        Schema::table('workout_logs', function (Blueprint $table) {
            $table->dropColumn(['ai_feedback', 'muscles_trained']);
        });
    }
};
