<?php

namespace AiGovernor\Prompts;

abstract class PromptDefinition
{
    /**
     * Unique slug that identifies this prompt across versions.
     * Must be kebab-case, e.g. 'summarize-article'.
     */
    public string $name;

    /**
     * Monotonically increasing integer. Increment on every content change.
     * The sync command uses this to detect whether a definition is new.
     */
    public int $version;

    /**
     * The model identifier passed to the provider, e.g. 'gpt-4o', 'claude-sonnet-4-6'.
     */
    public string $model;

    public float $temperature = 0.70;

    public int $maxTokens = 1000;

    /**
     * The system prompt text. Defines model persona and constraints.
     */
    abstract public function system(): string;

    /**
     * The user prompt template. Use {{variable}} placeholders for dynamic values.
     */
    abstract public function user(): string;

    /**
     * Assert that all required properties are initialised.
     *
     * Called by SyncPromptsCommand before writing to the database so that
     * missing properties produce a clear, actionable error rather than an
     * opaque "must not be accessed before initialization" fatal from PHP.
     *
     * @throws \LogicException
     */
    final public function validate(): void
    {
        foreach (['name', 'version', 'model'] as $property) {
            if (! isset($this->{$property})) {
                throw new \LogicException(
                    sprintf(
                        '%s::$%s must be set. Ensure your prompt definition declares this property.',
                        static::class,
                        $property,
                    )
                );
            }
        }

        if (empty(trim($this->name))) {
            throw new \LogicException(static::class . '::$name must not be empty.');
        }

        if ($this->version < 1) {
            throw new \LogicException(static::class . '::$version must be a positive integer.');
        }
    }

    /**
     * Render the user template by substituting all {{variable}} placeholders.
     *
     * For structured or JSON payloads, override this method in your definition
     * to apply custom rendering logic before the template substitution.
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(array $variables): string
    {
        $template = $this->user();

        foreach ($variables as $key => $value) {
            $rendered = is_array($value)
                ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : (string) $value;

            $template = str_replace('{{' . $key . '}}', $rendered, $template);
        }

        return $template;
    }
}
