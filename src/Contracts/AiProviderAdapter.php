<?php

namespace AiGovernor\Contracts;

use AiGovernor\Models\PromptVersion;
use AiGovernor\Values\AdapterResult;

interface AiProviderAdapter
{
    /**
     * Execute a governed prompt against the AI provider.
     *
     * Implementations must return a normalised AdapterResult regardless of
     * the underlying provider's response shape. The GovernedExecutor depends
     * only on this contract — never on provider-specific response objects.
     *
     * @param  PromptVersion  $prompt   The resolved, versioned prompt definition.
     * @param  string         $rendered The fully rendered user message (variables substituted).
     *
     * @throws \Illuminate\Http\Client\RequestException           On provider HTTP errors.
     * @throws \AiGovernor\Exceptions\ProviderException           On provider-level semantic failures.
     */
    public function execute(PromptVersion $prompt, string $rendered): AdapterResult;
}
