<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Exception;

class GroqService
{
    private $apiKey;
    private $baseUrl;
    private $defaultModel;
    private $fallbackModels;
    private $visionModel;
    private $verifySsl;
    private $timeoutSeconds;
    private $maxTokens;
    private $requireJsonResponse;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->baseUrl = rtrim(config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $this->defaultModel = config('services.groq.model', 'llama-3.1-8b-instant');
        $this->fallbackModels = config('services.groq.fallback_models', ['llama-3.1-8b-instant']);
        $this->visionModel = config('services.groq.vision_model', 'llama-3.2-11b-vision-preview');
        $this->verifySsl = filter_var(config('services.groq.verify_ssl', true), FILTER_VALIDATE_BOOLEAN);
        $this->timeoutSeconds = (int) config('services.groq.timeout_seconds', 20);
        $this->maxTokens = (int) config('services.groq.max_tokens', 1800);
        $this->requireJsonResponse = filter_var(config('services.groq.require_json_response', true), FILTER_VALIDATE_BOOLEAN);

        if (empty($this->apiKey)) {
            Log::warning('Groq API key is missing in config. AI features will be unavailable.');
        }
    }

    /**
     * Sends a text prompt to the Gemini API and expects a JSON response.
     */
    public function generateTextResponse(?string $model, string $prompt): ?array
    {
        if (empty($this->apiKey)) {
            throw new Exception('Groq API key is missing. Please check your .env file.');
        }
        $model = $model ?: $this->defaultModel;
        $messages = [
            [
                'role' => 'system',
                'content' => 'You must output a single valid JSON object only. Never output markdown, comments, or extra text. All textual values must be written in Brazilian Portuguese (pt-BR). Keep JSON keys exactly as requested.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        Log::info('Groq Request (Text):', [
            'model' => $model,
            'prompt_preview' => substr($prompt, 0, 100) . '...'
        ]);

        $response = $this->sendWithFallback($model, $messages, 'text');

        return $this->extractJsonFromResponse($response->json());
    }

    /**
     * Sends an image and a text prompt to the Gemini API and expects a JSON response.
     */
    public function generateVisionResponse(?string $model, string $prompt, string $imageBase64, string $mimeType = 'image/jpeg'): ?array
    {
        if (empty($this->apiKey)) {
            throw new Exception('Groq API key is missing. Please check your .env file.');
        }
        $model = $model ?: $this->visionModel;
        $messages = [
            [
                'role' => 'system',
                'content' => 'You must output a single valid JSON object only. Never output markdown, comments, or extra text. All textual values must be written in Brazilian Portuguese (pt-BR). Keep JSON keys exactly as requested.',
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]],
                ],
            ],
        ];

        Log::info('Groq Request (Vision):', [
            'model' => $model,
            'prompt_preview' => substr($prompt, 0, 100) . '...'
        ]);

        $response = $this->sendWithFallback($model, $messages, 'vision');

        return $this->extractJsonFromResponse($response->json());
    }

    private function sendWithFallback(string $preferredModel, array $messages, string $context)
    {
        $models = array_values(array_unique(array_filter(array_merge([$preferredModel], $this->fallbackModels))));
        $lastResponse = null;

        foreach ($models as $index => $model) {
            try {
                $response = $this->requestChatCompletion($model, $messages);
            } catch (ConnectionException $e) {
                Log::error('Groq connection error', [
                    'context' => $context,
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Could not connect to Groq API. Check internet access and SSL certificate configuration.');
            }

            if ($response->successful()) {
                return $response;
            }

            $lastResponse = $response;
            $status = $response->status();
            $body = $response->body();

            Log::warning('Groq API attempt failed', [
                'context' => $context,
                'model' => $model,
                'status' => $status,
                'attempt' => $index + 1,
                'body_preview' => substr($body, 0, 500),
            ]);

            $isLastModel = $index === (count($models) - 1);
            if ($isLastModel) {
                break;
            }
        }

        throw new Exception(
            sprintf(
                'Groq API request failed with status %s. Body: %s',
                $lastResponse?->status() ?? 'unknown',
                substr((string) $lastResponse?->body(), 0, 500) ?: 'empty response'
            )
        );
    }

    private function requestChatCompletion(string $model, array $messages)
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2,
            'max_tokens' => $this->maxTokens,
        ];

        if ($this->requireJsonResponse) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return Http::withToken($this->apiKey)
            ->connectTimeout(min($this->timeoutSeconds, 5))
            ->timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->withOptions(['verify' => $this->verifySsl])
            ->post("{$this->baseUrl}/chat/completions", $payload);
    }


    /**
     * Helpers to parse the Gemini JSON response down to the actual payload we care about.
     */
    private function extractJsonFromResponse(?array $decoded): ?array
    {
        $this->assertNotTruncated($decoded);
        $content = $this->extractContent($decoded);
        $textResponse = trim(str_replace(['```json', '```'], '', $content));

        $parsed = $this->parseJsonPayload($textResponse);
        if (!is_array($parsed)) {
            throw new Exception('Groq response is not valid JSON. Raw text: ' . substr($textResponse, 0, 500));
        }

        return $parsed;
    }

    private function assertNotTruncated(?array $decoded): void
    {
        if (($decoded['choices'][0]['finish_reason'] ?? null) === 'length') {
            throw new Exception('Groq response was truncated before completing JSON. Reduce prompt size or increase GROQ_MAX_TOKENS.');
        }
    }

    private function extractContent(?array $decoded): string
    {
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            Log::error('Unexpected Groq Response Structure', ['response' => $decoded]);
            throw new Exception('Could not parse response from Groq.');
        }

        return $content;
    }

    private function parseJsonPayload(string $text): ?array
    {
        $parsed = json_decode($text, true);
        if (is_array($parsed)) {
            return $parsed;
        }

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $parsed = json_decode($matches[0], true);
        }

        return is_array($parsed) ? $parsed : null;
    }
}
