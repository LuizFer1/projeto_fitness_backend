<?php

namespace App\Services\Ranking;

use App\Models\UserGamification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisRankingService
{
    public function weeklyKey(): string
    {
        return 'ranking:weekly:' . Carbon::now()->format('o-\WW');
    }

    public function monthlyKey(): string
    {
        return 'ranking:monthly:' . Carbon::now()->format('Y-m');
    }

    public function alltimeKey(): string
    {
        return 'ranking:alltime:global';
    }

    public function keyForPeriod(string $period): string
    {
        return match ($period) {
            'weekly'   => $this->weeklyKey(),
            'monthly'  => $this->monthlyKey(),
            default    => $this->alltimeKey(),
        };
    }

    /**
     * Increment a user's score in weekly, monthly, and alltime sets.
     * Silent on failure — DB is the source of truth.
     */
    public function incrementXp(string $userId, int $delta): void
    {
        if ($delta <= 0) {
            return;
        }

        try {
            $pipe = Redis::pipeline();
            $wk   = $this->weeklyKey();
            $mo   = $this->monthlyKey();
            $at   = $this->alltimeKey();

            $pipe->zincrby($wk, $delta, $userId);
            $pipe->zincrby($mo, $delta, $userId);
            $pipe->zincrby($at, $delta, $userId);
            // Refresh TTLs on each write so active keys never expire mid-period
            $pipe->expire($wk, 60 * 60 * 24 * 8);    // 8 days
            $pipe->expire($mo, 60 * 60 * 24 * 35);   // 35 days
            $pipe->execute();
        } catch (\Throwable) {
            // Redis unavailable — reads fall back to DB
        }
    }

    /**
     * Return top-N [{user_id, score}] for a given key, sorted highest first.
     * Returns empty array if Redis is unavailable.
     */
    public function topN(string $key, int $n = 100): array
    {
        try {
            $raw = Redis::zrevrange($key, 0, $n - 1, 'WITHSCORES');
            if (empty($raw)) {
                return [];
            }
            $result = [];
            foreach ($raw as $userId => $score) {
                $result[] = ['user_id' => (string) $userId, 'score' => (int) $score];
            }
            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Return the user's 1-indexed rank in a sorted set. Null if not found or Redis unavailable.
     */
    public function userRank(string $key, string $userId): ?int
    {
        try {
            $rank = Redis::zrevrank($key, $userId);
            return $rank !== null ? (int) $rank + 1 : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Return the user's score in a sorted set. Null if not found or Redis unavailable.
     */
    public function userScore(string $key, string $userId): ?int
    {
        try {
            $score = Redis::zscore($key, $userId);
            return $score !== null ? (int) $score : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Full rebuild of all three sorted sets from DB values.
     * Called by RecalculateLeaderboardJob as a nightly guardrail.
     */
    public function rebuild(): void
    {
        try {
            $rows = UserGamification::select(['user_id', 'xp_total', 'current_week_xp', 'current_month_xp'])->get();

            $wk = $this->weeklyKey();
            $mo = $this->monthlyKey();
            $at = $this->alltimeKey();

            $pipe = Redis::pipeline();
            $pipe->del($wk);
            $pipe->del($mo);
            $pipe->del($at);

            foreach ($rows as $row) {
                if ($row->current_week_xp  > 0) {
                    $pipe->zadd($wk, [$row->user_id => (int) $row->current_week_xp]);
                }
                if ($row->current_month_xp > 0) {
                    $pipe->zadd($mo, [$row->user_id => (int) $row->current_month_xp]);
                }
                if ($row->xp_total > 0) {
                    $pipe->zadd($at, [$row->user_id => (int) $row->xp_total]);
                }
            }

            $pipe->expire($wk, 60 * 60 * 24 * 8);
            $pipe->expire($mo, 60 * 60 * 24 * 35);
            $pipe->execute();
        } catch (\Throwable $e) {
            Log::warning('Redis ranking rebuild failed', ['error' => $e->getMessage()]);
        }
    }
}
