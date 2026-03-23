# Changelog

All notable changes to this package will be documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [1.0.0] — 2026-03-13

### Added
- `PromptDefinition` base class with `{{variable}}` template rendering
- `php artisan make:prompt` — generate timestamped prompt definition files
- `php artisan ai:prompts:sync` — deterministic sync from filesystem to database with `--dry-run` support
- `php artisan ai:budgets:reset` — prune expired token usage records
- `PromptVersion` Eloquent model with environment-scoped `resolve()` helper
- `TokenBudget` and `TokenUsage` Eloquent models with polymorphic owner support
- `BudgetPeriod` enum — `daily` and `monthly` with pre-computed period keys
- `BudgetEnforcer` — pre-execution budget check and post-execution usage recording
- `GovernedExecutor` — orchestrates enforcement, rendering, adapter delegation, and logging
- `HasAiBudget` trait — `setAiBudget()`, `currentTokenUsage()`, `remainingTokens()`
- `EnforceAiBudget` middleware — route-level budget enforcement returning `429`
- `OpenAiAdapter` — production-ready HTTP client with retry on 429 and configurable timeout
- `NullAdapter` — deterministic test double with configurable response content and token counts
- `AiProviderAdapter` contract — single interface for all provider implementations
- `AdapterResult` readonly value object — normalised provider response
- `BudgetExceededException` — typed exception with owner, scope, used, limit, and period context
- Three anonymous migrations: `prompt_versions`, `token_budgets`, `token_usage`
- `AiGovernorServiceProvider` with publishable config, migrations, and stubs
- GitHub Actions CI matrix: PHP 8.2/8.3/8.4 × Laravel 11/12
- Pest test suite: unit tests for `BudgetPeriod`, `AdapterResult`; feature tests for `BudgetEnforcer`, `SyncPromptsCommand`
