<?php

use AiGovernor\Values\AdapterResult;

describe('AdapterResult', function () {

    it('calculates total tokens correctly', function () {
        $result = new AdapterResult(
            content:          'Test output',
            promptTokens:     100,
            completionTokens: 50,
            latencyMs:        200,
            model:            'gpt-4o-mini',
        );

        expect($result->totalTokens())->toBe(150);
    });

    it('serialises to array with all expected keys', function () {
        $result = new AdapterResult(
            content:          'Test',
            promptTokens:     10,
            completionTokens: 20,
            latencyMs:        100,
            model:            'gpt-4o-mini',
        );

        expect($result->toArray())->toHaveKeys([
            'content',
            'prompt_tokens',
            'completion_tokens',
            'total_tokens',
            'latency_ms',
            'model',
        ]);
    });

});
