<?php

namespace App\Services\Ai\Exceptions;

class TimeoutException extends AiException
{
    public function __construct(
        string $message = 'AI provider request timed out.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 408, $previous);
    }
}
