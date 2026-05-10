<?php

namespace App\Jobs;

use App\Models\VictoryAsset;
use App\Models\WorkoutLog;
use App\Services\Assets\CardioVictoryAssetService;
use App\Services\Assets\StrengthVictoryAssetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateVictoryAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 2;

    public function __construct(
        private string $victoryAssetId,
        private string $workoutLogId
    ) {}

    public function handle(
        StrengthVictoryAssetService $strengthService,
        CardioVictoryAssetService $cardioService
    ): void {
        $asset = VictoryAsset::findOrFail($this->victoryAssetId);
        $log = WorkoutLog::findOrFail($this->workoutLogId);

        $asset->update(['status' => 'processing']);

        try {
            $isCardio = in_array($log->modality, ['cardio']) || ($log->distance_m > 0);
            $s3Key = $isCardio
                ? $cardioService->generate($asset->user, $log)
                : $strengthService->generate($asset->user, $log);

            $publicUrl = Storage::disk('s3')->url($s3Key);

            $asset->update([
                'status' => 'ready',
                's3_key' => $s3Key,
                'public_url' => $publicUrl,
                'generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $asset->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        }
    }
}
