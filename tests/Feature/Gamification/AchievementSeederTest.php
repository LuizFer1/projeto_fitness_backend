<?php

namespace Tests\Feature\Gamification;

use App\Models\Achievement;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_14_achievements(): void
    {
        $this->seed(AchievementSeeder::class);

        $this->assertDatabaseCount('achievements', 14);
    }

    public function test_all_keys_are_unique(): void
    {
        $this->seed(AchievementSeeder::class);

        $keys = Achievement::pluck('key');
        $this->assertCount(14, $keys->unique());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(AchievementSeeder::class);
        $this->seed(AchievementSeeder::class);

        $this->assertDatabaseCount('achievements', 14);
    }

    public function test_required_keys_exist(): void
    {
        $this->seed(AchievementSeeder::class);

        $expectedKeys = [
            'first_goal_created',
            'first_checkin',
            'goal_completed_1',
            'goal_completed_5',
            'first_workout_done',
            'streak_3_days',
            'streak_7_days',
            'streak_30_days',
            'first_friend',
            'social_butterfly',
            'first_plan_generated',
            'plan_refined',
            'leaderboard_top_10',
            'leaderboard_top_1',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertDatabaseHas('achievements', ['key' => $key]);
        }
    }

    public function test_xp_rewards_match_spec(): void
    {
        $this->seed(AchievementSeeder::class);

        $expectedRewards = [
            'first_goal_created' => 10,
            'first_checkin' => 10,
            'goal_completed_1' => 50,
            'goal_completed_5' => 150,
            'first_workout_done' => 25,
            'streak_3_days' => 30,
            'streak_7_days' => 75,
            'streak_30_days' => 300,
            'first_friend' => 20,
            'social_butterfly' => 100,
            'first_plan_generated' => 30,
            'plan_refined' => 50,
            'leaderboard_top_10' => 100,
            'leaderboard_top_1' => 500,
        ];

        foreach ($expectedRewards as $key => $reward) {
            $this->assertDatabaseHas('achievements', [
                'key' => $key,
                'xp_reward' => $reward,
            ]);
        }
    }

    public function test_categories_are_correct(): void
    {
        $this->seed(AchievementSeeder::class);

        $expectedCategories = [
            'first_goal_created' => 'goals',
            'first_checkin' => 'goals',
            'goal_completed_1' => 'goals',
            'goal_completed_5' => 'goals',
            'first_workout_done' => 'milestone',
            'streak_3_days' => 'streak',
            'streak_7_days' => 'streak',
            'streak_30_days' => 'streak',
            'first_friend' => 'social',
            'social_butterfly' => 'social',
            'first_plan_generated' => 'milestone',
            'plan_refined' => 'milestone',
            'leaderboard_top_10' => 'leaderboard',
            'leaderboard_top_1' => 'leaderboard',
        ];

        foreach ($expectedCategories as $key => $category) {
            $this->assertDatabaseHas('achievements', [
                'key' => $key,
                'category' => $category,
            ]);
        }
    }

    public function test_all_achievements_have_title_and_description(): void
    {
        $this->seed(AchievementSeeder::class);

        $achievements = Achievement::all();

        foreach ($achievements as $achievement) {
            $this->assertNotEmpty($achievement->title, "Achievement {$achievement->key} has empty title");
            $this->assertNotEmpty($achievement->description, "Achievement {$achievement->key} has empty description");
        }
    }
}
