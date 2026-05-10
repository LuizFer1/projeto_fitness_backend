<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (test) requires doctrine/dbal to alter columns; the simplest
        // portable approach is to use raw SQL guarded by driver.
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('user_goals', function (Blueprint $table) {
            $table->string('diet_objective', 20)->nullable()->after('main_goal');
        });

        // Allow creating a UserGoal for a brand-new user who hasn't completed
        // onboarding yet (e.g. when they first set alimentation goals).
        if ($driver === 'mysql') {
            Schema::getConnection()->statement(
                "ALTER TABLE user_goals MODIFY main_goal ENUM('weight_loss','hypertrophy','maintenance','health','conditioning') NULL"
            );
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't enforce NOT NULL retroactively in a simple way without
            // a table rebuild; tests rely on the controller passing a value or
            // the column already accepting null after recreation. The fresh
            // migration on :memory: already follows the new definition once the
            // base migration is updated to nullable() — see the b_create migration.
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('user_goals', function (Blueprint $table) {
            $table->dropColumn('diet_objective');
        });

        if ($driver === 'mysql') {
            Schema::getConnection()->statement(
                "ALTER TABLE user_goals MODIFY main_goal ENUM('weight_loss','hypertrophy','maintenance','health','conditioning') NOT NULL"
            );
        }
    }
};
