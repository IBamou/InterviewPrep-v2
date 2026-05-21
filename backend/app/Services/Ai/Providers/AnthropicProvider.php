<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\DTO\Response;
use App\Services\Ai\Exceptions\AuthenticationException;
use App\Services\Ai\Exceptions\ProviderException;
use App\Services\Ai\Exceptions\RateLimitException;
use App\Services\Ai\Exceptions\TimeoutException;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements AiProvider
{
    private const BASE_URL = 'https://api.anthropic.com/v1';

    private readonly string $apiKey;

    private readonly string $model;

    private readonly array $defaults;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        $this->model = $config['model'];
        $this->defaults = config('ai.defaults');
    }

    public function send(array $messages, array $options = []): Response
    {
        $response = $this->post('/messages', $this->buildPayload($messages, $options));

        return $this->parseResponse($response);
    }

    public function sendJson(array $messages, array $options = []): array
    {
        $response = $this->post('/messages', $this->buildPayload($messages, $options));

        return $this->parseJsonResponse($response);
    }

    public function sendStream(array $messages, array $options = []): \Illuminate\Http\Response
    {
        $payload = $this->buildPayload($messages, $options);
        $payload['stream'] = true;

        return response()->stream(function () use ($payload) {
            try {
                $response = $this->client()
                    ->withOptions(['stream' => true])
                    ->post(self::BASE_URL . '/messages', $payload);

                if ($response->failed()) {
                    echo "event: error\n";
                    echo "data: " . json_encode(['error' => $this->parseErrorMessage($response)]) . "\n\n";
                    echo "event: done\ndata: [DONE]\n\n";
                    ob_flush();
                    flush();

                    return;
                }

                $body = $response->toPsrResponse()->getBody();

                while (!$body->eof()) {
                    $line = $this->readLine($body);

                    if ($line === '') {
                        continue;
                    }

                    if (str_starts_with($line, 'event: ')) {
                        continue;
                    }

                    if (str_starts_with($line, 'data: ')) {
                        $jsonStr = substr($line, 6);

                        if ($jsonStr === '[DONE]') {
                            continue;
                        }

                        $data = json_decode($jsonStr, true);

                        if (isset($data['type']) && $data['type'] === 'content_block_delta') {
                            $content = $data['delta']['text'] ?? '';
                            if ($content !== '') {
                                echo "data: " . json_encode(['content' => $content]) . "\n\n";
                                ob_flush();
                                flush();
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                echo "event: error\n";
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
            }

            echo "event: done\ndata: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function getConfig(): array
    {
        return [
            'base_url' => self::BASE_URL,
            'model' => $this->model,
            'timeout' => $this->defaults['timeout'],
        ];
    }

    private function buildPayload(array $messages, array $options): array
    {
        $systemPrompt = '';
        $converted = [];

        foreach ($messages as $msg) {
            $role = $msg instanceof \App\Services\Ai\DTO\Message ? $msg->role : ($msg['role'] ?? '');
            $content = $msg instanceof \App\Services\Ai\DTO\Message ? $msg->content : ($msg['content'] ?? '');

            if ($role === 'system') {
                $systemPrompt .= $content . "\n";
            } else {
                $converted[] = [
                    'role' => $role === 'assistant' ? 'assistant' : 'user',
                    'content' => $content,
                ];
            }
        }

        $body = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $converted,
            'max_tokens' => $options['max_tokens'] ?? $this->defaults['max_tokens'],
            'temperature' => $options['temperature'] ?? $this->defaults['temperature'],
        ];

        if ($systemPrompt !== '') {
            $body['system'] = trim($systemPrompt);
        }

        return array_merge($body, $options['extra'] ?? []);
    }

    private function post(string $url, array $data): HttpClientResponse
    {
        try {
            $response = $this->client()->post(self::BASE_URL . $url, $data);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new TimeoutException(
                'Connection to Anthropic API failed: ' . $e->getMessage(),
                $e,
            );
        }

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response;
    }

    private function parseResponse(HttpClientResponse $response): Response
    {
        $data = $response->json();

        $content = $data['content'][0]['text'] ?? null;

        if ($content === null || $content === '') {
            throw new ProviderException('Empty response content from Anthropic.');
        }

        return new Response(
            content: $content,
            model: $data['model'] ?? null,
            inputTokens: $data['usage']['input_tokens'] ?? null,
            outputTokens: $data['usage']['output_tokens'] ?? null,
            finishReason: $data['stop_reason'] ?? null,
        );
    }

    private function parseJsonResponse(HttpClientResponse $response): array
    {
        $data = $response->json();

        $content = $data['content'][0]['text'] ?? null;

        if ($content === null || $content === '') {
            throw new ProviderException('Empty response from Anthropic.');
        }

        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            throw new ProviderException('Failed to parse JSON from Anthropic response.');
        }

        return $parsed;
    }

    private function handleError(HttpClientResponse $response): void
    {
        $status = $response->status();
        $body = $response->json();
        $message = $body['error']['message'] ?? $response->reason() ?? 'Unknown error';

        throw match ($status) {
            401 => new AuthenticationException(
                "Anthropic API authentication failed: {$message}",
            ),
            429 => new RateLimitException(
                "Anthropic API rate limit exceeded: {$message}",
            ),
            408, 504 => new TimeoutException(
                "Anthropic API request timed out: {$message}",
            ),
            default => new ProviderException(
                "Anthropic API error [{$status}]: {$message}",
                $status,
            ),
        };
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->contentType('application/json')
            ->withHeaders(['anthropic-version' => '2023-06-01'])
            ->timeout($this->defaults['timeout'])
            ->retry(
                $this->defaults['retry'],
                $this->defaults['retry_delay'],
                fn (\Exception $e) => $e instanceof TimeoutException === false,
            );
    }

    private function readLine(\Psr\Http\Message\StreamInterface $body): string
    {
        $line = '';

        while (!$body->eof()) {
            $byte = $body->read(1);
            if ($byte === "\n") {
                break;
            }
            $line .= $byte;
        }

        return trim($line);
    }
}
