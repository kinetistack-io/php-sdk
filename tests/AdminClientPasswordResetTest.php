<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\AdminClient;
use KinetiStack\Sdk\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AdminClientPasswordResetTest extends TestCase
{
    public function testRequestPasswordResetSuccess(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 202,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', '', $httpClient);

        $client->requestPasswordReset('user@agency.com');

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/password-reset-requests', $mockResponse->getRequestUrl());

        /** @var string $body */
        $body = $mockResponse->getRequestOptions()['body'];
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertSame(['email' => 'user@agency.com'], $payload);
    }

    public function testRequestPasswordResetValidationFails(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'This value is not a valid email address.',
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'This value is not a valid email address.',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 422,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', '', $httpClient);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This value is not a valid email address.');

        $client->requestPasswordReset('invalid-email');
    }

    public function testResetPasswordSuccessWith204(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 204,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', '', $httpClient);

        $client->resetPassword('TOKEN123', 'NewSecurePassword1!');

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/password-resets', $mockResponse->getRequestUrl());

        /** @var string $body */
        $body = $mockResponse->getRequestOptions()['body'];
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertSame([
            'token' => 'TOKEN123',
            'password' => 'NewSecurePassword1!',
        ], $payload);
    }

    public function testResetPasswordSuccessWith200(): void
    {
        $mockResponse = new MockResponse(json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', '', $httpClient);

        $client->resetPassword('TOKEN123', 'NewSecurePassword1!');

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/password-resets', $mockResponse->getRequestUrl());
    }

    public function testResetPasswordInvalidOrExpiredTokenThrowsValidationException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'The reset password token is invalid or has expired.',
            'violations' => [],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 422,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', '', $httpClient);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The reset password token is invalid or has expired.');

        $client->resetPassword('EXPIRED_TOKEN', 'NewPassword1!');
    }

    public function testResetPasswordWeakPasswordThrowsValidationException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'This value is too short. It should have 12 characters or more.',
            'violations' => [
                [
                    'propertyPath' => 'password',
                    'message' => 'This value is too short. It should have 12 characters or more.',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 422,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', '', $httpClient);

        try {
            $client->resetPassword('VALID_TOKEN', 'short');
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('This value is too short. It should have 12 characters or more.', $e->getMessage());
            $this->assertCount(1, $e->getViolations());
            $this->assertSame('password', $e->getViolations()[0]['propertyPath']);
        }
    }
}
