<?php

namespace App\Services\Assets;

use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class CardioVictoryAssetService
{
    private const WIDTH = 1080;

    private const HEIGHT = 1080;

    /**
     * Generate a cardio summary card with optional Mapbox route map and upload to S3.
     * Returns the S3 key of the uploaded file.
     */
    public function generate(User $user, WorkoutLog $log): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->create(self::WIDTH, self::HEIGHT);
        $image->fill('#0f172a');

        $mapImage = $this->fetchMapImage($log);
        if ($mapImage) {
            // Place the map in the upper two-thirds of the card
            $map = $manager->read($mapImage);
            $map->scale(self::WIDTH, 680);
            $image->place($map, 'top-left', 0, 0);
            // Darken map overlay for text readability
            $image->colorize(-30, -30, -30);
        }

        $paceStr = $this->formatPace($log->pace_seconds_per_km);
        $distStr = $log->distance_m ? number_format($log->distance_m / 1000, 2, ',', '.').' km' : '';
        $elevStr = $log->elevation_gain_m ? '↑ '.$log->elevation_gain_m.' m' : '';
        $hrStr = $log->avg_hr ? $log->avg_hr.' bpm' : '';
        $dateStr = $log->date?->format('d/m/Y') ?? now()->format('d/m/Y');

        $image->text('CÁRDIO CONCLUÍDO', self::WIDTH / 2, 730, function ($font) {
            $font->color('22c55e');
            $font->size(48);
            $font->align('center');
        });

        $image->text($dateStr, self::WIDTH / 2, 790, function ($font) {
            $font->color('94a3b8');
            $font->size(32);
            $font->align('center');
        });

        if ($distStr) {
            $image->text($distStr, self::WIDTH / 2, 880, function ($font) {
                $font->color('ffffff');
                $font->size(80);
                $font->align('center');
            });
        }

        $statsY = 960;
        $statParts = array_filter([$paceStr, $hrStr, $elevStr]);
        $statStr = implode('  ·  ', $statParts);
        if ($statStr) {
            $image->text($statStr, self::WIDTH / 2, $statsY, function ($font) {
                $font->color('f59e0b');
                $font->size(34);
                $font->align('center');
            });
        }

        $image->text('EvoFit', self::WIDTH / 2, 1055, function ($font) {
            $font->color('22c55e');
            $font->size(26);
            $font->align('center');
        });

        $pngData = $image->toPng()->toString();
        $s3Key = "victory-assets/cardio/{$user->id}/{$log->id}.png";

        Storage::disk('s3')->put($s3Key, $pngData, 'public');

        return $s3Key;
    }

    private function fetchMapImage(WorkoutLog $log): ?string
    {
        $polyline = $log->route_polyline;
        $token = config('services.mapbox.token');

        if (! $polyline || ! $token) {
            return null;
        }

        try {
            $encoded = rawurlencode($polyline);
            $url = "https://api.mapbox.com/styles/v1/mapbox/dark-v11/static/path-3+22c55e-0.8({$encoded})/auto/1080x680@2x?access_token={$token}";
            $response = Http::timeout(8)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatPace(?int $secondsPerKm): string
    {
        if (! $secondsPerKm) {
            return '';
        }
        $min = intdiv($secondsPerKm, 60);
        $sec = $secondsPerKm % 60;

        return sprintf("%d'%02d\"/km", $min, $sec);
    }
}
