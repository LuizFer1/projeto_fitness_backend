<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
        $startedAt = microtime(true);

        Log::shareContext([
            'request_id' => $requestId,
            'method'     => $request->method(),
            'path'       => $request->path(),
        ]);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::info('http_request', [
            'status'      => $response->getStatusCode(),
            'duration_ms' => $durationMs,
        ]);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
