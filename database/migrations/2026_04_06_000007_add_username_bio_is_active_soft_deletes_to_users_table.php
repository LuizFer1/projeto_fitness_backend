<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 40)->unique()->after('uuid');
            $table->text('bio')->nullable()->after('avatar_url');
            $table->boolean('is_active')->default(true)->after('bio');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'bio', 'is_active']);
            $table->dropSoftDeletes();
        });
    }
};
