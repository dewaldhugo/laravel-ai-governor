<?php

namespace AiGovernor\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class BudgetExceededException extends RuntimeException
{
    public function __construct(
        public readonly Model  $owner,
        public readonly string $scope,
        public readonly int    $used,
        public readonly int    $limit,
        public readonly string $period,
    ) {
        parent::__construct(
            sprintf(
                'Token budget exceeded for %s #%s. Scope: %s. Period: %s. Used: %d / %d.',
                class_basename($owner),
                $owner->getKey(),
                $scope,
                $period,
                $used,
                $limit,
            )
        );
    }
}
