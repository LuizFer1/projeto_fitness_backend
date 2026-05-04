<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGarminActivitiesJob;
use App\Jobs\SyncGoogleFitActivitiesJob;
use App\Models\ExternalActivity;
use App\Models\ExternalOauthToken;
use App\Models\WorkoutLog;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegrationController extends Controller
{
    public function __construct(private GamificationService $gamification) {}

    /**
     * GET /v1/integrations
     *
     * Returns connection status for each external provider.
     */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $tokens  = ExternalOauthToken::where('user_id', $user->id)->get()->keyBy('provider');
        $providers = ['healthkit', 'googlefit', 'garmin'];

        $status = collect($providers)->map(fn ($p) => [
            'provider'   => $p,
            'connected'  => $tokens->has($p),
            'expired'    => $tokens->has($p) && $tokens[$p]->isExpired(),
            'expires_at' => $tokens->get($p)?->expires_at,
        ]);

        return response()->json(['integrations' => $status]);
    }

    /**
     * POST /v1/integrations/healthkit/sync
     *
     * Accepts a batch of HealthKit activities from the mobile app.
     * The mobile app handles the HealthKit permission flow; we just receive the data.
     * Each activity is deduped by provider_activity_id (RNF-08).
     */
    public function syncHealthKit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activities'                        => 'required|array|max:100',
            'activities.*.external_id'          => 'required|string|max:200',
            'activities.*.date'                 => 'required|date',
            'activities.*.modality'             => 'nullable|in:strength,cardio,mixed,mobility',
            'activities.*.duration_min'         => 'nullable|integer|min:1',
            'activities.*.calories_burned'      => 'nullable|numeric|min:0',
            'activities.*.distance_m'           => 'nullable|integer|min:0',
            'activities.*.avg_hr'               => 'nullable|integer|min:30|max:300',
            'activities.*.route_polyline'       => 'nullable|string',
        ]);

        $user    = $request->user();
        $synced  = 0;
        $skipped = 0;

        foreach ($validated['activities'] as $activity) {
            $externalId = $activity['external_id'];

            // Idempotency check
            $existing = ExternalActivity::where('provider', 'healthkit')
                ->where('provider_activity_id', $externalId)
                ->first();

            if ($existing) {
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($user, $activity, $externalId, &$synced) {
                ExternalActivity::create([
                    'user_id'              => $user->id,
                    'provider'             => 'healthkit',
                    'provider_activity_id' => $externalId,
                    'payload_json'         => $activity,
                ]);

                $log = WorkoutLog::create([
                    'user_id'         => $user->id,
                    'date'            => $activity['date'],
                    'modality'        => $activity['modality'] ?? 'cardio',
                    'duration_min'    => $activity['duration_min'] ?? null,
                    'calories_burned' => $activity['calories_burned'] ?? null,
                    'distance_m'      => $activity['distance_m'] ?? null,
                    'avg_hr'          => $activity['avg_hr'] ?? null,
                    'route_polyline'  => $activity['route_polyline'] ?? null,
                    'external_source' => 'healthkit',
                    'external_id'     => $externalId,
                    'mood'            => 'neutral',
                ]);

                $modality = $activity['modality'] ?? 'cardio';
                if ($modality === 'cardio') {
                    $this->gamification->grantCardioCompletedXp($user, $log->id);
                } else {
                    $this->gamification->grantWorkoutCompletedXp($user, $log->id);
                }

                $this->gamification->checkWorkoutBadges($user);
                $synced++;
            });
        }

        return response()->json([
            'message' => "Sync concluído: {$synced} atividades importadas, {$skipped} ignoradas (duplicatas).",
            'synced'  => $synced,
            'skipped' => $skipped,
        ]);
    }

    /**
     * GET /v1/integrations/googlefit/authorize
     *
     * Returns the Google OAuth2 authorization URL.
     * The app opens this URL in a browser/webview.
     */
    public function authorizeGoogleFit(Request $request): JsonResponse
    {
        $clientId    = config('services.googlefit.client_id');
        $redirectUri = config('services.googlefit.redirect_uri');
        $scopes      = 'https://www.googleapis.com/auth/fitness.activity.read https://www.googleapis.com/auth/fitness.heart_rate.read';

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $scopes,
            'access_type'   => 'offline',
            'state'         => $request->user()->id,
        ]);

        return response()->json(['authorization_url' => $url]);
    }

    /**
     * GET /v1/integrations/googlefit/callback
     *
     * Exchanges authorization code for tokens and saves them.
     */
    public function callbackGoogleFit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'  => 'required|string',
            'state' => 'required|string',
        ]);

        $tokenResponse = $this->exchangeGoogleCode($validated['code']);

        ExternalOauthToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'provider' => 'googlefit'],
            [
                'access_token'  => $tokenResponse['access_token'],
                'refresh_token' => $tokenResponse['refresh_token'] ?? null,
                'expires_at'    => now()->addSeconds($tokenResponse['expires_in'] ?? 3600),
                'scopes'        => explode(' ', $tokenResponse['scope'] ?? ''),
            ]
        );

        SyncGoogleFitActivitiesJob::dispatch($request->user());

        return response()->json(['message' => 'Google Fit conectado com sucesso.']);
    }

    /**
     * DELETE /v1/integrations/{provider}
     *
     * Revokes and removes the OAuth token for a provider.
     */
    public function disconnect(Request $request, string $provider): JsonResponse
    {
        $allowed = ['healthkit', 'googlefit', 'garmin'];
        if (!in_array($provider, $allowed)) {
            return response()->json(['message' => 'Provedor inválido.'], 422);
        }

        ExternalOauthToken::where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->delete();

        return response()->json(['message' => ucfirst($provider) . ' desconectado.']);
    }

    private function exchangeGoogleCode(string $code): array
    {
        $response = \Http::post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => config('services.googlefit.client_id'),
            'client_secret' => config('services.googlefit.client_secret'),
            'redirect_uri'  => config('services.googlefit.redirect_uri'),
            'grant_type'    => 'authorization_code',
        ]);

        return $response->json();
    }
}
