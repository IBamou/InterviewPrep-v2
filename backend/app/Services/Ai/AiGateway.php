<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\DTO\Message;
use App\Services\Ai\DTO\Response;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Providers\AnthropicProvider;
use App\Services\Ai\Providers\OpenAiProvider;

class AiGateway
{
    private ?AiProvider $provider = null;

    private string $providerName;

    private array $providers = [];

    public function __construct()
    {
        $this->providerName = config('ai.default_provider');
    }

    public function provider(?string $name = null): self
    {
        if ($name !== null) {
            $this->providerName = $name;
            $this->provider = null;
        }

        return $this;
    }

    public function send(array $messages, array $options = []): Response
    {
        return $this->resolveProvider()->send($messages, $options);
    }

    public function sendJson(array $messages, array $options = []): array
    {
        return $this->resolveProvider()->sendJson($messages, $options);
    }

    public function sendStream(array $messages, array $options = []): \Illuminate\Http\Response
    {
        return $this->resolveProvider()->sendStream($messages, $options);
    }

    public function getConfig(): array
    {
        return $this->resolveProvider()->getConfig();
    }

    public function getAvailableProviders(): array
    {
        return array_keys(config('ai.providers'));
    }

    private function resolveProvider(): AiProvider
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $config = config("ai.providers.{$this->providerName}");

        if ($config === null) {
            throw new AiException("AI provider '{$this->providerName}' is not configured.");
        }

        $this->provider = match ($config['driver']) {
            'openai' => app(OpenAiProvider::class, ['config' => $config]),
            'anthropic' => app(AnthropicProvider::class, ['config' => $config]),
            default => throw new AiException("Unsupported AI driver: '{$config['driver']}'."),
        };

        return $this->provider;
    }
}
