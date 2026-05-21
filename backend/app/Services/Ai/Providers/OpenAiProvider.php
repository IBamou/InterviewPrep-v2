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

class OpenAiProvider implements AiProvider
{
    private readonly string $apiKey;

    private readonly string $baseUrl;

    private readonly string $model;

    private readonly array $defaults;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        $this->baseUrl = $config['base_url'];
        $this->model = $config['model'];
        $this->defaults = config('ai.defaults');
    }

    public function send(array $messages, array $options = []): Response
    {
        $response = $this->post('/chat/completions', $this->buildPayload($messages, $options));

        return $this->parseResponse($response);
    }

    public function sendJson(array $messages, array $options = []): array
    {
        $options['response_format'] = ['type' => 'json_object'];

        $response = $this->post('/chat/completions', $this->buildPayload($messages, $options));

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
                    ->post($this->baseUrl . '/chat/completions', $payload);

                if ($response->failed()) {
                    echo "data: " . json_encode(['error' => $this->parseErrorMessage($response)]) . "\n\n";
                    echo "data: [DONE]\n\n";
                    ob_flush();
                    flush();

                    return;
                }

                $body = $response->toPsrResponse()->getBody();

                while (!$body->eof()) {
                    $line = $this->readLine($body);

                    if ($line === '' || $line === 'data: [DONE]') {
                        continue;
                    }

                    $jsonStr = str_replace('data: ', '', $line);
                    $data = json_decode($jsonStr, true);
                    $content = $data['choices'][0]['delta']['content'] ?? '';

                    if ($content !== '') {
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
            }

            echo "data: [DONE]\n\n";
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
            'base_url' => $this->baseUrl,
            'model' => $this->model,
            'timeout' => $this->defaults['timeout'],
        ];
    }

    private function buildPayload(array $messages, array $options): array
    {
        $formatted = [];

        foreach ($messages as $msg) {
            if ($msg instanceof \App\Services\Ai\DTO\Message) {
                $formatted[] = $msg->toArray();
            } elseif (is_array($msg) && isset($msg['role'], $msg['content'])) {
                $formatted[] = $msg;
            }
        }

        return array_merge([
            'model' => $options['model'] ?? $this->model,
            'messages' => $formatted,
            'max_tokens' => $options['max_tokens'] ?? $this->defaults['max_tokens'],
            'temperature' => $options['temperature'] ?? $this->defaults['temperature'],
        ], $options['extra'] ?? [], $options['response_format'] ? ['response_format' => $options['response_format']] : []);
    }

    private function post(string $url, array $data): HttpClientResponse
    {
        try {
            $response = $this->client()->post($this->baseUrl . $url, $data);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new TimeoutException(
                'Connection to AI provider failed: ' . $e->getMessage(),
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

        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null || $content === '') {
            throw new ProviderException('Empty response content from AI provider.');
        }

        return new Response(
            content: $content,
            model: $data['model'] ?? null,
            inputTokens: $data['usage']['prompt_tokens'] ?? null,
            outputTokens: $data['usage']['completion_tokens'] ?? null,
            finishReason: $data['choices'][0]['finish_reason'] ?? null,
        );
    }

    private function parseJsonResponse(HttpClientResponse $response): array
    {
        $data = $response->json();

        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null || $content === '') {
            throw new ProviderException('Empty response from AI provider.');
        }

        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            throw new ProviderException('Failed to parse JSON from AI provider response.');
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
                "OpenAI API authentication failed: {$message}",
            ),
            429 => new RateLimitException(
                "OpenAI API rate limit exceeded: {$message}",
            ),
            408, 504 => new TimeoutException(
                "OpenAI API request timed out: {$message}",
            ),
            default => new ProviderException(
                "OpenAI API error [{$status}]: {$message}",
                $status,
            ),
        };
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->contentType('application/json')
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
