<?php

namespace Tests\Feature\Gamification;

use App\Jobs\RecalculateLeaderboardJob;
use App\Models\User;
use App\Models\UserGamification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecalculateLeaderboardJobTest extends TestCase
{
    use RefreshDatabase;

    private function gamification(User $user, int $weekXp = 0, int $total = 0): void
    {
        UserGamification::create([
            'user_id' => $user->id,
            'xp_total' => $total,
            'current_level' => 1, 'xp_to_next' => 200,
            'current_streak' => 0, 'max_streak' => 0,
            'current_week_xp' => $weekXp,
            'current_month_xp' => 0,
            'total_workouts' => 0, 'total_water_days' => 0,
        ]);
    }

    public function test_job_writes_snapshot_with_positions(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();

        $this->gamification($u1, weekXp: 500);
        $this->gamification($u2, weekXp: 300);
        $this->gamification($u3, weekXp: 100);

        (new RecalculateLeaderboardJob('weekly'))->handle();

        $snap = DB::table('ranking_snapshots')->where('type', 'weekly')->get()->keyBy('user_id');

        $this->assertSame(1, (int) $snap[$u1->id]->position);
        $this->assertSame(2, (int) $snap[$u2->id]->position);
        $this->assertSame(3, (int) $snap[$u3->id]->position);
    }

    public function test_job_respects_ties_with_same_rank(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();

        $this->gamification($u1, weekXp: 500);
        $this->gamification($u2, weekXp: 500);
        $this->gamification($u3, weekXp: 100);

        (new RecalculateLeaderboardJob('weekly'))->handle();

        $snap = DB::table('ranking_snapshots')->where('type', 'weekly')->get()->keyBy('user_id');

        $this->assertSame(1, (int) $snap[$u1->id]->position);
        $this->assertSame(1, (int) $snap[$u2->id]->position);
        $this->assertSame(3, (int) $snap[$u3->id]->position);
    }
}
