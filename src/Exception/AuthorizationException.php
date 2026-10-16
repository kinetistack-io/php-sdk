<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

class AuthorizationException extends KinetiException
{
    public function __construct(string $message = 'Access Denied', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
