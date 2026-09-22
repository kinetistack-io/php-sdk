<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Middleware;

use GuzzleHttp\Middleware;
use GuzzleHttp\RetryMiddleware;
use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RateLimitRetryMiddleware
{
    public const DEFAULT_MAX_RETRIES = 3;
    public const DEFAULT_BASE_DELAY_MS = 1000;
    public const DEFAULT_MAX_DELAY_MS = 60000;

    /** @var (callable(int, RequestInterface, ?ResponseInterface, ?\Throwable): bool)|null */
    private $decider;

    /** @var (callable(int, ?ResponseInterface, ?RequestInterface): int)|null */
    private $delay;

    /**
     * @param array<int, int> $retryStatusCodes
     * @param (callable(int, RequestInterface, ?ResponseInterface, ?\Throwable): bool)|null $decider
     * @param (callable(int, ?ResponseInterface, ?RequestInterface): int)|null $delay
     */
    public function __construct(
        private readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
        private readonly int $baseDelayMs = self::DEFAULT_BASE_DELAY_MS,
        private readonly int $maxDelayMs = self::DEFAULT_MAX_DELAY_MS,
        private readonly bool $enableJitter = true,
        ?callable $decider = null,
        ?callable $delay = null,
        private readonly array $retryStatusCodes = [429]
    ) {
        if (!class_exists(Middleware::class)) {
            throw new \LogicException('RateLimitRetryMiddleware requires "guzzlehttp/guzzle" to be installed.');
        }

        $this->decider = $decider;
        $this->delay = $delay;
    }

    /**
     * @param array<int, int> $retryStatusCodes
     * @param (callable(int, RequestInterface, ?ResponseInterface, ?\Throwable): bool)|null $decider
     * @param (callable(int, ?ResponseInterface, ?RequestInterface): int)|null $delay
     */
    public static function create(
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        int $baseDelayMs = self::DEFAULT_BASE_DELAY_MS,
        int $maxDelayMs = self::DEFAULT_MAX_DELAY_MS,
        bool $enableJitter = true,
        ?callable $decider = null,
        ?callable $delay = null,
        array $retryStatusCodes = [429]
    ): self {
        return new self(
            $maxRetries,
            $baseDelayMs,
            $maxDelayMs,
            $enableJitter,
            $decider,
            $delay,
            $retryStatusCodes
        );
    }

    /**
     * @param array<int, int> $retryStatusCodes
     * @param (callable(int, RequestInterface, ?ResponseInterface, ?\Throwable): bool)|null $decider
     * @param (callable(int, ?ResponseInterface, ?RequestInterface): int)|null $delay
     */
    public static function factory(
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        int $baseDelayMs = self::DEFAULT_BASE_DELAY_MS,
        int $maxDelayMs = self::DEFAULT_MAX_DELAY_MS,
        bool $enableJitter = true,
        ?callable $decider = null,
        ?callable $delay = null,
        array $retryStatusCodes = [429]
    ): callable {
        return self::create(
            $maxRetries,
            $baseDelayMs,
            $maxDelayMs,
            $enableJitter,
            $decider,
            $delay,
            $retryStatusCodes
        );
    }

    public function __invoke(callable $handler): RetryMiddleware
    {
        return Middleware::retry($this->buildDecider(), $this->buildDelay())($handler);
    }

    /**
     * @return callable(int, RequestInterface, ?ResponseInterface, ?\Throwable): bool
     */
    public function buildDecider(): callable
    {
        if ($this->decider !== null) {
            return $this->decider;
        }

        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Throwable $exception = null
        ): bool {
            if ($retries >= $this->maxRetries) {
                return false;
            }

            if ($response !== null && in_array($response->getStatusCode(), $this->retryStatusCodes, true)) {
                if ($response->hasHeader('Retry-After')) {
                    $rateLimitInfo = RateLimitInfoDto::fromHeaders($response->getHeaders());
                    if ($rateLimitInfo !== null && $rateLimitInfo->retryAfter !== null) {
                        $retryAfterMs = $rateLimitInfo->retryAfter * 1000;
                        if ($retryAfterMs > $this->maxDelayMs) {
                            return false;
                        }
                    }
                }

                return true;
            }

            return false;
        };
    }

    /**
     * @return callable(int, ?ResponseInterface, ?RequestInterface): int
     */
    public function buildDelay(): callable
    {
        if ($this->delay !== null) {
            return $this->delay;
        }

        return function (
            int $retries,
            ?ResponseInterface $response = null,
            ?RequestInterface $request = null
        ): int {
            if ($response !== null && $response->hasHeader('Retry-After')) {
                $rateLimitInfo = RateLimitInfoDto::fromHeaders($response->getHeaders());
                if ($rateLimitInfo !== null && $rateLimitInfo->retryAfter !== null) {
                    return max(0, $rateLimitInfo->retryAfter * 1000);
                }
            }

            $exponent = max(0, $retries - 1);
            $calculatedDelay = (int) ($this->baseDelayMs * (2 ** $exponent));

            if ($this->enableJitter && $calculatedDelay > 0) {
                $maxJitter = min(500, (int) round($calculatedDelay * 0.2));
                if ($maxJitter > 0) {
                    $calculatedDelay += random_int(0, $maxJitter);
                }
            }

            return min(max(0, $calculatedDelay), $this->maxDelayMs);
        };
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getBaseDelayMs(): int
    {
        return $this->baseDelayMs;
    }

    public function getMaxDelayMs(): int
    {
        return $this->maxDelayMs;
    }

    public function isJitterEnabled(): bool
    {
        return $this->enableJitter;
    }

    /**
     * @return array<int, int>
     */
    public function getRetryStatusCodes(): array
    {
        return $this->retryStatusCodes;
    }
}
