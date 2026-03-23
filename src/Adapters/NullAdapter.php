<?php

namespace AiGovernor\Adapters;

use AiGovernor\Contracts\AiProviderAdapter;
use AiGovernor\Models\PromptVersion;
use AiGovernor\Values\AdapterResult;

/**
 * NullAdapter — use in tests and local development.
 *
 * Returns a deterministic, configurable response without making
 * any HTTP calls. Configure via the constructor or the static
 * factory and bind the instance in the service container to
 * avoid shared static state problems with parallel test runners.
 *
 * Usage in tests:
 *
 *   $this->app->instance(
 *       AiProviderAdapter::class,
 *       NullAdapter::make('Mocked summary output.'),
 *   );
 */
class NullAdapter implements AiProviderAdapter
{
    public function __construct(
        private readonly string $content      = 'Null adapter response.',
        private readonly int    $promptTokens = 10,
        private readonly int    $completionTokens = 20,
    ) {}

    /**
     * Static factory for fluent test setup.
     *
     * Bind the returned instance into the container rather than relying
     * on shared state:
     *
     *   $this->app->instance(AiProviderAdapter::class, NullAdapter::make('Hello'));
     */
    public static function make(
        string $content          = 'Null adapter response.',
        int    $promptTokens     = 10,
        int    $completionTokens = 20,
    ): self {
        return new self($content, $promptTokens, $completionTokens);
    }

    public function execute(PromptVersion $prompt, string $rendered): AdapterResult
    {
        return new AdapterResult(
            content:          $this->content,
            promptTokens:     $this->promptTokens,
            completionTokens: $this->completionTokens,
            latencyMs:        1,
            model:            $prompt->model,
        );
    }
}
