<?php

namespace App\Infrastructure\Logging;

use App\Application\Contracts\LoggerInterface;
use Illuminate\Support\Facades\Log;

class AppLogger implements LoggerInterface
{
    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, $context);
        
        // Em um cenário real com Sentry instalado via composer:
        // Se a lib Sentry estiver disponível:
        if (function_exists('app') && app()->bound('sentry')) {
            app('sentry')->captureMessage($message, [], $context);
        }
    }

    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, $context);

        // Se a lib Sentry estiver disponível:
        if (function_exists('app') && app()->bound('sentry')) {
            app('sentry')->captureMessage($message, [], $context);
        }
    }
}
