<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

class ServerException extends KinetiException
{
    public function __construct(string $message = 'Internal Server Error', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
