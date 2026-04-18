# Laravel AI Governor

[![Tests](https://github.com/dewaldhugo/laravel-ai-governor/actions/workflows/tests.yml/badge.svg)](https://github.com/dewaldhugo/laravel-ai-governor/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/dewaldhugo/laravel-ai-governor.svg)](https://packagist.org/packages/dewaldhugo/laravel-ai-governor)
[![License](https://img.shields.io/github/license/dewaldhugo/laravel-ai-governor.svg)](LICENSE.md)

**Prompt versioning, token governance, and deployment tooling for Laravel AI applications.**

Most Laravel AI packages solve the provider integration problem. AI Governor solves what comes after: how do you version prompts like schema, enforce spending limits per user, and deploy AI configuration through CI without touching production databases?

Compatible with `openai-php/laravel` and Anthropic. Prism PHP support coming soon.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.2+ |
| Laravel | 11 / 12 / 13 |

## Compatibility

| Laravel | PHP       | Tested in CI |
|---------|-----------|--------------|
| 11.x    | 8.2 – 8.4 | ✅ |
| 12.x    | 8.2 – 8.4 | ✅ |
| 13.x    | 8.3 – 8.4 | ⏳ Pending `pestphp/pest-plugin-laravel` Laravel 13 support |

---

## Installation

```bash
composer require dewaldhugo/laravel-ai-governor
```

Publish the config and run migrations:

```bash
php artisan vendor:publish --tag=ai-governor-config
php artisan migrate
```

Set your provider key in `.env`:

```env
OPENAI_API_KEY=sk-...
```

---

## Core Concepts

AI Governor is built around three independent concerns that compose into a governance layer:

**Prompt Migrations** — Prompt definitions live in version control as PHP files. A sync command writes them to the database deterministically, making prompt state part of your deployment pipeline.

**Budget Enforcement** — Any Eloquent model (User, Team, Tenant) can hold a token budget. The `GovernedExecutor` checks the budget before calling the provider and records usage after. Hard limits throw an exception. Soft limits log a warning.

**The Adapter Contract** — Provider calls are isolated behind a single interface. Swap providers by changing one config value. Your application code never changes.

---

## Prompt Migrations

### 1. Generate a definition

```bash
php artisan make:prompt SummarizeArticle
```

This creates a timestamped file in `app/Prompts`:

```php
<?php

use AiGovernor\Prompts\PromptDefinition;

return new class extends PromptDefinition
{
    public string $name = 'summarize';
    public int    $version = 1;
    public string $model = 'gpt-4o-mini';
    public float  $temperature = 0.2;
    public int    $maxTokens = 800;

    public function system(): string
    {
        return 'You are a precise summarizer. Return a maximum of three sentences.';
    }

    public function user(): string
    {
        return 'Summarize the following: {{text}}';
    }
};
```

### 2. Sync to the database

```bash
php artisan ai:prompts:sync
```

Add this to your deploy script after `php artisan migrate`. Prompts are scoped to the current Laravel environment automatically, so staging and production never share definitions.

Preview changes before writing:

```bash
php artisan ai:prompts:sync --dry-run
```

### 3. Rollbacks

Revert the definition file in Git, redeploy, and run the sync command. No manual database edits required.

---

## Executing Prompts

Resolve the prompt by name and run it through the `GovernedExecutor`:

```php
use AiGovernor\Execution\GovernedExecutor;
use AiGovernor\Models\PromptVersion;

$prompt = PromptVersion::resolve('summarize');

$result = app(GovernedExecutor::class)->run(
    prompt:    $prompt,
    variables: ['text' => $article->body],
);

echo $result->content;
echo $result->totalTokens(); // prompt + completion
echo $result->latencyMs;
```

`PromptVersion::resolve()` always returns the latest version scoped to the current environment.

---

## Token Budgets

### Add the trait to your model

```php
use AiGovernor\Traits\HasAiBudget;

class User extends Authenticatable
{
    use HasAiBudget;
}
```

### Set a budget

```php
// 100,000 tokens per month — hard limit
$user->setAiBudget(limit: 100_000, period: 'monthly');

// 10,000 tokens per day — soft limit (warns, does not throw)
$user->setAiBudget(limit: 10_000, period: 'daily', hard: false);

// Scoped to a specific feature
$user->setAiBudget(limit: 50_000, period: 'monthly', scope: 'summarize');
```

### Execute with enforcement

Pass `$owner` to the executor and it handles the rest:

```php
$result = app(GovernedExecutor::class)->run(
    prompt:    $prompt,
    variables: ['text' => $article->body],
    owner:     $user,
    scope:     'summarize',
);
```

If the user has exhausted their budget, a `BudgetExceededException` is thrown before the provider is called — no tokens consumed, no cost incurred.

### Check usage

```php
$used      = $user->currentTokenUsage(scope: 'summarize', period: 'monthly');
$remaining = $user->remainingTokens(scope: 'summarize', period: 'monthly');
```

### Route middleware

Protect entire routes without touching controller logic:

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'ai.budget' => \AiGovernor\Http\Middleware\EnforceAiBudget::class,
    ]);
})
```

```php
Route::post('/summarize', SummarizeController::class)
     ->middleware('ai.budget:summarize');
