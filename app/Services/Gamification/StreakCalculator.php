<?php

namespace App\Services\Gamification;

use App\Models\User;
use App\Models\XpTransaction;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class StreakCalculator
{
    private const TIMEZONE = 'America/Sao_Paulo';

    private const ELIGIBLE_REASONS = [
        'login_daily',
        'workout_done',
        'goal_checkin',
    ];

    public static function currentStreak(User $user, ?Carbon $at = null): int
    {
        $now = $at
            ? CarbonImmutable::parse($at)->setTimezone(self::TIMEZONE)
            : CarbonImmutable::now(self::TIMEZONE);

        $today = $now->startOfDay();

        $dates = XpTransaction::where('user_uuid', $user->uuid)
            ->whereIn('reason', self::ELIGIBLE_REASONS)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($tx) => Carbon::parse($tx->created_at)->setTimezone(self::TIMEZONE)->format('Y-m-d'))
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $checkDate = $today;

        // If there's no activity today, start checking from yesterday
        if (! $dates->contains($checkDate->format('Y-m-d'))) {
            $checkDate = $today->subDay();
        }

        foreach (range(0, $dates->count()) as $i) {
            $dateStr = $checkDate->subDays($i)->format('Y-m-d');

            if ($dates->contains($dateStr)) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    public static function bonusFor(User $user, string $reason, Carbon $at): int
    {
        if (! in_array($reason, self::ELIGIBLE_REASONS, true)) {
            return 0;
        }

        $streakDay = self::currentStreak($user, $at);

        if ($streakDay <= 1) {
            return 0;
        }

        return min($streakDay - 1, 10) * 5;
    }
}
