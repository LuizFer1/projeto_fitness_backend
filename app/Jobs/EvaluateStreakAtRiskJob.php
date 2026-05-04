<?php

namespace App\Jobs;

use App\Models\DailyActivityLimit;
use App\Models\NotificationPreference;
use App\Models\UserGamification;
use App\Notifications\StreakAtRiskNotification;
use App\Services\Notifications\QuietHoursPolicy;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates which users have an active streak but no activity today,
 * and dispatches push notifications (SRS RF-22).
 *
 * Scheduled hourly between 19h–22h (user's local time) via Console/Kernel.
 */
class EvaluateStreakAtRiskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function handle(): void
    {
        $policy = new QuietHoursPolicy();

        $usersAtRisk = UserGamification::where('current_streak', '>', 0)
            ->with('user')
            ->cursor();

        foreach ($usersAtRisk as $gam) {
            try {
                $this->evaluateUser($gam, $policy);
            } catch (\Throwable $e) {
                Log::error("EvaluateStreak: failed for user {$gam->user_id}: {$e->getMessage()}");
            }
        }
    }

    private function evaluateUser(UserGamification $gam, QuietHoursPolicy $policy): void
    {
        $user = $gam->user;
        if (! $user) {
            return;
        }

        $prefs = NotificationPreference::where('user_id', $user->id)->first();
        if ($prefs && ! $prefs->streak_at_risk) {
            return;
        }

        $userNow = Carbon::now($user->timezone ?? 'UTC');

        // Only fire between 19h–22h in user's local time
        if ($userNow->hour < 19 || $userNow->hour >= 22) {
            return;
        }

        if ($prefs && $policy->isQuiet($userNow, $prefs->quiet_hours_start, $prefs->quiet_hours_end)) {
            return;
        }

        $today = $userNow->toDateString();

        $hasActivityToday = DailyActivityLimit::where('user_id', $user->id)
            ->where('date', $today)
            ->where(fn ($q) => $q->where('workout_count', '>', 0)->orWhere('meal_xp_granted', true))
            ->exists();

        if ($hasActivityToday) {
            return;
        }

        $user->notify(new StreakAtRiskNotification($gam->current_streak));
    }
}
