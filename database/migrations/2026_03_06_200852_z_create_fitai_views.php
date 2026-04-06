<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW vw_daily_summary AS
            SELECT
                u.id                                AS user_id,
                u.name                              AS name,
                CURDATE()                           AS date,
                COALESCE(dal.workout_count, 0)      AS workouts_today,
                COALESCE(dal.water_liters, 0)       AS water_today,
                COALESCE(dal.water_goal_reached, 0) AS water_goal_ok,
                COALESCE(dal.daily_xp_gained, 0)    AS xp_today,
                COALESCE(SUM(ml.calories_consumed), 0) AS calories_today,
                COALESCE(SUM(ml.protein_g), 0)      AS protein_today,
                COALESCE(SUM(ml.carbs_g), 0)        AS carbs_today,
                COALESCE(SUM(ml.fat_g), 0)          AS fat_today,
                ug.xp_total,
                ug.current_level,
                ug.current_streak
            FROM users u
            LEFT JOIN daily_activity_limits dal ON dal.user_id = u.id AND dal.date = CURDATE()
            LEFT JOIN meal_logs ml ON ml.user_id = u.id AND ml.date = CURDATE()
            LEFT JOIN user_gamification ug ON ug.user_id = u.id
            GROUP BY u.id, u.name, dal.workout_count, dal.water_liters, dal.water_goal_reached,
                     dal.daily_xp_gained, ug.xp_total, ug.current_level, ug.current_streak;
        ");

        DB::statement("
            CREATE OR REPLACE VIEW vw_ranking_geral AS
            SELECT
                ROW_NUMBER() OVER (ORDER BY ug.xp_total DESC) AS position,
                u.id       AS user_id,
                u.name     AS name,
                u.last_name,
                ug.xp_total,
                ug.current_level,
                ug.current_streak,
                ld.title   AS level_title,
                ld.icon    AS level_icon
            FROM user_gamification ug
            JOIN users u            ON u.id = ug.user_id
            LEFT JOIN level_definitions ld ON ld.level_num = ug.current_level
            ORDER BY ug.xp_total DESC;
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS vw_ranking_geral;");
        DB::statement("DROP VIEW IF EXISTS vw_daily_summary;");
    }
};
