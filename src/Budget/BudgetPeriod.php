<?php

namespace AiGovernor\Budget;

enum BudgetPeriod: string
{
    case Daily   = 'daily';
    case Monthly = 'monthly';

    /**
     * Returns the pre-computed period key for this period at the current time.
     *
     * Storing this key on each usage row means budget lookups are a simple
     * WHERE clause with no date arithmetic at read time.
     */
    public function currentKey(): string
    {
        return match ($this) {
            self::Daily   => now()->format('Y-m-d'),
            self::Monthly => now()->format('Y-m'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Daily   => 'today',
            self::Monthly => 'this month',
        };
    }
}
