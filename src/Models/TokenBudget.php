<?php

namespace AiGovernor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TokenBudget extends Model
{
    protected $table = 'token_budgets';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'scope',
        'period',
        'limit',
        'hard_limit',
    ];

    protected $casts = [
        'limit'      => 'integer',
        'hard_limit' => 'boolean',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
