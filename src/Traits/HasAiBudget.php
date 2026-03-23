<?php

namespace AiGovernor\Traits;

use AiGovernor\Budget\BudgetPeriod;
use AiGovernor\Models\TokenBudget;
use AiGovernor\Models\TokenUsage;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

trait HasAiBudget
{
    public function aiBudgets(): MorphMany
    {
        return $this->morphMany(TokenBudget::class, 'owner');
    }

    public function aiUsage(): MorphMany
    {
        return $this->morphMany(TokenUsage::class, 'owner');
    }

    /**
     * Set or update a token budget for this owner.
     *
     * @param  int     $limit   Maximum tokens allowed in the period.
     * @param  string  $period  'daily' | 'monthly'
     * @param  string  $scope   Feature scope, e.g. 'summarize', 'global'.
     * @param  bool    $hard    True = throw on breach. False = warn only.
     */
    public function setAiBudget(
        int    $limit,
        string $period = 'monthly',
        string $scope  = 'global',
        bool   $hard   = true,
    ): TokenBudget {
        return $this->aiBudgets()->updateOrCreate(
            ['scope' => $scope, 'period' => $period],
            ['limit' => $limit, 'hard_limit' => $hard],
        );
    }

    /**
     * Return token consumption for the current period.
     */
    public function currentTokenUsage(
        string $scope  = 'global',
        string $period = 'monthly',
    ): int {
        $periodKey = BudgetPeriod::from($period)->currentKey();

        return (int) $this->aiUsage()
            ->where('scope', $scope)
            ->where('period_key', $periodKey)
            ->sum(DB::raw('prompt_tokens + completion_tokens'));
    }

    /**
     * Return remaining tokens for the current period.
     * Returns null when no budget is configured for the given scope + period.
     */
    public function remainingTokens(
        string $scope  = 'global',
        string $period = 'monthly',
    ): ?int {
        $budget = $this->aiBudgets()
            ->where('scope', $scope)
            ->where('period', $period)
            ->first();

        if (! $budget) {
            return null;
        }

        return max(0, $budget->limit - $this->currentTokenUsage($scope, $period));
    }
}
