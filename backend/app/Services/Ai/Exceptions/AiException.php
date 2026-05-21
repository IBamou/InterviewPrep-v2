<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

class AiException extends RuntimeException
{
    public function __construct(
        string $message = 'AI provider error',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
