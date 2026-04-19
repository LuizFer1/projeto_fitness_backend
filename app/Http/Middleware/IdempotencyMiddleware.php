<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    private const HEADER = 'Idempotency-Key';
    private const TTL_HOURS = 24;
    private const MAX_KEY_LENGTH = 100;

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = $request->header(self::HEADER);
        if (! $key) {
            return $next($request);
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            return response()->json([
                'error' => [
                    'code' => 'IDEMPOTENCY_KEY_TOO_LONG',
                    'message' => 'Idempotency-Key must be at most '.self::MAX_KEY_LENGTH.' characters.',
                ],
            ], 400);
        }

        $userId = $request->user()?->id;
        $requestHash = $this->hashRequest($request);
        $now = CarbonImmutable::now();

        $existing = IdempotencyKey::where('key', $key)
            ->where(function ($q) use ($userId) {
                $userId === null ? $q->whereNull('user_id') : $q->where('user_id', $userId);
            })
            ->first();

        if ($existing) {
            if ($existing->expires_at->isPast()) {
                $existing->delete();
            } elseif ($existing->request_hash !== $requestHash) {
                return response()->json([
                    'error' => [
                        'code' => 'IDEMPOTENCY_KEY_CONFLICT',
                        'message' => 'This Idempotency-Key was already used with a different request body.',
                    ],
                ], 409);
            } elseif ($existing->status === IdempotencyKey::STATUS_PROCESSING) {
                return response()->json([
                    'error' => [
                        'code' => 'IDEMPOTENT_REQUEST_IN_PROGRESS',
                        'message' => 'A request with this Idempotency-Key is still being processed.',
                    ],
                ], 409);
            } else {
                return response()->json(
                    $existing->response_body,
                    $existing->response_status ?? 200
                )->header('Idempotent-Replay', 'true');
            }
        }

        try {
            $record = IdempotencyKey::create([
                'key'          => $key,
                'user_id'      => $userId,
                'method'       => $request->method(),
                'path'         => $request->path(),
                'request_hash' => $requestHash,
                'status'       => IdempotencyKey::STATUS_PROCESSING,
                'expires_at'   => $now->addHours(self::TTL_HOURS),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => [
                    'code' => 'IDEMPOTENCY_KEY_CONFLICT',
                    'message' => 'Concurrent request with the same Idempotency-Key.',
                ],
            ], 409);
        }

        $response = $next($request);

        if ($response instanceof JsonResponse || $this->looksJson($response)) {
            $record->update([
                'status'          => IdempotencyKey::STATUS_COMPLETED,
                'response_status' => $response->getStatusCode(),
                'response_body'   => $this->extractBody($response),
            ]);
        } else {
            $record->delete();
        }

        return $response;
    }

    private function hashRequest(Request $request): string
    {
        $payload = [
            'method' => $request->method(),
            'path'   => $request->path(),
            'body'   => $request->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function looksJson(Response $response): bool
    {
        $ct = $response->headers->get('Content-Type', '');
        return str_contains($ct, 'application/json');
    }

    private function extractBody(Response $response): ?array
    {
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
