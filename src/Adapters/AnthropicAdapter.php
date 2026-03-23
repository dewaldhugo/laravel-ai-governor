<?php

namespace AiGovernor\Adapters;

use AiGovernor\Contracts\AiProviderAdapter;
use AiGovernor\Exceptions\ProviderException;
use AiGovernor\Models\PromptVersion;
use AiGovernor\Values\AdapterResult;
use Illuminate\Support\Facades\Http;

class AnthropicAdapter implements AiProviderAdapter
{
    /**
     * HTTP status codes that indicate a transient failure safe to retry.
     * 429 = rate-limited, 529 = Anthropic overloaded, 500/502/503 = transient.
     */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 529];

    public function execute(PromptVersion $prompt, string $rendered): AdapterResult
    {
        $start = microtime(true);

        $response = Http::withHeaders([
                'x-api-key'         => config('ai-governor.providers.anthropic.key'),
                'anthropic-version' => config('ai-governor.providers.anthropic.version', '2023-06-01'),
            ])
            ->baseUrl('https://api.anthropic.com')
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
            ->post('/v1/messages', [
                'model'      => $prompt->model,
                'max_tokens' => $prompt->max_tokens,
                'system'     => $prompt->system_prompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $rendered],
                ],
            ]);

        $response->throw();

        $body      = $response->json();
        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        // Guard against a malformed response with no content blocks.
        if (empty($body['content'])) {
            throw new ProviderException(
                message:  'Anthropic returned a response with no content blocks.',
                provider: 'anthropic',
                context:  $body,
            );
        }

        // Extract text from the first text-type content block.
        $content = collect($body['content'])
            ->firstWhere('type', 'text')['text'] ?? '';

        return new AdapterResult(
            content:          $content,
            promptTokens:     $body['usage']['input_tokens'] ?? 0,
            completionTokens: $body['usage']['output_tokens'] ?? 0,
            latencyMs:        $latencyMs,
            model:            $body['model'] ?? $prompt->model,
        );
    }
}
