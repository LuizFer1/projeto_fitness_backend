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
        if (! $this->needsIdempotencyCheck($request)) {
            return $next($request);
        }

        $key = $request->header(self::HEADER);
        if (! $key) {
            return $next($request);
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            return $this->errorResponse('IDEMPOTENCY_KEY_TOO_LONG', 'Idempotency-Key must be at most '.self::MAX_KEY_LENGTH.' characters.', 400);
        }

        $userId = $request->user()?->id;
        $requestHash = $this->hashRequest($request);

        $existing = $this->findExistingKey($key, $userId);
        if ($existing) {
            $replay = $this->handleExisting($existing, $requestHash);
            if ($replay !== null) {
                return $replay;
            }
        }

        $record = $this->createRecord($key, $userId, $request, $requestHash);
        if ($record === null) {
            return $this->errorResponse('IDEMPOTENCY_KEY_CONFLICT', 'Concurrent request with the same Idempotency-Key.', 409);
        }

        $response = $next($request);
        $this->persistResponse($record, $response);

        return $response;
    }

    private function needsIdempotencyCheck(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function findExistingKey(string $key, $userId): ?IdempotencyKey
    {
        return IdempotencyKey::where('key', $key)
            ->where(fn ($q) => $userId === null ? $q->whereNull('user_id') : $q->where('user_id', $userId))
            ->first();
    }

    private function handleExisting(IdempotencyKey $existing, string $requestHash): ?Response
    {
        if ($existing->expires_at->isPast()) {
            $existing->delete();
            return null;
        }

        if ($existing->request_hash !== $requestHash) {
            return $this->errorResponse('IDEMPOTENCY_KEY_CONFLICT', 'This Idempotency-Key was already used with a different request body.', 409);
        }

        if ($existing->status === IdempotencyKey::STATUS_PROCESSING) {
            return $this->errorResponse('IDEMPOTENT_REQUEST_IN_PROGRESS', 'A request with this Idempotency-Key is still being processed.', 409);
        }

        return response()->json($existing->response_body, $existing->response_status ?? 200)
            ->header('Idempotent-Replay', 'true');
    }

    private function createRecord(string $key, $userId, Request $request, string $requestHash): ?IdempotencyKey
    {
        try {
            return IdempotencyKey::create([
                'key'          => $key,
                'user_id'      => $userId,
                'method'       => $request->method(),
                'path'         => $request->path(),
                'request_hash' => $requestHash,
                'status'       => IdempotencyKey::STATUS_PROCESSING,
                'expires_at'   => CarbonImmutable::now()->addHours(self::TTL_HOURS),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function persistResponse(IdempotencyKey $record, Response $response): void
    {
        if ($response instanceof JsonResponse || $this->looksJson($response)) {
            $record->update([
                'status'          => IdempotencyKey::STATUS_COMPLETED,
                'response_status' => $response->getStatusCode(),
                'response_body'   => $this->extractBody($response),
            ]);
            return;
        }

        $record->delete();
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
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
