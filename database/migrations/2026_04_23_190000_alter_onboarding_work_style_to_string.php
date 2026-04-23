<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `onboarding` MODIFY `work_style` VARCHAR(32) NOT NULL");
            return;
        }

        Schema::table('onboarding', function (Blueprint $table) {
            $table->string('work_style', 32)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `onboarding` MODIFY `work_style` ENUM('sedentary','light','moderate','active','very_active') NOT NULL");
            return;
        }

        Schema::table('onboarding', function (Blueprint $table) {
            $table->enum('work_style', ['sedentary', 'light', 'moderate', 'active', 'very_active'])->change();
        });
    }
};
