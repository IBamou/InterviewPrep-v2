<?php

namespace App\Services\Ai\Exceptions;

class RateLimitException extends AiException
{
    public function __construct(
        string $message = 'AI provider rate limit exceeded. Please wait before retrying.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 429, $previous);
    }
}
