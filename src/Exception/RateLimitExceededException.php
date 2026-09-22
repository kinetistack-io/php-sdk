<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

use KinetiStack\Sdk\Dto\RateLimitInfoDto;

class RateLimitExceededException extends RateLimitException
{
    public function __construct(
        string $message = 'Rate limit exceeded.',
        ?int $retryAfter = null,
        ?\Throwable $previous = null,
        ?int $limit = null,
        ?int $remaining = null,
        ?int $reset = null,
        ?RateLimitInfoDto $rateLimitInfo = null
    ) {
        parent::__construct(
            $message,
            $retryAfter,
            $previous,
            $limit,
            $remaining,
            $reset,
            $rateLimitInfo
        );
    }

    public static function fromRateLimitInfo(
        string $message = 'Rate limit exceeded.',
        ?RateLimitInfoDto $rateLimitInfo = null,
        ?\Throwable $previous = null
    ): self {
        return new self(
            $message,
            $rateLimitInfo?->retryAfter,
            $previous,
            $rateLimitInfo?->limit,
            $rateLimitInfo?->remaining,
            $rateLimitInfo?->reset,
            $rateLimitInfo
        );
    }
}
