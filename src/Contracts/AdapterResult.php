<?php

namespace AiGovernor\Contracts;

/**
 * @deprecated Use AiGovernor\Values\AdapterResult directly.
 *             This compatibility shim will be removed in v2.
 *
 * Kept as a concrete subclass (not class_alias) so that PSR-4 autoloading,
 * IDE static analysis, and instanceof checks all work without surprises.
 */
class AdapterResult extends \AiGovernor\Values\AdapterResult {}
