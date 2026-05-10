<?php

namespace App\Jobs;

use App\Models\ExternalActivity;
use App\Models\ExternalOauthToken;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Services\GamificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncGoogleFitActivitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private User $user) {}

    public function handle(GamificationService $gamification): void
    {
        $tokenRecord = ExternalOauthToken::where('user_id', $this->user->id)
            ->where('provider', 'googlefit')
            ->first();

        if (! $tokenRecord) {
            return;
        }

        if ($tokenRecord->isExpired()) {
            $this->refreshToken($tokenRecord);
            $tokenRecord->refresh();
        }

        $startTimeMs = now()->subDays(7)->timestamp * 1000;
        $endTimeMs = now()->timestamp * 1000;

        $response = Http::timeout(5)
            ->withToken($tokenRecord->access_token)
            ->post('https://www.googleapis.com/fitness/v1/users/me/dataset:aggregate', [
                'aggregateBy' => [['dataTypeName' => 'com.google.activity.segment']],
                'bucketByTime' => ['durationMillis' => 86400000],
                'startTimeMillis' => $startTimeMs,
                'endTimeMillis' => $endTimeMs,
            ]);

        if (! $response->successful()) {
            Log::warning("SyncGoogleFit: API error for user {$this->user->id}: ".$response->body());

            return;
        }

        $buckets = $response->json('bucket', []);
        $synced = 0;

        foreach ($buckets as $bucket) {
            foreach ($bucket['dataset'] ?? [] as $dataset) {
                foreach ($dataset['point'] ?? [] as $point) {
                    $externalId = $point['originDataSourceId'].'_'.$point['startTimeNanos'];

                    $exists = ExternalActivity::where('provider', 'googlefit')
                        ->where('provider_activity_id', $externalId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::transaction(function () use ($point, $externalId, $gamification, &$synced) {
                        ExternalActivity::create([
                            'user_id' => $this->user->id,
                            'provider' => 'googlefit',
                            'provider_activity_id' => $externalId,
                            'payload_json' => $point,
                        ]);

                        $startSec = (int) ($point['startTimeNanos'] / 1e9);
                        $endSec = (int) ($point['endTimeNanos'] / 1e9);
                        $durationMin = (int) (($endSec - $startSec) / 60);

                        $log = WorkoutLog::create([
                            'user_id' => $this->user->id,
                            'date' => date('Y-m-d', $startSec),
                            'modality' => 'cardio',
                            'duration_min' => $durationMin,
                            'external_source' => 'googlefit',
                            'external_id' => $externalId,
                            'mood' => 'neutral',
                        ]);

                        $gamification->grantCardioCompletedXp($this->user, $log->id);
                        $gamification->checkWorkoutBadges($this->user);
                        $synced++;
                    });
                }
            }
        }

        Log::info("SyncGoogleFit: {$synced} activities synced for user {$this->user->id}");
    }

    private function refreshToken(ExternalOauthToken $token): void
    {
        if (! $token->refresh_token) {
            return;
        }

        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.googlefit.client_id'),
            'client_secret' => config('services.googlefit.client_secret'),
            'refresh_token' => $token->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->successful()) {
            $token->update([
                'access_token' => $response->json('access_token'),
                'expires_at' => now()->addSeconds($response->json('expires_in', 3600)),
            ]);
        }
    }
}
