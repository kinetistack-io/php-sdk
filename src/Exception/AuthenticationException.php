<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

class AuthenticationException extends KinetiException
{
    public function __construct(string $message = 'Unauthorized', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
