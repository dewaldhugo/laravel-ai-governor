<?php

namespace AiGovernor\Budget;

use AiGovernor\Exceptions\BudgetExceededException;
use AiGovernor\Models\PromptVersion;
use AiGovernor\Models\TokenBudget;
use AiGovernor\Models\TokenUsage;
use AiGovernor\Values\AdapterResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BudgetEnforcer
{
    /**
     * Check whether the owner has budget remaining for the given scope.
     *
     * Uses SELECT … FOR UPDATE inside a transaction to produce a consistent
     * snapshot of budget rows and current usage, preventing two concurrent
     * requests from both passing the check on a nearly-exhausted budget.
     *
     * Note: overshoot by one concurrent call remains possible because the
     * HTTP round-trip to the AI provider occurs after this lock is released.
     * This is an acceptable trade-off; holding a database lock across a
     * network call to a third-party API is not viable. The next pre-flight
     * check will catch any overshoot on the following request.
     *
     * When hard_limit is true on the budget row, throws BudgetExceededException.
     * When false (soft limit), logs a warning and allows the call to proceed.
     * Falls through silently when no budget is configured for this owner + scope.
     *
     * @throws BudgetExceededException
     */
    public function checkOrFail(Model $owner, string $scope = 'global'): void
    {
        DB::transaction(function () use ($owner, $scope) {
            $budgets = TokenBudget::where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->where('scope', $scope)
                ->lockForUpdate()
                ->get();

            if ($budgets->isEmpty()) {
                return;
            }

            // Fetch all usage totals for the relevant period keys in one query.
            $periodKeys = $budgets
                ->map(fn ($b) => BudgetPeriod::from($b->period)->currentKey())
                ->unique()
                ->values()
                ->toArray();

            $usageByPeriodKey = TokenUsage::where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->where('scope', $scope)
                ->whereIn('period_key', $periodKeys)
                ->selectRaw('period_key, SUM(prompt_tokens + completion_tokens) AS total')
                ->groupBy('period_key')
                ->pluck('total', 'period_key');

            foreach ($budgets as $budget) {
                $periodKey = BudgetPeriod::from($budget->period)->currentKey();
                $used      = (int) ($usageByPeriodKey[$periodKey] ?? 0);

                if ($used < $budget->limit) {
                    continue;
                }

                $softFail = config('ai-governor.budgets.soft_fail_by_default', false);

                if (! $budget->hard_limit || $softFail) {
                    Log::warning('AiGovernor: token budget exceeded (soft limit)', [
                        'owner_type' => $owner->getMorphClass(),
                        'owner_id'   => $owner->getKey(),
                        'scope'      => $scope,
                        'period'     => $budget->period,
                        'used'       => $used,
                        'limit'      => $budget->limit,
                    ]);

                    continue;
                }

                throw new BudgetExceededException(
                    owner:  $owner,
                    scope:  $scope,
                    used:   $used,
                    limit:  $budget->limit,
                    period: $budget->period,
                );
            }
        });
    }

    /**
     * Record token usage for the owner against all BudgetPeriod cases.
     *
     * We write a row per period (all cases defined in the BudgetPeriod enum)
     * so that budget lookups for any period are always a single indexed WHERE
     * clause. Recording against all enum cases — rather than a hardcoded
     * subset — means adding a new period (e.g. weekly) to the enum
     * automatically starts recording for it without touching this method.
     */
    public function record(
        Model         $owner,
        AdapterResult $result,
        PromptVersion $prompt,
        string        $scope = 'global',
    ): void {
        DB::transaction(function () use ($owner, $result, $prompt, $scope) {
            foreach (BudgetPeriod::cases() as $period) {
                TokenUsage::create([
                    'owner_type'        => $owner->getMorphClass(),
                    'owner_id'          => $owner->getKey(),
                    'prompt_version_id' => $prompt->id,
                    'scope'             => $scope,
                    'model'             => $result->model,
                    'prompt_tokens'     => $result->promptTokens,
                    'completion_tokens' => $result->completionTokens,
                    'period_key'        => $period->currentKey(),
                ]);
            }
        });
    }
}
