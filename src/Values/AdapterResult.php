<?php

namespace AiGovernor\Values;

class AdapterResult
{
    public function __construct(
        public readonly string $content,
        public readonly int    $promptTokens,
        public readonly int    $completionTokens,
        public readonly int    $latencyMs,
        public readonly string $model,
    ) {}

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    public function toArray(): array
    {
        return [
            'content'           => $this->content,
            'prompt_tokens'     => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens'      => $this->totalTokens(),
            'latency_ms'        => $this->latencyMs,
            'model'             => $this->model,
        ];
    }
}
