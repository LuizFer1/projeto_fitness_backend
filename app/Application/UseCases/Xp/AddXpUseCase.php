<?php

namespace App\Application\UseCases\Xp;

use App\Events\LevelChanged;
use App\Models\User;
use App\Models\XpTransaction;
use App\Services\Gamification\LevelCalculator;
use App\Services\Gamification\StreakCalculator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class AddXpUseCase
{
    private const STREAK_ELIGIBLE_REASONS = [
        'login_daily',
        'workout_done',
        'goal_checkin',
    ];

    public function execute(
        User $user,
        int $baseAmount,
        string $reason,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?Carbon $occurredAt = null,
    ): ?XpTransaction {
        $occurredAt = $occurredAt ?? Carbon::now();

        $streakBonus = in_array($reason, self::STREAK_ELIGIBLE_REASONS, true)
            ? StreakCalculator::bonusFor($user, $reason, $occurredAt)
            : 0;

        $total = $baseAmount + $streakBonus;

        try {
            $transaction = XpTransaction::create([
                'user_uuid' => $user->uuid,
                'amount' => $total,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'meta' => [
                    'base_amount' => $baseAmount,
                    'streak_bonus' => $streakBonus,
                    'streak_day' => $streakBonus > 0
                        ? StreakCalculator::currentStreak($user, $occurredAt)
                        : 0,
                ],
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed') || str_contains($e->getMessage(), '23000')) {
                return null;
            }
            throw $e;
        }

        $user->increment('xp_points', $total);
        $user->refresh();

        $oldLevel = $user->level;
        $newLevel = LevelCalculator::forXp($user->xp_points);

        if ($newLevel !== $oldLevel) {
            $user->update(['level' => $newLevel]);
            LevelChanged::dispatch($user, $oldLevel, $newLevel);
        }

        return $transaction;
    }
}
