<?php

namespace AiGovernor\Http\Middleware;

use AiGovernor\Budget\BudgetEnforcer;
use AiGovernor\Exceptions\BudgetExceededException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAiBudget
{
    public function __construct(
        private readonly BudgetEnforcer $enforcer,
    ) {}

    /**
     * Route middleware that checks the authenticated user's token budget
     * before the request reaches the controller.
     *
     * Apply to routes via the registered alias:
     *
     *   Route::post('/summarize', SummarizeController::class)
     *        ->middleware('ai.budget:summarize');
     *
     * The optional $scope parameter maps to your feature bucket.
     * Defaults to 'global' when not supplied.
     *
     * IMPORTANT: Unauthenticated requests are allowed through without a
     * budget check. If your AI routes must be protected, ensure this
     * middleware is applied after 'auth' (or equivalent) in the stack,
     * or add an explicit authentication guard here for your use case.
     */
    public function handle(Request $request, Closure $next, string $scope = 'global'): Response
    {
        $user = $request->user();

        if ($user) {
            try {
                $this->enforcer->checkOrFail($user, $scope);
            } catch (BudgetExceededException $e) {
                return response()->json([
                    'error'   => 'token_budget_exceeded',
                    'message' => 'You have reached your AI usage limit for this period.',
                ], 429);
            }
        }

        return $next($request);
    }
}
