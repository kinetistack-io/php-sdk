<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

use KinetiStack\Sdk\Dto\RateLimitInfoDto;

class RateLimitException extends KinetiException
{
    public readonly ?int $retryAfter;
    public readonly ?int $limit;
    public readonly ?int $remaining;
    public readonly ?int $reset;
    public readonly ?RateLimitInfoDto $rateLimitInfo;

    public function __construct(
        string $message,
        ?int $retryAfter = null,
        ?\Throwable $previous = null,
        ?int $limit = null,
        ?int $remaining = null,
        ?int $reset = null,
        ?RateLimitInfoDto $rateLimitInfo = null
    ) {
        parent::__construct($message, 429, $previous);

        $this->retryAfter = $retryAfter ?? $rateLimitInfo?->retryAfter;
        $this->limit = $limit ?? $rateLimitInfo?->limit;
        $this->remaining = $remaining ?? $rateLimitInfo?->remaining;
        $this->reset = $reset ?? $rateLimitInfo?->reset;

        $this->rateLimitInfo = $rateLimitInfo ?? (
            ($this->limit !== null || $this->remaining !== null || $this->reset !== null || $this->retryAfter !== null)
                ? new RateLimitInfoDto($this->limit, $this->remaining, $this->reset, $this->retryAfter)
                : null
        );
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getRemaining(): ?int
    {
        return $this->remaining;
    }

    public function getReset(): ?int
    {
        return $this->reset;
    }

    public function getRateLimitInfo(): ?RateLimitInfoDto
    {
        return $this->rateLimitInfo;
    }
}
