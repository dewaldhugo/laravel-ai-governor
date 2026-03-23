<?php

namespace AiGovernor\Console\Commands;

use AiGovernor\Budget\BudgetPeriod;
use AiGovernor\Models\TokenUsage;
use Illuminate\Console\Command;

class ResetBudgetsCommand extends Command
{
    protected $signature = 'ai:budgets:reset
                            {period : Period to reset — "daily" or "monthly"}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Prune token usage rows for the specified period rollover.';

    public function handle(): int
    {
        $periodInput = $this->argument('period');

        try {
            $period = BudgetPeriod::from($periodInput);
        } catch (\ValueError) {
            $this->error("Invalid period [{$periodInput}]. Use 'daily' or 'monthly'.");
            return self::FAILURE;
        }

        $key = $period->currentKey();

        $count = TokenUsage::where('period_key', '<', $key)
            ->where('period_key', 'LIKE', match ($period) {
                BudgetPeriod::Daily   => '____-__-__%',
                BudgetPeriod::Monthly => '____-__%',
            })
            ->count();

        if ($count === 0) {
            $this->info("No expired {$period->value} usage records found.");
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("{$count} expired {$period->value} usage records will be deleted. Continue?")) {
            $this->line('Aborted.');
            return self::SUCCESS;
        }

        TokenUsage::where('period_key', '<', $key)
            ->where('period_key', 'LIKE', match ($period) {
                BudgetPeriod::Daily   => '____-__-__%',
                BudgetPeriod::Monthly => '____-__%',
            })
            ->delete();

        $this->info("Deleted {$count} expired {$period->value} usage records.");

        return self::SUCCESS;
    }
}
