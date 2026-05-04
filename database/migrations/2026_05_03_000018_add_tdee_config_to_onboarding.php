<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding', function (Blueprint $table) {
            $table->string('tdee_formula', 20)->default('mifflin')->after('bmr')
                  ->comment('mifflin (Mifflin-St Jeor) or harris (Harris-Benedict)');
            $table->decimal('activity_factor', 4, 2)->nullable()->after('tdee_formula')
                  ->comment('Manual override for activity multiplier (1.2–1.9). Null = auto from exercise_frequency + work_style');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding', function (Blueprint $table) {
            $table->dropColumn(['tdee_formula', 'activity_factor']);
        });
    }
};
