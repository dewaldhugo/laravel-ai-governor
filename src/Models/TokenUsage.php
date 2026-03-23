<?php

namespace AiGovernor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TokenUsage extends Model
{
    protected $table = 'token_usage';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'prompt_version_id',
        'scope',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'period_key',
    ];

    protected $casts = [
        'prompt_tokens'     => 'integer',
        'completion_tokens' => 'integer',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class);
    }

    public function totalTokens(): int
    {
        return $this->prompt_tokens + $this->completion_tokens;
    }
}
