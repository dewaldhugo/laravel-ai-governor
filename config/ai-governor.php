<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prompt Definition Path
    |--------------------------------------------------------------------------
    |
    | The directory where your prompt definition files are stored.
    | These are scanned by the ai:prompts:sync Artisan command.
    |
    */
    'prompt_path' => app_path('Prompts'),

    /*
    |--------------------------------------------------------------------------
    | Default Provider Adapter
    |--------------------------------------------------------------------------
    |
    | The adapter class responsible for executing AI requests.
    | Swap this to switch providers without touching application code.
    |
    | Bundled adapters:
    |   - \AiGovernor\Adapters\OpenAiAdapter::class
    |   - \AiGovernor\Adapters\NullAdapter::class  (testing)
    |
    */
    'adapter' => \AiGovernor\Adapters\OpenAiAdapter::class,

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum seconds to wait for a provider response before throwing.
    | Without this ceiling, a stalled connection holds your queue worker.
    |
    */
    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Provider Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials consumed by the bundled adapters.
    | Custom adapters may read from here or define their own config.
    |
    */
    'providers' => [
        'openai' => [
            'key'  => env('OPENAI_API_KEY'),
            'url'  => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
        ],
        'anthropic' => [
            'key'     => env('ANTHROPIC_API_KEY'),
            'version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Budget Settings
    |--------------------------------------------------------------------------
    |
    | Controls default behaviour of the BudgetEnforcer.
    |
    | soft_fail_by_default: When true, over-budget calls log a warning instead
    |                        of throwing BudgetExceededException. Individual
    |                        budgets can override this via the hard_limit column.
    |
    */
    'budgets' => [
        'soft_fail_by_default' => false,
    ],

];
