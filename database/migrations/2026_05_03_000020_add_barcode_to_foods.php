<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->string('barcode_ean', 30)->nullable()->unique()->after('is_active')
                  ->comment('EAN-8, EAN-13, UPC-A, etc.');
            $table->string('source', 20)->default('manual')->after('barcode_ean')
                  ->comment('manual | taco | usda | openfoodfacts');
            $table->string('external_id')->nullable()->after('source')
                  ->comment('Original ID in external dataset (e.g. OpenFoodFacts code)');

            $table->index('barcode_ean');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropIndex(['barcode_ean']);
            $table->dropIndex(['source']);
            $table->dropColumn(['barcode_ean', 'source', 'external_id']);
        });
    }
};
