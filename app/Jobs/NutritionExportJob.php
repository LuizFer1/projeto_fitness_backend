<?php

namespace App\Jobs;

use App\Models\NutritionDaily;
use App\Models\NutritionExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class NutritionExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private string $exportId) {}

    public function handle(): void
    {
        $export = NutritionExport::findOrFail($this->exportId);
        $user = $export->user;

        $export->update(['status' => 'generating']);

        try {
            $rows = NutritionDaily::where('user_id', $user->id)
                ->whereBetween('date', [$export->date_from, $export->date_to])
                ->orderBy('date')
                ->get();

            $csv = $this->buildCsv($rows);
            $s3Key = "nutrition-exports/{$user->id}/{$export->id}.csv";

            Storage::disk('s3')->put($s3Key, $csv);

            $export->update([
                'status' => 'ready',
                's3_key' => $s3Key,
                'generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        }
    }

    private function buildCsv($rows): string
    {
        $headers = [
            'date', 'calories_goal', 'calories_consumed', 'protein_goal_g', 'protein_consumed_g',
            'carbs_goal_g', 'carbs_consumed_g', 'fat_goal_g', 'fat_consumed_g',
            'delta_kcal', 'dilution_active',
        ];

        $lines = [implode(',', $headers)];

        foreach ($rows as $row) {
            $lines[] = implode(',', [
                $row->date?->toDateString() ?? '',
                $row->calories_goal ?? '',
                $row->calories_consumed ?? '',
                $row->protein_goal_g ?? '',
                $row->protein_consumed_g ?? '',
                $row->carbs_goal_g ?? '',
                $row->carbs_consumed_g ?? '',
                $row->fat_goal_g ?? '',
                $row->fat_consumed_g ?? '',
                $row->delta_kcal ?? '',
                $row->dilution_active ? '1' : '0',
            ]);
        }

        return implode("\n", $lines);
    }
}
