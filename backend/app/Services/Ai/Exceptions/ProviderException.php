<?php

namespace App\Services\Ai\Exceptions;

class ProviderException extends AiException
{
    public function __construct(
        string $message = 'AI provider error',
        int $statusCode = 500,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
