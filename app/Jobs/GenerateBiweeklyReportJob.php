<?php

namespace App\Jobs;

use App\Models\BiweeklyReport;
use App\Models\NutritionDaily;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateBiweeklyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private string $reportId
    ) {}

    public function handle(): void
    {
        $report = BiweeklyReport::findOrFail($this->reportId);
        $user   = $report->user;

        $report->update(['status' => 'generating']);

        try {
            $summary = $this->buildSummary($user, $report->period_start, $report->period_end);

            // Full PDF rendering via browsershot/spatie would go here.
            // For MVP, we store a JSON summary and mark ready for the frontend to render.
            $report->update([
                'status'       => 'ready',
                'summary_data' => $summary,
                'generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $report->update([
                'status'        => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        }
    }

    private function buildSummary(User $user, $start, $end): array
    {
        $workouts = WorkoutLog::where('user_id', $user->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        $nutrition = NutritionDaily::where('user_id', $user->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        $photos = ProgressPhoto::where('user_id', $user->id)
            ->whereBetween('taken_at', [$start, $end])
            ->select(['id', 'taken_at', 'weight_kg', 'category'])
            ->get();

        return [
            'period_start'       => $start instanceof \DateTimeInterface ? $start->toDateString() : (string) $start,
            'period_end'         => $end instanceof \DateTimeInterface ? $end->toDateString() : (string) $end,
            'total_workouts'     => $workouts->count(),
            'total_calories_burned' => $workouts->sum('calories_burned'),
            'avg_daily_calories' => $nutrition->avg('calories_consumed') ? round($nutrition->avg('calories_consumed')) : null,
            'avg_protein_g'      => $nutrition->avg('protein_consumed_g') ? round($nutrition->avg('protein_consumed_g')) : null,
            'progress_photos'    => $photos->count(),
            'weight_start'       => $photos->sortBy('taken_at')->first()?->weight_kg,
            'weight_end'         => $photos->sortByDesc('taken_at')->first()?->weight_kg,
        ];
    }

    /**
     * Dispatch a biweekly report for all active users.
     * Called by the scheduler every 14 days.
     */
    public static function dispatchForAllUsers(): void
    {
        $end   = now()->subDay()->toDateString();
        $start = now()->subDays(14)->toDateString();

        User::where('is_active', true)->chunkById(100, function ($users) use ($start, $end) {
            foreach ($users as $user) {
                $alreadyExists = BiweeklyReport::where('user_id', $user->id)
                    ->where('period_start', $start)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                $report = BiweeklyReport::create([
                    'user_id'      => $user->id,
                    'period_start' => $start,
                    'period_end'   => $end,
                    'status'       => 'pending',
                ]);

                static::dispatch($report->id);
            }
        });
    }
}
