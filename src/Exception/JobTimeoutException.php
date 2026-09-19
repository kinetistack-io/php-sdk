<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

use KinetiStack\Sdk\Dto\JobDto;

class JobTimeoutException extends KinetiException
{
    public function __construct(
        string $message,
        public readonly JobDto $latestJob,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
