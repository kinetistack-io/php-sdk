<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Exception;

use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\RateLimitExceededException;
use KinetiStack\Sdk\Exception\RateLimitException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitExceededException::class)]
final class RateLimitExceededExceptionTest extends TestCase
{
    public function testInheritance(): void
    {
        $exception = new RateLimitExceededException();

        $this->assertInstanceOf(RateLimitException::class, $exception);
        $this->assertInstanceOf(KinetiException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Rate limit exceeded.', $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
    }

    public function testCustomMessageAndProperties(): void
    {
        $previous = new \RuntimeException('Root cause');
        $info = new RateLimitInfoDto(limit: 100, remaining: 0, reset: 1726950000, retryAfter: 30);

        $exception = new RateLimitExceededException(
            message: 'Too many requests, slow down.',
            retryAfter: 30,
            previous: $previous,
            limit: 100,
            remaining: 0,
            reset: 1726950000,
            rateLimitInfo: $info
        );

        $this->assertSame('Too many requests, slow down.', $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(30, $exception->retryAfter);
        $this->assertSame(30, $exception->getRetryAfter());
        $this->assertSame(100, $exception->limit);
        $this->assertSame(100, $exception->getLimit());
        $this->assertSame(0, $exception->remaining);
        $this->assertSame(0, $exception->getRemaining());
        $this->assertSame(1726950000, $exception->reset);
        $this->assertSame(1726950000, $exception->getReset());
        $this->assertSame($info, $exception->rateLimitInfo);
        $this->assertSame($info, $exception->getRateLimitInfo());
    }

    public function testFromRateLimitInfo(): void
    {
        $previous = new \RuntimeException('Inner');
        $info = new RateLimitInfoDto(limit: 60, remaining: 5, reset: 1726999999, retryAfter: 15);

        $exception = RateLimitExceededException::fromRateLimitInfo(
            message: 'Custom rate limit message',
            rateLimitInfo: $info,
            previous: $previous
        );

        $this->assertSame('Custom rate limit message', $exception->getMessage());
        $this->assertSame(429, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(15, $exception->getRetryAfter());
        $this->assertSame(60, $exception->getLimit());
        $this->assertSame(5, $exception->getRemaining());
        $this->assertSame(1726999999, $exception->getReset());
        $this->assertSame($info, $exception->getRateLimitInfo());
    }

    public function testFromRateLimitInfoWithNull(): void
    {
        $exception = RateLimitExceededException::fromRateLimitInfo();

        $this->assertSame('Rate limit exceeded.', $exception->getMessage());
        $this->assertNull($exception->getRetryAfter());
        $this->assertNull($exception->getLimit());
        $this->assertNull($exception->getRemaining());
        $this->assertNull($exception->getReset());
        $this->assertNull($exception->getRateLimitInfo());
    }
}
