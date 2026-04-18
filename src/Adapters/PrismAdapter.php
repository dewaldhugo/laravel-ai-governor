<?php

namespace AiGovernor\Adapters;

use AiGovernor\Contracts\AiProviderAdapter;
use AiGovernor\Exceptions\ProviderException;
use AiGovernor\Models\PromptVersion;
use AiGovernor\Values\AdapterResult;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\ValueObjects\Messages\UserMessage;
use EchoLabs\Prism\ValueObjects\Messages\SystemMessage;

class PrismAdapter implements AiProviderAdapter
{
    public function execute(PromptVersion $prompt, string $rendered): AdapterResult
    {
        $start = microtime(true);

        $provider = Provider::from(
            config('ai-governor.providers.prism.provider', 'anthropic')
        );

        $response = Prism::text()
            ->using($provider, $prompt->model)
            ->withSystemPrompt($prompt->system_prompt)
            ->withMessages([new UserMessage($rendered)])
            ->withMaxTokens($prompt->max_tokens)
            ->usingTemperature((float) $prompt->temperature)
            ->generate();

        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        if (empty($response->text)) {
            throw new ProviderException(
                message:  'Prism returned an empty response.',
                provider: 'prism',
                context:  [],
            );
        }

        return new AdapterResult(
            content:          $response->text,
            promptTokens:     $response->usage->promptTokens ?? 0,
            completionTokens: $response->usage->completionTokens ?? 0,
            latencyMs:        $latencyMs,
            model:            $prompt->model,
        );
    }
}
