<?php

namespace App\Jobs;

use App\Events\LeaderboardPositionChanged;
use App\Models\LeaderboardSnapshot;
use App\Models\User;
use App\Models\XpTransaction;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecalculateLeaderboardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $now = Carbon::now();

        $this->recalculate('weekly', $this->weeklyPeriodKey($now), $now, 3600);
        $this->recalculate('monthly', $now->format('Y-m'), $now, 3600);
        $this->recalculate('alltime', 'global', $now, 21600);
    }

    private function recalculate(string $period, string $periodKey, Carbon $now, int $ttl): void
    {
        $rankings = $this->computeRankings($period, $periodKey, $now);

        // Load previous top-10 ranks for comparison
        $previousTop10 = LeaderboardSnapshot::where('period', $period)
            ->where('period_key', $periodKey)
            ->where('rank', '<=', 10)
            ->pluck('rank', 'user_uuid')
            ->toArray();

        // Upsert snapshots
        foreach ($rankings as $index => $row) {
            $rank = $index + 1;

            LeaderboardSnapshot::updateOrCreate(
                [
                    'user_uuid' => $row->user_uuid,
                    'period' => $period,
                    'period_key' => $periodKey,
                ],
                [
                    'rank' => $rank,
                    'xp_points' => $row->total_xp,
                    'calculated_at' => $now,
                ],
            );
        }

        // Remove users no longer in top 100
        $rankedUuids = collect($rankings)->pluck('user_uuid')->toArray();
        if (!empty($rankedUuids)) {
            LeaderboardSnapshot::where('period', $period)
                ->where('period_key', $periodKey)
                ->whereNotIn('user_uuid', $rankedUuids)
                ->delete();
        }

        // Cache payload
        $payload = collect($rankings)->map(fn ($row, $index) => [
            'rank' => $index + 1,
            'user_uuid' => $row->user_uuid,
            'xp_points' => (int) $row->total_xp,
        ])->values()->toArray();

        Cache::put("leaderboard:{$period}:{$periodKey}", $payload, $ttl);

        // Dispatch LeaderboardPositionChanged for users newly entering top 10
        foreach ($rankings as $index => $row) {
            $newRank = $index + 1;
            if ($newRank > 10) {
                break;
            }

            $oldRank = $previousTop10[$row->user_uuid] ?? null;

            if ($oldRank === null || $oldRank > 10) {
                $user = User::where('uuid', $row->user_uuid)->first();
                if ($user) {
                    LeaderboardPositionChanged::dispatch($user, $oldRank, $newRank);
                }
            }
        }
    }

    private function computeRankings(string $period, string $periodKey, Carbon $now): array
    {
        if ($period === 'alltime') {
            return DB::table('users')
                ->select('uuid as user_uuid', 'xp_points as total_xp')
                ->where('xp_points', '>', 0)
                ->orderByDesc('xp_points')
                ->limit(100)
                ->get()
                ->toArray();
        }

        [$start, $end] = $this->periodBounds($period, $now);

        return DB::table('xp_transactions')
            ->select('user_uuid', DB::raw('SUM(amount) as total_xp'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('user_uuid')
            ->orderByDesc('total_xp')
            ->limit(100)
            ->get()
            ->toArray();
    }

    private function periodBounds(string $period, Carbon $now): array
    {
        if ($period === 'weekly') {
            return [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
            ];
        }

        return [
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
        ];
    }

    private function weeklyPeriodKey(Carbon $now): string
    {
        return $now->format('o-\\WW');
    }
}