```

Returns a `429` JSON response when the budget is exceeded.

> **Note:** Unauthenticated requests (where `$request->user()` returns `null`) pass through the middleware without a budget check. If your AI routes must be protected for all traffic, ensure `ai.budget` is applied **after** an authentication middleware (`auth`, `auth:sanctum`, etc.) in the stack.

---

## Switching Providers

Change one line in `config/ai-governor.php`:

```php
// OpenAI (default)
'adapter' => \AiGovernor\Adapters\OpenAiAdapter::class,

// Anthropic Claude
'adapter' => \AiGovernor\Adapters\AnthropicAdapter::class,

// No-op for tests and local development
'adapter' => \AiGovernor\Adapters\NullAdapter::class,
```

Set the corresponding key in `.env` for your chosen provider:

```env
# OpenAI
OPENAI_API_KEY=sk-...

# Anthropic
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_API_VERSION=2023-06-01   # optional — this is the default
```

To build a custom adapter, implement the `AiProviderAdapter` contract:

```php
use AiGovernor\Contracts\AiProviderAdapter;
use AiGovernor\Exceptions\ProviderException;
use AiGovernor\Models\PromptVersion;
use AiGovernor\Values\AdapterResult;

class MyCustomAdapter implements AiProviderAdapter
{
    public function execute(PromptVersion $prompt, string $rendered): AdapterResult
    {
        // Call your provider and return a normalised AdapterResult.
        // Throw ProviderException for provider-level semantic failures
        // (e.g. invalid model, content policy rejection).
        // Throw Illuminate\Http\Client\RequestException for HTTP/network errors.
        return new AdapterResult(
            content:          $responseBody,
            promptTokens:     $usage['input'],
            completionTokens: $usage['output'],
            latencyMs:        $latencyMs,
            model:            $prompt->model,
        );
    }
}
```

Then bind it in `config/ai-governor.php` or register it in a Service Provider.

---

## Testing

Use the `NullAdapter` to test your application logic without making real API calls.

Bind a configured instance into the container — do **not** use `app->bind()` with the class name, as that would construct the adapter with default values and ignore your configured response:

```php
use AiGovernor\Adapters\NullAdapter;
use AiGovernor\Contracts\AiProviderAdapter;

// In your TestCase::setUp() or in an individual test:
$this->app->instance(
    AiProviderAdapter::class,
    NullAdapter::make(
        content:          'This is the mocked AI response.',
        promptTokens:     10,
        completionTokens: 20,
    ),
);
```

Each test that needs a different response simply re-binds a new instance. Because state lives on the instance rather than in static properties, tests running in parallel cannot interfere with each other.

---

## Artisan Reference

| Command | Description |
|---|---|
| `make:prompt {Name}` | Generate a new versioned prompt definition |
| `ai:prompts:sync` | Sync definitions to the database |
| `ai:prompts:sync --dry-run` | Preview sync without writing |
| `ai:budgets:reset {period}` | Prune expired usage records |

---

## CI Integration

Recommended deploy script order:

```bash
php artisan migrate --force
php artisan ai:prompts:sync
php artisan config:cache
php artisan route:cache
```

The sync command exits with code `1` if any definition fails, so a failed sync will halt your pipeline before the new release receives traffic.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## Contributing

Pull requests welcome. Please write tests for new functionality and run `composer test` before opening a PR.

---

## License

MIT. See [LICENSE.md](LICENSE.md).
