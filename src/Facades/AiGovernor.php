<?php

namespace AiGovernor\Facades;

use AiGovernor\Contracts\AdapterResult;
use AiGovernor\Execution\GovernedExecutor;
use AiGovernor\Models\PromptVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static AdapterResult run(PromptVersion $prompt, array $variables, ?Model $owner = null, string $scope = 'global')
 *
 * @see GovernedExecutor
 */
class AiGovernor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GovernedExecutor::class;
    }
}
