<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

class NotFoundException extends KinetiException
{
    public function __construct(string $message = 'Not Found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
