<?php

namespace App\Services\Ai\Exceptions;

class AuthenticationException extends AiException
{
    public function __construct(
        string $message = 'AI provider authentication failed. Check your API key.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 401, $previous);
    }
}
