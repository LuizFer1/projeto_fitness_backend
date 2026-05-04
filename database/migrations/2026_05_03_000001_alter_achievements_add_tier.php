<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->enum('tier', ['bronze', 'silver', 'gold', 'platinum'])
                  ->default('bronze')
                  ->after('category');
        });

        // Backfill existing achievements to bronze (already default, but explicit)
        DB::table('achievements')->update(['tier' => 'bronze']);
    }

    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
