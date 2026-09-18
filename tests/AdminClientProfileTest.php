<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\AdminClient;
use KinetiStack\Sdk\Dto\UserDto;
use KinetiStack\Sdk\Exception\ConflictException;
use KinetiStack\Sdk\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AdminClientProfileTest extends TestCase
{
    public function testGetMeSuccess(): void
    {
        $userData = [
            'id' => 'user-me-123',
            'email' => 'caller@agency.com',
            'role' => 'member',
            'roles' => ['ROLE_USER'],
            'must_change_password' => false,
            'created_at' => '2026-09-18T10:00:00+00:00',
        ];

        $mockResponse = new MockResponse(json_encode($userData, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $user = $client->getMe();

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/me', $mockResponse->getRequestUrl());

        $headers = $mockResponse->getRequestOptions()['headers'] ?? [];
        $authHeaders = array_filter($headers, static fn ($h) => str_starts_with((string) $h, 'Authorization:'));
        $this->assertNotEmpty($authHeaders);
        $this->assertSame('Authorization: Bearer mock-jwt-token', reset($authHeaders));

        $this->assertInstanceOf(UserDto::class, $user);
        $this->assertSame('user-me-123', $user->id);
        $this->assertSame('caller@agency.com', $user->email);
        $this->assertSame('ROLE_USER', $user->getRole());
        $this->assertTrue($user->hasRole('ROLE_USER'));
        $this->assertFalse($user->mustChangePassword);
        $this->assertSame('2026-09-18T10:00:00+00:00', $user->createdAt);
    }

    public function testUpdateMeSuccess(): void
    {
        $updatedData = [
            'id' => 'user-me-123',
            'email' => 'new-email@agency.com',
            'role' => 'member',
            'roles' => ['ROLE_USER'],
            'must_change_password' => false,
            'created_at' => '2026-09-18T10:00:00+00:00',
        ];

        $mockResponse = new MockResponse(json_encode($updatedData, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $user = $client->updateMe(['email' => 'new-email@agency.com']);

        $this->assertSame('PATCH', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/me', $mockResponse->getRequestUrl());

        $headers = $mockResponse->getRequestOptions()['headers'] ?? [];
        $contentTypeHeaders = array_filter($headers, static fn ($h) => str_starts_with((string) $h, 'Content-Type:'));
        $this->assertNotEmpty($contentTypeHeaders);
        $this->assertSame('Content-Type: application/merge-patch+json', reset($contentTypeHeaders));

        /** @var string $body */
        $body = $mockResponse->getRequestOptions()['body'];
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['email' => 'new-email@agency.com'], $payload);

        $this->assertInstanceOf(UserDto::class, $user);
        $this->assertSame('user-me-123', $user->id);
        $this->assertSame('new-email@agency.com', $user->email);
    }

    public function testUpdateMeConflictThrowsConflictException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Conflict',
            'status' => 409,
            'detail' => 'A user with this email address already exists.',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 409,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('A user with this email address already exists.');

        $client->updateMe(['email' => 'duplicate@agency.com']);
    }

    public function testUpdateMeValidationFailsThrowsValidationException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'Invalid email address.',
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'Invalid email address.',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 422,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid email address.');

        $client->updateMe(['email' => 'invalid-format']);
    }

    public function testUpdateMyPasswordSuccessWith200(): void
    {
        $mockResponse = new MockResponse(json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $client->updateMyPassword('correctCurrentPass', 'NewSecurePass123!');

        $this->assertSame('PATCH', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/me/password', $mockResponse->getRequestUrl());

        $headers = $mockResponse->getRequestOptions()['headers'] ?? [];
        $contentTypeHeaders = array_filter($headers, static fn ($h) => str_starts_with((string) $h, 'Content-Type:'));
        $this->assertNotEmpty($contentTypeHeaders);
        $this->assertSame('Content-Type: application/merge-patch+json', reset($contentTypeHeaders));

        /** @var string $body */
        $body = $mockResponse->getRequestOptions()['body'];
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'currentPassword' => 'correctCurrentPass',
            'newPassword' => 'NewSecurePass123!',
        ], $payload);
    }

    public function testUpdateMyPasswordSuccessWith204(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 204,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $client->updateMyPassword('correctCurrentPass', 'NewSecurePass123!');

        $this->assertSame('PATCH', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/me/password', $mockResponse->getRequestUrl());
    }

    public function testUpdateMyPasswordIncorrectPasswordThrowsValidationException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'Incorrect current password.',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 422,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Incorrect current password.');

        $client->updateMyPassword('wrongCurrentPass', 'NewSecurePass123!');
    }

    public function testUpdateMyPasswordTooShortThrowsValidationException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'New password must be at least 8 characters long.',
            'violations' => [
                [
                    'propertyPath' => 'newPassword',
                    'message' => 'New password must be at least 8 characters long.',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 422,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        try {
            $client->updateMyPassword('correctCurrentPass', 'short');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame('New password must be at least 8 characters long.', $e->getMessage());
            $this->assertCount(1, $e->getViolations());
            $this->assertSame('newPassword', $e->getViolations()[0]['propertyPath']);
            $this->assertSame('New password must be at least 8 characters long.', $e->getViolations()[0]['message']);
        }
    }
}
