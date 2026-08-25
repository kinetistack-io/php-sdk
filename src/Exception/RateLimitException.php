<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

class RateLimitException extends KinetiException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 429, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
