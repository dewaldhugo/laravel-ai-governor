<?php

namespace AiGovernor\Execution;

use AiGovernor\Budget\BudgetEnforcer;
use AiGovernor\Contracts\AiProviderAdapter;
use AiGovernor\Models\PromptVersion;
use AiGovernor\Values\AdapterResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class GovernedExecutor
{
    public function __construct(
        private readonly AiProviderAdapter $adapter,
        private readonly BudgetEnforcer    $enforcer,
    ) {}

    /**
     * Execute a governed AI prompt.
     *
     * Enforces token budget before calling the provider, records usage after.
     * Pass $owner to enable budget enforcement and usage tracking.
     * Pass $scope to bucket usage by feature (e.g. 'summarize', 'moderation').
     *
     * @param  PromptVersion         $prompt     Resolved versioned prompt.
     * @param  array<string, mixed>  $variables  Template variable substitutions.
     * @param  Model|null            $owner      Budget owner (User, Team, etc.).
     * @param  string                $scope      Usage scope for bucketing.
     *
     * @throws \AiGovernor\Exceptions\BudgetExceededException
     * @throws \AiGovernor\Exceptions\ProviderException
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function run(
        PromptVersion $prompt,
        array         $variables,
        ?Model        $owner = null,
        string        $scope = 'global',
    ): AdapterResult {
        // 1. Enforce budget ceiling before touching the provider.
        if ($owner) {
            $this->enforcer->checkOrFail($owner, $scope);
        }

        // 2. Render variable placeholders into the user template.
        $rendered = $prompt->render($variables);

        // 3. Delegate to the provider adapter.
        $result = $this->adapter->execute($prompt, $rendered);

        // 4. Record token usage against the owner.
        if ($owner) {
            $this->enforcer->record($owner, $result, $prompt, $scope);
        }

        Log::info('AiGovernor: execution complete', [
            'prompt'  => $prompt->name,
            'version' => $prompt->version,
            'model'   => $result->model,
            'tokens'  => $result->totalTokens(),
            'latency' => $result->latencyMs,
        ]);

        return $result;
    }
}
