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

/**
 * Syncs activities from Garmin Connect Activity API.
 *
 * Garmin uses OAuth1.0a; token exchange is handled in IntegrationController
 * (future: add Garmin OAuth flow). This job reads from the stored token.
 */
class SyncGarminActivitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(private User $user) {}

    public function handle(GamificationService $gamification): void
    {
        $tokenRecord = ExternalOauthToken::where('user_id', $this->user->id)
            ->where('provider', 'garmin')
            ->first();

        if (!$tokenRecord) {
            return;
        }

        // Garmin Connect Activity Summary endpoint
        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate   = now()->format('Y-m-d');

        $response = Http::timeout(5)
            ->withHeaders(['Authorization' => 'Bearer ' . $tokenRecord->access_token])
            ->get('https://apis.garmin.com/wellness-api/rest/activities', [
                'uploadStartTimeInSeconds' => now()->subDays(7)->timestamp,
                'uploadEndTimeInSeconds'   => now()->timestamp,
            ]);

        if (!$response->successful()) {
            Log::warning("SyncGarmin: API error for user {$this->user->id}: " . $response->body());
            return;
        }

        $activities = $response->json('activityDetails', $response->json([], []));
        $synced     = 0;

        foreach ($activities as $activity) {
            $externalId = (string) ($activity['activityId'] ?? $activity['summaryId'] ?? null);

            if (!$externalId) {
                continue;
            }

            $exists = ExternalActivity::where('provider', 'garmin')
                ->where('provider_activity_id', $externalId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::transaction(function () use ($activity, $externalId, $gamification, &$synced) {
                ExternalActivity::create([
                    'user_id'              => $this->user->id,
                    'provider'             => 'garmin',
                    'provider_activity_id' => $externalId,
                    'payload_json'         => $activity,
                ]);

                $activityType = strtolower($activity['activityType'] ?? 'cardio');
                $modality     = str_contains($activityType, 'cycling') || str_contains($activityType, 'running')
                    ? 'cardio' : 'strength';

                $log = WorkoutLog::create([
                    'user_id'             => $this->user->id,
                    'date'                => date('Y-m-d', $activity['startTimeInSeconds'] ?? time()),
                    'modality'            => $modality,
                    'duration_min'        => isset($activity['durationInSeconds']) ? (int) ($activity['durationInSeconds'] / 60) : null,
                    'calories_burned'     => $activity['activeKilocalories'] ?? null,
                    'distance_m'          => isset($activity['distanceInMeters']) ? (int) $activity['distanceInMeters'] : null,
                    'avg_hr'              => $activity['averageHeartRateInBeatsPerMinute'] ?? null,
                    'max_hr'              => $activity['maxHeartRateInBeatsPerMinute'] ?? null,
                    'elevation_gain_m'    => $activity['totalElevationGainInMeters'] ?? null,
                    'external_source'     => 'garmin',
                    'external_id'         => $externalId,
                    'mood'                => 'neutral',
                ]);

                if ($modality === 'cardio') {
                    $gamification->grantCardioCompletedXp($this->user, $log->id);
                } else {
                    $gamification->grantWorkoutCompletedXp($this->user, $log->id);
                }

                $gamification->checkWorkoutBadges($this->user);
                $synced++;
            });
        }

        Log::info("SyncGarmin: {$synced} activities synced for user {$this->user->id}");
    }
}
