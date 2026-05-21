<?php

namespace App\Services\Ai\Prompts\Concerns;

trait HasJsonEnforcement
{
    public function enforceJson(string $prompt, string $template, string $format = 'json'): string
    {
        return implode("\n\n", array_filter([
            $prompt,
            "IMPORTANT: You MUST respond with valid {$format} only. No markdown, no code fences, no explanations outside the JSON.",
            "Response format:",
            $template,
        ]));
    }
}
