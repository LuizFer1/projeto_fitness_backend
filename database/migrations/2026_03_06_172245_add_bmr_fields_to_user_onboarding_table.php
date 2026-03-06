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
        Schema::table('user_onboarding', function (Blueprint $table) {
            $table->enum('gender', ['M', 'F'])->nullable()->after('completed');
            $table->integer('age')->nullable()->after('gender');
            $table->integer('bmr')->nullable()->after('work_style');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_onboarding', function (Blueprint $table) {
            $table->dropColumn(['gender', 'age', 'bmr']);
        });
    }
};
