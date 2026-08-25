<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

use KinetiStack\Sdk\Dto\BatchJobDto;

class BatchJobTimeoutException extends KinetiException
{
    public function __construct(
        string $message,
        public readonly BatchJobDto $latestJob,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
