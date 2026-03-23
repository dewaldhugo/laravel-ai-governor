<?php

namespace AiGovernor\Exceptions;

use RuntimeException;

/**
 * Thrown by adapter implementations when the AI provider returns an
 * error that is not a transient HTTP failure (e.g. invalid model,
 * malformed request body, content policy rejection).
 *
 * Adapters should throw Illuminate\Http\Client\RequestException for
 * network/HTTP-layer errors and reserve ProviderException for
 * provider-level semantic failures.
 */
class ProviderException extends RuntimeException
{
    public function __construct(
        string           $message,
        public readonly string $provider,
        public readonly ?array $context = null,
        ?\Throwable      $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
