<?php

namespace App\Services;

use App\Models\Concept;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Prompts\PromptBuilder;

class ExplanationService
{
    public function __construct(
        private readonly AiGateway $ai,
        private readonly PromptBuilder $prompts,
    ) {}

    public function generate(string $title, string $domainName): array
    {
        $messages = $this->prompts->generateExplanation($title, $domainName);

        return $this->ai->sendJson($messages, [
            'temperature' => 0.3,
            'max_tokens' => 500,
        ]);
    }

    public function improve(Concept $concept): array
    {
        $messages = $this->prompts->improveExplanation($concept);

        return $this->ai->sendJson($messages, [
            'temperature' => 0.3,
            'max_tokens' => 500,
        ]);
    }

    public function verify(string $title, string $domainName): array
    {
        $messages = $this->prompts->verifyTitle($title, $domainName);

        return $this->ai->sendJson($messages, [
            'temperature' => 0.2,
            'max_tokens' => 100,
        ]);
    }
}
