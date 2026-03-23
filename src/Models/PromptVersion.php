<?php

namespace AiGovernor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptVersion extends Model
{
    protected $table = 'prompt_versions';

    protected $fillable = [
        'name',
        'version',
        'model',
        'system_prompt',
        'user_template',
        'temperature',
        'max_tokens',
        'checksum',
        'environment',
    ];

    protected $casts = [
        'version'     => 'integer',
        'temperature' => 'float',
        'max_tokens'  => 'integer',
    ];

    public function tokenUsage(): HasMany
    {
        return $this->hasMany(TokenUsage::class);
    }

    /**
     * Resolve the current active version for a given prompt name
     * scoped to the running Laravel environment.
     */
    public static function resolve(string $name): self
    {
        return static::where('name', $name)
            ->where('environment', app()->environment())
            ->latest('version')
            ->firstOrFail();
    }

    /**
     * Render the user_template by substituting all {{variable}} placeholders.
     *
     * Array values are JSON-encoded; all other values are cast to string.
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(array $variables): string
    {
        $template = $this->user_template;

        foreach ($variables as $key => $value) {
            $rendered = is_array($value)
                ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : (string) $value;

            $template = str_replace('{{' . $key . '}}', $rendered, $template);
        }

        return $template;
    }
}
