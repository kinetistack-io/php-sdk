<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

class ServiceUnavailableException extends KinetiException
{
    public function __construct(string $message = 'Service Unavailable', int $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
