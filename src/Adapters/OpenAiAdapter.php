<?php

namespace AiGovernor\Adapters;

use AiGovernor\Contracts\AiProviderAdapter;
use AiGovernor\Exceptions\ProviderException;
use AiGovernor\Models\PromptVersion;
use AiGovernor\Values\AdapterResult;
use Illuminate\Support\Facades\Http;

class OpenAiAdapter implements AiProviderAdapter
{
    /**
     * HTTP status codes that indicate a transient failure safe to retry.
     * 429 = rate-limited, 500/502/503 = provider-side transient errors.
     */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503];

    public function execute(PromptVersion $prompt, string $rendered): AdapterResult
    {
        $start = microtime(true);

        $response = Http::withToken(config('ai-governor.providers.openai.key'))
            ->baseUrl(config('ai-governor.providers.openai.url', 'https://api.openai.com/v1'))
            ->retry(
                times: 3,
                sleepMilliseconds: 500,
                when: fn ($exception) => in_array(
                    $exception->response?->status(),
                    self::RETRYABLE_STATUSES,
                    strict: true,
                ),
            )
            ->timeout(config('ai-governor.timeout', 30))
            ->post('/chat/completions', [
                'model'       => $prompt->model,
                'temperature' => (float) $prompt->temperature,
                'max_tokens'  => $prompt->max_tokens,
                'messages'    => [
                    ['role' => 'system', 'content' => $prompt->system_prompt],
                    ['role' => 'user',   'content' => $rendered],
                ],
            ]);

        $response->throw();

        $body      = $response->json();
        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        // Guard against a malformed response that has no choices.
        if (empty($body['choices'])) {
            throw new ProviderException(
                message:  'OpenAI returned a response with no choices.',
                provider: 'openai',
                context:  $body,
            );
        }

        return new AdapterResult(
            content:          $body['choices'][0]['message']['content'] ?? '',
            promptTokens:     $body['usage']['prompt_tokens'] ?? 0,
            completionTokens: $body['usage']['completion_tokens'] ?? 0,
            latencyMs:        $latencyMs,
            model:            $body['model'] ?? $prompt->model,
        );
    }
}
