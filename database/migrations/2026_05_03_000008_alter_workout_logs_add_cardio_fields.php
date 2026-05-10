<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_logs', function (Blueprint $table) {
            $table->enum('modality', ['strength', 'cardio', 'mixed', 'mobility'])
                ->default('strength')->after('date');

            // Cardio metrics
            $table->unsignedInteger('distance_m')->nullable()->comment('Total distance in metres');
            $table->unsignedSmallInteger('pace_seconds_per_km')->nullable()->comment('Average pace in seconds/km');
            $table->unsignedSmallInteger('avg_hr')->nullable()->comment('Average heart rate bpm');
            $table->unsignedSmallInteger('max_hr')->nullable()->comment('Max heart rate bpm');
            $table->smallInteger('elevation_gain_m')->nullable()->comment('Elevation gain in metres');

            // Route data (encoded polyline stored in DB; full GeoJSON in S3)
            $table->mediumText('route_polyline')->nullable()->comment('Google encoded polyline');
            $table->string('route_geojson_path', 500)->nullable()->comment('S3 key for full GeoJSON');

            // External sync dedup
            $table->enum('external_source', ['manual', 'healthkit', 'googlefit', 'garmin'])
                ->default('manual')->after('route_geojson_path');
            $table->string('external_id', 120)->nullable()->after('external_source');
        });

        // Unique constraint for external dedup — only when external_id is set.
        // Partial unique indexes are not supported by all SQLite versions in Laravel,
        // so we use a composite nullable unique that's enforced at the app layer for SQLite.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE workout_logs ADD UNIQUE KEY uq_external_activity (external_source, external_id)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE workout_logs DROP INDEX uq_external_activity');
        }

        Schema::table('workout_logs', function (Blueprint $table) {
            $table->dropColumn([
                'modality', 'distance_m', 'pace_seconds_per_km', 'avg_hr', 'max_hr',
                'elevation_gain_m', 'route_polyline', 'route_geojson_path',
                'external_source', 'external_id',
            ]);
        });
    }
};
