<?php

namespace App\Services\Ai\DTO;

class Response
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $model = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?string $finishReason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'],
            model: $data['model'] ?? null,
            inputTokens: $data['input_tokens'] ?? null,
            outputTokens: $data['output_tokens'] ?? null,
            finishReason: $data['finish_reason'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'finish_reason' => $this->finishReason,
        ];
    }
}
