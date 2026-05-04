<?php

namespace App\Services\Assets;

use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class StrengthVictoryAssetService
{
    private const WIDTH  = 1080;
    private const HEIGHT = 1080;

    /**
     * Generate a 1080x1080 strength summary card and upload it to S3.
     * Returns the S3 key of the uploaded file.
     */
    public function generate(User $user, WorkoutLog $log): string
    {
        $manager = new ImageManager(new Driver());
        $image   = $manager->create(self::WIDTH, self::HEIGHT);

        // Background gradient (dark slate)
        $image->fill('#171717');

        $exercises = $log->workoutLogExercises()->with('exercise')->get();

        // Compute headline stats
        $totalVolume = $exercises->sum(fn ($e) => ($e->sets ?? 0) * ($e->reps ?? 0) * ($e->weight_kg ?? 0));
        $top3 = $exercises
            ->sortByDesc(fn ($e) => ($e->sets ?? 0) * ($e->reps ?? 0) * ($e->weight_kg ?? 0))
            ->take(3)
            ->map(fn ($e) => ($e->exercise?->name ?? 'Exercício') . ' · ' . $e->sets . 'x' . $e->reps . ' @ ' . $e->weight_kg . 'kg')
            ->values();

        $dateStr     = $log->date?->format('d/m/Y') ?? now()->format('d/m/Y');
        $durationStr = $log->duration_min ? $log->duration_min . ' min' : '';
        $caloriesStr = $log->calories_burned ? $log->calories_burned . ' kcal' : '';
        $volumeStr   = number_format($totalVolume, 0, ',', '.') . ' kg vol.';

        // Draw text blocks
        $image->text('TREINO CONCLUÍDO', self::WIDTH / 2, 160, function ($font) {
            $font->color('22c55e');
            $font->size(52);
            $font->align('center');
        });

        $image->text($dateStr, self::WIDTH / 2, 240, function ($font) {
            $font->color('94a3b8');
            $font->size(36);
            $font->align('center');
        });

        $image->text($volumeStr, self::WIDTH / 2, 360, function ($font) {
            $font->color('ffffff');
            $font->size(72);
            $font->align('center');
        });

        $image->text('Volume Total', self::WIDTH / 2, 440, function ($font) {
            $font->color('64748b');
            $font->size(32);
            $font->align('center');
        });

        // Stats row
        $statsY = 560;
        foreach ([$durationStr, $caloriesStr] as $i => $stat) {
            if ($stat) {
                $x = 270 + $i * 540;
                $image->text($stat, $x, $statsY, function ($font) {
                    $font->color('f59e0b');
                    $font->size(40);
                    $font->align('center');
                });
            }
        }

        // Top 3 exercises
        $image->text('Top exercícios', self::WIDTH / 2, 660, function ($font) {
            $font->color('64748b');
            $font->size(28);
            $font->align('center');
        });

        foreach ($top3 as $i => $line) {
            $image->text($line, self::WIDTH / 2, 710 + $i * 60, function ($font) {
                $font->color('e2e8f0');
                $font->size(30);
                $font->align('center');
            });
        }

        // Branding
        $image->text('EvoFit', self::WIDTH / 2, 1020, function ($font) {
            $font->color('22c55e');
            $font->size(28);
            $font->align('center');
        });

        $pngData = $image->toPng()->toString();
        $s3Key   = "victory-assets/strength/{$user->id}/{$log->id}.png";

        Storage::disk('s3')->put($s3Key, $pngData, 'public');

        return $s3Key;
    }
}
