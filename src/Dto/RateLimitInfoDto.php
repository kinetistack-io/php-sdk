<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class RateLimitInfoDto
{
    public function __construct(
        public readonly ?int $limit = null,
        public readonly ?int $remaining = null,
        public readonly ?int $reset = null,
        public readonly ?int $retryAfter = null,
    ) {
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

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * @return array{limit: ?int, remaining: ?int, reset: ?int, retry_after: ?int}
     */
    public function toArray(): array
    {
        return [
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'reset' => $this->reset,
            'retry_after' => $this->retryAfter,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $limit = isset($data['limit']) && is_numeric($data['limit']) ? (int) $data['limit'] : null;
        $remaining = isset($data['remaining']) && is_numeric($data['remaining']) ? (int) $data['remaining'] : null;
        $reset = isset($data['reset']) && is_numeric($data['reset']) ? (int) $data['reset'] : null;
        $retryAfter = isset($data['retry_after']) && is_numeric($data['retry_after'])
            ? (int) $data['retry_after']
            : (isset($data['retryAfter']) && is_numeric($data['retryAfter']) ? (int) $data['retryAfter'] : null);

        return new self($limit, $remaining, $reset, $retryAfter);
    }

    /**
     * Parse rate limit information from an array of HTTP response headers.
     * Returns null if no rate limit or retry headers are present.
     *
     * @param array<array-key, mixed> $headers
     */
    public static function fromHeaders(array $headers): ?self
    {
        $limit = null;
        $remaining = null;
        $reset = null;
        $retryAfter = null;

        $hasAnyHeader = false;

        foreach ($headers as $name => $values) {
            $nameLower = strtolower(trim((string) $name));
            $first = is_array($values) ? reset($values) : $values;
            if ($first === false || $first === null) {
                continue;
            }
            $raw = trim((string) $first);
            if ($raw === '') {
                continue;
            }

            if (in_array($nameLower, ['x-ratelimit-limit', 'ratelimit-limit'], true)) {
                if (is_numeric($raw)) {
                    $limit = (int) $raw;
                    $hasAnyHeader = true;
                }
            } elseif (in_array($nameLower, ['x-ratelimit-remaining', 'ratelimit-remaining'], true)) {
                if (is_numeric($raw)) {
                    $remaining = (int) $raw;
                    $hasAnyHeader = true;
                }
            } elseif (in_array($nameLower, ['x-ratelimit-reset', 'ratelimit-reset'], true)) {
                if (is_numeric($raw)) {
                    $reset = (int) $raw;
                    $hasAnyHeader = true;
                } else {
                    $timestamp = strtotime($raw);
                    if ($timestamp !== false) {
                        $reset = $timestamp;
                        $hasAnyHeader = true;
                    }
                }
            } elseif ($nameLower === 'retry-after') {
                if (is_numeric($raw)) {
                    $retryAfter = max(0, (int) $raw);
                    $hasAnyHeader = true;
                } else {
                    $timestamp = strtotime($raw);
                    if ($timestamp !== false) {
                        $retryAfter = max(0, $timestamp - time());
                        $hasAnyHeader = true;
                    }
                }
            }
        }

        if (!$hasAnyHeader) {
            return null;
        }

        return new self($limit, $remaining, $reset, $retryAfter);
    }
}
