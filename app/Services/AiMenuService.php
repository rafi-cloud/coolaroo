<?php

namespace App\Services;

use App\Exceptions\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 07.4, 07.9. GitHub Models' OpenAI-compatible chat completions endpoint.
 * This is the client only — building the menu/venue context (T121), the
 * prompt and structured output schema (T122), and the /ai/chat and
 * meal-builder endpoints that call chat() (T127, T128) are separate tasks.
 */
class AiMenuService
{
    /**
     * BR49: a missing key, a timeout, a 429, a 5xx, or any other failure all
     * surface to the caller as the same AiUnavailableException — the real
     * cause is logged to the `integrations` channel (07.11) here, once, so
     * nothing downstream needs to know GitHub Models' error shape.
     *
     * @param array<int, array{role:string, content:string}> $messages
     * @param array<string, mixed>|null $responseFormat Passed through as
     *     `response_format` (OpenAI-compatible structured output) — T122
     *     fills this in; optional here.
     * @return array<string, mixed> Decoded chat completion response body.
     */
    public function chat(array $messages, ?array $responseFormat = null): array
    {
        $apiKey = config('services.ai.api_key');

        if (empty($apiKey)) {
            Log::channel('integrations')->error('AI request skipped: AI_API_KEY not configured');

            throw new AiUnavailableException();
        }

        $payload = array_filter([
            'model' => config('services.ai.model'),
            'messages' => $messages,
            'response_format' => $responseFormat,
        ], fn ($value) => $value !== null);

        try {
            $response = Http::withToken($apiKey)
                ->baseUrl(config('services.ai.base_url'))
                ->timeout(config('services.ai.timeout'))
                ->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            Log::channel('integrations')->error('AI request timed out', ['message' => $e->getMessage()]);

            throw new AiUnavailableException();
        }

        if ($response->failed()) {
            Log::channel('integrations')->error('AI request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AiUnavailableException();
        }

        return $response->json();
    }
}
