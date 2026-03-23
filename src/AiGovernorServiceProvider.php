<?php

namespace AiGovernor;

use AiGovernor\Budget\BudgetEnforcer;
use AiGovernor\Console\Commands\MakePromptCommand;
use AiGovernor\Console\Commands\ResetBudgetsCommand;
use AiGovernor\Console\Commands\SyncPromptsCommand;
use AiGovernor\Contracts\AiProviderAdapter;
use AiGovernor\Execution\GovernedExecutor;
use AiGovernor\Http\Middleware\EnforceAiBudget;
use Illuminate\Support\ServiceProvider;

class AiGovernorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai-governor.php',
            'ai-governor'
        );

        $this->app->singleton(AiProviderAdapter::class, function () {
            $adapterClass = config('ai-governor.adapter');

            if (! class_exists($adapterClass)) {
                throw new \RuntimeException(
                    "AiGovernor: Adapter class [{$adapterClass}] does not exist. " .
                    'Check the [adapter] key in config/ai-governor.php.'
                );
            }

            return new $adapterClass;
        });

        $this->app->singleton(BudgetEnforcer::class);

        $this->app->singleton(GovernedExecutor::class, function ($app) {
            return new GovernedExecutor(
                adapter:  $app->make(AiProviderAdapter::class),
                enforcer: $app->make(BudgetEnforcer::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register the route middleware alias so consumers can write:
        //   ->middleware('ai.budget:summarize')
        // instead of the full class name.
        $this->app['router']->aliasMiddleware('ai.budget', EnforceAiBudget::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ai-governor.php' => config_path('ai-governor.php'),
            ], 'ai-governor-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'ai-governor-migrations');

            $this->publishes([
                __DIR__ . '/../resources/stubs/ai-governor/' => base_path('stubs/ai-governor'),
            ], 'ai-governor-stubs');

            $this->commands([
                MakePromptCommand::class,
                SyncPromptsCommand::class,
                ResetBudgetsCommand::class,
            ]);
        }
    }
}
