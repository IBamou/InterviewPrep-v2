<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTO\Response;

interface AiProvider
{
    public function send(array $messages, array $options = []): Response;

    public function sendJson(array $messages, array $options = []): array;

    public function sendStream(array $messages, array $options = []): \Illuminate\Http\Response;

    public function getConfig(): array;
}
