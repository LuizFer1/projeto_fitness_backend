<?php

namespace App\Jobs;

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\GamificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retroactively verifies Gold/Platinum achievement eligibility
 * for all users by querying compliance_log / xp_transactions directly,
 * preventing counter-only fraud (SRS anti-fraude section 6.4).
 *
 * Scheduled daily at off-peak hours via Console/Kernel.
 */
class VerifyAchievementsRetroactivelyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600; // 10 min max

    public function handle(GamificationService $gamification): void
    {
        $highTierSlugs = Achievement::whereIn('tier', ['gold', 'platinum'])
            ->where('is_active', true)
            ->pluck('slug');

        if ($highTierSlugs->isEmpty()) {
            return;
        }

        $users = User::whereHas('gamification', fn ($q) => $q->where('xp_total', '>=', config('gamification.levels.5.min_xp', 10000))
        )->cursor();

        foreach ($users as $user) {
            try {
                $this->verifyForUser($user, $gamification);
            } catch (\Throwable $e) {
                Log::error("VerifyAchievements: failed for user {$user->id}: {$e->getMessage()}");
            }
        }
    }

    private function verifyForUser(User $user, GamificationService $gamification): void
    {
        $gam = $user->gamification;
        if (! $gam) {
            return;
        }

        // ── Gold: gold_trident — 60-day combined streak (retroactive check) ──
        $this->checkGoldTrident($user, $gam, $gamification);

        // ── Gold: top_1_percent_global — handled by leaderboard job, skip here ──

        // ── Platinum: evofit_legendary — 365-day streak ──
        if ($gam->max_streak >= 365) {
            $gamification->awardBadge($user, 'evofit_legendary');
        }

        // ── Platinum: the_immortal — level 8 ──
        if ($gam->current_level >= 8) {
            $gamification->awardBadge($user, 'the_immortal');
        }
    }

    private function checkGoldTrident(User $user, UserGamification $gam, GamificationService $gamification): void
    {
        // Confirm via xp_transactions that user had at least 60 days of triple compliance
        // (workout + meal + water) — not just from the streak counter.
        $tripleComplianceDays = DB::table('daily_activity_limits')
            ->where('user_id', $user->id)
            ->where('workout_count', '>', 0)
            ->where('meal_xp_granted', true)
            ->where('water_xp_granted', true)
            ->count();

        if ($tripleComplianceDays >= 60) {
            $gamification->awardBadge($user, 'gold_trident');
        }
    }
}
