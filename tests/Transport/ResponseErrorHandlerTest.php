<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\ConflictException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\NotFoundException;
use KinetiStack\Sdk\Exception\PayloadTooLargeException;
use KinetiStack\Sdk\Exception\RateLimitExceededException;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\Exception\ServiceModuleDisabledException;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\Transport\ResponseErrorHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseErrorHandler::class)]
#[UsesClass(RateLimitInfoDto::class)]
#[UsesClass(AuthenticationException::class)]
#[UsesClass(AuthorizationException::class)]
#[UsesClass(ConflictException::class)]
#[UsesClass(KinetiException::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(PayloadTooLargeException::class)]
#[UsesClass(RateLimitExceededException::class)]
#[UsesClass(ServerException::class)]
#[UsesClass(ServiceModuleDisabledException::class)]
#[UsesClass(ServiceUnavailableException::class)]
#[UsesClass(ValidationException::class)]
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

    public function testHandleErrorThrowsRateLimitExceededExceptionWithParsedHeaders(): void
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
            $this->fail('Expected RateLimitExceededException to be thrown');
        } catch (RateLimitExceededException $e) {
            $this->assertInstanceOf(KinetiException::class, $e);
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

    public function testHandleErrorThrowsRateLimitExceededExceptionWithoutHeaders(): void
    {
        $body = json_encode([
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'Rate limit exceeded.',
        ], JSON_THROW_ON_ERROR);

        try {
            $this->invokeErrorHandler(429, ['Content-Type' => ['application/problem+json']], $body);
            $this->fail('Expected RateLimitExceededException to be thrown');
        } catch (RateLimitExceededException $e) {
            $this->assertInstanceOf(KinetiException::class, $e);
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

    /**
     * Scenario 2: Non-String detail in Error Payload & Exception Status Codes
     *
     * Given an error response with HTTP status 401 (or 418) and body {"detail": ["unexpected", "array"], "title": 123}
     * When ResponseErrorHandler::handleError() processes the response
     * Then it throws AuthenticationException with $e->getCode() === 401 (or KinetiException with $e->getCode() === 418)
     * and a fallback string message instead of a fatal \TypeError.
     */
    public function testNonStringDetailOrTitleDoesNotThrowTypeErrorAndFallsBackToString(): void
    {
        $payload = json_encode([
            'detail' => ['unexpected', 'array'],
            'title' => 123,
        ], JSON_THROW_ON_ERROR);

        try {
            $this->invokeErrorHandler(401, ['Content-Type' => ['application/problem+json']], $payload);
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertSame('Unknown error occurred', $e->getMessage());
        }

        try {
            $this->invokeErrorHandler(418, ['Content-Type' => ['application/problem+json']], $payload);
            $this->fail('Expected KinetiException');
        } catch (KinetiException $e) {
            $this->assertSame(418, $e->getCode());
            $this->assertSame('API Error 418: Unknown error occurred', $e->getMessage());
        }
    }

    /**
     * @param class-string<\Throwable> $expectedClass
     */
    #[DataProvider('provideExceptionStatusCodeCases')]
    public function testExceptionStatusCodesMatchExpectedHttpCodes(
        int $statusCode,
        string $body,
        string $expectedClass,
        int $expectedCode
    ): void {
        $this->expectException($expectedClass);
        $this->expectExceptionCode($expectedCode);

        ResponseErrorHandler::handleError($statusCode, ['Content-Type' => ['application/problem+json']], $body);
    }

    /**
     * @return array<string, array{0: int, 1: string, 2: class-string<\Throwable>, 3: int}>
     */
    public static function provideExceptionStatusCodeCases(): array
    {
        return [
            '401 Unauthorized' => [
                401,
                json_encode(['detail' => 'Invalid credentials'], JSON_THROW_ON_ERROR),
                AuthenticationException::class,
                401,
            ],
            '403 Forbidden without module' => [
                403,
                json_encode(['detail' => 'Forbidden resource'], JSON_THROW_ON_ERROR),
                AuthorizationException::class,
                403,
            ],
            '403 Service Module Disabled' => [
                403,
                json_encode(['detail' => 'Module disabled', 'module' => 'rag'], JSON_THROW_ON_ERROR),
                ServiceModuleDisabledException::class,
                403,
            ],
            '404 Not Found' => [
                404,
                json_encode(['detail' => 'Resource not found'], JSON_THROW_ON_ERROR),
                NotFoundException::class,
                404,
            ],
            '409 Conflict' => [
                409,
                json_encode(['detail' => 'Resource already exists'], JSON_THROW_ON_ERROR),
                ConflictException::class,
                409,
            ],
            '413 Payload Too Large' => [
                413,
                json_encode(['detail' => 'Request body too large'], JSON_THROW_ON_ERROR),
                PayloadTooLargeException::class,
                413,
            ],
            '422 Validation Error' => [
                422,
                json_encode(['detail' => 'Validation failed', 'violations' => []], JSON_THROW_ON_ERROR),
                ValidationException::class,
                422,
            ],
            '429 Rate Limit' => [
                429,
                json_encode(['detail' => 'Too many requests'], JSON_THROW_ON_ERROR),
                RateLimitExceededException::class,
                429,
            ],
            '500 Server Error' => [
                500,
                json_encode(['detail' => 'Internal server error'], JSON_THROW_ON_ERROR),
                ServerException::class,
                500,
            ],
            '503 Service Unavailable' => [
                503,
                json_encode(['detail' => 'Service temporarily unavailable'], JSON_THROW_ON_ERROR),
                ServiceUnavailableException::class,
                503,
            ],
            '418 Fallback default' => [
                418,
                json_encode(['detail' => 'I am a teapot'], JSON_THROW_ON_ERROR),
                KinetiException::class,
                418,
            ],
        ];
    }
}
