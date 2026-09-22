<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\ConflictException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ServiceModuleDisabledException;
use KinetiStack\Sdk\Transport\ResponseErrorHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseErrorHandler::class)]
final class ResponseErrorHandlerTest extends TestCase
{
    public function testHandleErrorThrowsServiceModuleDisabledExceptionWhenModulePresent(): void
    {
        $body = json_encode([
            'type' => 'https://kinetistack.io/errors/module-disabled',
            'title' => 'Service Module Disabled',
            'status' => 403,
            'detail' => "Service module 'rag' is not enabled for your organization.",
            'module' => 'rag',
        ], JSON_THROW_ON_ERROR);

        try {
            $this->invokeErrorHandler(403, ['Content-Type' => ['application/problem+json']], $body);
            $this->fail('Expected ServiceModuleDisabledException to be thrown');
        } catch (ServiceModuleDisabledException $e) {
            $this->assertSame("Service module 'rag' is not enabled for your organization.", $e->getMessage());
            $this->assertSame('rag', $e->moduleIdentifier);
            $this->assertSame('rag', $e->getModuleIdentifier());
            $this->assertSame(403, $e->getCode());
        }
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     */
    private function invokeErrorHandler(int $statusCode, array $headers, string $body): void
    {
        ResponseErrorHandler::handleError($statusCode, $headers, $body);
    }

    public function testHandleErrorThrowsAuthorizationExceptionWhenModuleNotPresent(): void
    {
        $body = json_encode([
            'type' => 'https://kinetistack.io/errors/forbidden',
            'title' => 'Access Denied',
            'status' => 403,
            'detail' => 'You do not have access to this resource.',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You do not have access to this resource.');

        ResponseErrorHandler::handleError(403, ['Content-Type' => ['application/problem+json']], $body);
    }

    public function testHandleErrorThrowsAuthorizationExceptionWhenModuleIsEmptyString(): void
    {
        $body = json_encode([
            'status' => 403,
            'detail' => 'Forbidden',
            'module' => '   ',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Forbidden');

        ResponseErrorHandler::handleError(403, ['Content-Type' => ['application/problem+json']], $body);
    }

    public function testHandleErrorThrowsConflictExceptionOn409(): void
    {
        $body = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Conflict',
            'status' => 409,
            'detail' => 'An account with email admin@acme.com already exists.',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('An account with email admin@acme.com already exists.');

        ResponseErrorHandler::handleError(409, ['Content-Type' => ['application/problem+json']], $body);
    }

    public function testHandleErrorThrowsRateLimitExceptionWithParsedHeaders(): void
    {
        $body = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'Rate limit exceeded.',
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => ['application/problem+json'],
            'X-RateLimit-Limit' => ['100'],
            'X-RateLimit-Remaining' => ['0'],
            'X-RateLimit-Reset' => ['1726955000'],
            'Retry-After' => ['45'],
        ];

        try {
            $this->invokeErrorHandler(429, $headers, $body);
            $this->fail('Expected RateLimitException to be thrown');
        } catch (RateLimitException $e) {
            $this->assertSame('Rate limit exceeded.', $e->getMessage());
            $this->assertSame(429, $e->getCode());
            $this->assertSame(100, $e->getLimit());
            $this->assertSame(100, $e->limit);
            $this->assertSame(0, $e->getRemaining());
            $this->assertSame(0, $e->remaining);
            $this->assertSame(1726955000, $e->getReset());
            $this->assertSame(1726955000, $e->reset);
            $this->assertSame(45, $e->getRetryAfter());
            $this->assertSame(45, $e->retryAfter);

            $info = $e->getRateLimitInfo();
            $this->assertNotNull($info);
            $this->assertSame(100, $info->limit);
            $this->assertSame(0, $info->remaining);
            $this->assertSame(1726955000, $info->reset);
            $this->assertSame(45, $info->retryAfter);
        }
    }

    public function testHandleErrorThrowsRateLimitExceptionWithoutHeaders(): void
    {
        $body = json_encode([
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'Rate limit exceeded.',
        ], JSON_THROW_ON_ERROR);

        try {
            $this->invokeErrorHandler(429, ['Content-Type' => ['application/problem+json']], $body);
            $this->fail('Expected RateLimitException to be thrown');
        } catch (RateLimitException $e) {
            $this->assertSame('Rate limit exceeded.', $e->getMessage());
            $this->assertNull($e->getLimit());
            $this->assertNull($e->limit);
            $this->assertNull($e->getRemaining());
            $this->assertNull($e->remaining);
            $this->assertNull($e->getReset());
            $this->assertNull($e->reset);
            $this->assertNull($e->getRetryAfter());
            $this->assertNull($e->retryAfter);
            $this->assertNull($e->getRateLimitInfo());
        }
    }

    public function testParseRateLimitInfoHelper(): void
    {
        $headers = [
            'X-RateLimit-Limit' => ['60'],
            'X-RateLimit-Remaining' => ['50'],
        ];

        $info = ResponseErrorHandler::parseRateLimitInfo($headers);
        $this->assertNotNull($info);
        $this->assertSame(60, $info->limit);
        $this->assertSame(50, $info->remaining);
        $this->assertNull($info->reset);
    }
}
