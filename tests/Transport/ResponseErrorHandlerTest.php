<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\ConflictException;
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
}
