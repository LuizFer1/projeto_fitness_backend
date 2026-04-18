<?php

namespace App\Jobs;

use App\Models\UserGamification;
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

    public function __construct(private ?string $onlyType = null)
    {
    }

    public function handle(): void
    {
        $now = Carbon::now();
        $periods = [
            'weekly'   => ['column' => 'current_week_xp',  'ref' => $now->format('o-\WW')],
            'monthly'  => ['column' => 'current_month_xp', 'ref' => $now->format('Y-m')],
            'all_time' => ['column' => 'xp_total',         'ref' => 'all'],
        ];

        foreach ($periods as $type => $meta) {
            if ($this->onlyType && $this->onlyType !== $type) {
                continue;
            }
            $this->recalculateFor($type, $meta['column'], $meta['ref']);
            Cache::forget("leaderboard_{$type}_top20");
            Cache::forget("leaderboard_{$type}_top100");
        }
    }

    private function recalculateFor(string $type, string $column, string $refPeriod): void
    {
        $rows = UserGamification::select(['user_id', $column . ' as period_xp'])
            ->where($column, '>', 0)
            ->orderByDesc($column)
            ->get();

        $now = now();
        $position = 0;
        $previousXp = null;
        $tiedRank = 0;

        foreach ($rows as $index => $row) {
            $currentXp = (int) $row->period_xp;
            if ($currentXp !== $previousXp) {
                $position = $index + 1;
                $tiedRank = $position;
                $previousXp = $currentXp;
            }

            $existing = DB::table('ranking_snapshots')
                ->where('user_id', $row->user_id)
                ->where('type', $type)
                ->where('ref_period', $refPeriod)
                ->first();

            DB::table('ranking_snapshots')->updateOrInsert(
                [
                    'user_id'    => $row->user_id,
                    'type'       => $type,
                    'ref_period' => $refPeriod,
                ],
                [
                    'id'                 => $existing->id ?? (string) \Illuminate\Support\Str::uuid(),
                    'period_xp'          => $currentXp,
                    'position'           => $tiedRank,
                    'previous_position'  => $existing->position ?? null,
                    'updated_at'         => $now,
                ]
            );
        }
    }
}
