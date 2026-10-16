<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

class PayloadTooLargeException extends KinetiException
{
    public function __construct(string $message = 'Payload Too Large', int $code = 413, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
