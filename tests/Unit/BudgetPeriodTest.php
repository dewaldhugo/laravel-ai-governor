<?php

use AiGovernor\Budget\BudgetPeriod;

describe('BudgetPeriod', function () {

    it('returns a Y-m-d key for daily period', function () {
        $key = BudgetPeriod::Daily->currentKey();
        expect($key)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    });

    it('returns a Y-m key for monthly period', function () {
        $key = BudgetPeriod::Monthly->currentKey();
        expect($key)->toMatch('/^\d{4}-\d{2}$/');
    });

    it('can be instantiated from string value', function () {
        expect(BudgetPeriod::from('daily'))->toBe(BudgetPeriod::Daily);
        expect(BudgetPeriod::from('monthly'))->toBe(BudgetPeriod::Monthly);
    });

});
