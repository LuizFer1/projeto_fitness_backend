<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('description')
                ->comment('Demo video URL (YouTube embed or S3)');
            $table->string('thumbnail_url')->nullable()->after('video_url')
                ->comment('Thumbnail image URL');
            $table->uuid('parent_exercise_id')->nullable()->after('thumbnail_url')
                ->comment('Self-FK: null = base exercise, non-null = variation of parent');
            $table->json('body_zones')->nullable()->after('parent_exercise_id')
                ->comment('[{zone: "chest", type: "primary"}, {zone: "triceps", type: "secondary"}]');

            $table->foreign('parent_exercise_id')->references('id')->on('exercises')->nullOnDelete();
            $table->index('parent_exercise_id');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropForeign(['parent_exercise_id']);
            $table->dropIndex(['parent_exercise_id']);
            $table->dropColumn(['video_url', 'thumbnail_url', 'parent_exercise_id', 'body_zones']);
        });
    }
};
