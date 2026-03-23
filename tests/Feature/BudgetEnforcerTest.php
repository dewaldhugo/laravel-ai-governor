<?php

use AiGovernor\Adapters\NullAdapter;
use AiGovernor\Budget\BudgetEnforcer;
use AiGovernor\Budget\BudgetPeriod;
use AiGovernor\Values\AdapterResult;
use AiGovernor\Exceptions\BudgetExceededException;
use AiGovernor\Models\PromptVersion;
use AiGovernor\Models\TokenBudget;
use AiGovernor\Models\TokenUsage;
use Illuminate\Foundation\Auth\User;

beforeEach(function () {
    // Minimal User model backed by an in-memory SQLite users table.
    \Illuminate\Support\Facades\Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->default('Test User');
        $table->timestamps();
    });
});

function makeUser(): User
{
    return User::forceCreate(['name' => 'Test User']);
}

function makePromptVersion(): PromptVersion
{
    return PromptVersion::create([
        'name'          => 'test-prompt',
        'version'       => 1,
        'model'         => 'gpt-4o-mini',
        'system_prompt' => 'You are helpful.',
        'user_template' => '{{input}}',
        'temperature'   => 0.7,
        'max_tokens'    => 100,
        'checksum'      => str_repeat('a', 64),
        'environment'   => 'testing',
    ]);
}

describe('BudgetEnforcer', function () {

    it('passes when no budget is configured', function () {
        $enforcer = new BudgetEnforcer;
        $user     = makeUser();

        expect(fn () => $enforcer->checkOrFail($user))->not->toThrow(BudgetExceededException::class);
    });

    it('passes when usage is below budget', function () {
        $user     = makeUser();
        $enforcer = new BudgetEnforcer;
        $prompt   = makePromptVersion();

        TokenBudget::create([
            'owner_type' => User::class,
            'owner_id'   => $user->id,
            'scope'      => 'global',
            'period'     => 'monthly',
            'limit'      => 1000,
            'hard_limit' => true,
        ]);

        $result = new AdapterResult('output', 100, 50, 10, 'gpt-4o-mini');
        $enforcer->record($user, $result, $prompt);

        expect(fn () => $enforcer->checkOrFail($user))->not->toThrow(BudgetExceededException::class);
    });

    it('throws BudgetExceededException when hard limit is reached', function () {
        $user     = makeUser();
        $enforcer = new BudgetEnforcer;
        $prompt   = makePromptVersion();

        TokenBudget::create([
            'owner_type' => User::class,
            'owner_id'   => $user->id,
            'scope'      => 'global',
            'period'     => 'monthly',
            'limit'      => 100,
            'hard_limit' => true,
        ]);

        // Record usage that exceeds the limit.
        $result = new AdapterResult('output', 80, 80, 10, 'gpt-4o-mini');
        $enforcer->record($user, $result, $prompt);

        expect(fn () => $enforcer->checkOrFail($user))
            ->toThrow(BudgetExceededException::class);
    });

    it('records usage rows for both daily and monthly periods', function () {
        $user     = makeUser();
        $enforcer = new BudgetEnforcer;
        $prompt   = makePromptVersion();

        $result = new AdapterResult('output', 10, 20, 10, 'gpt-4o-mini');
        $enforcer->record($user, $result, $prompt);

        expect(TokenUsage::count())->toBe(2);

        $keys = TokenUsage::pluck('period_key')->toArray();

        expect($keys)->toContain(BudgetPeriod::Daily->currentKey())
                     ->toContain(BudgetPeriod::Monthly->currentKey());
    });

});
