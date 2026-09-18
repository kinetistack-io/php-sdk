<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\AdminClient;
use KinetiStack\Sdk\Dto\UserDto;
use KinetiStack\Sdk\Dto\UserProjectAssignmentDto;
use KinetiStack\Sdk\Exception\ConflictException;
use KinetiStack\Sdk\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AdminClientUserTest extends TestCase
{
    public function testListUsersWithHydraCollection(): void
    {
        $hydraData = [
            '@context' => '/api/v1/contexts/User',
            '@id' => '/api/v1/admin/users',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 2,
            'hydra:member' => [
                [
                    '@id' => '/api/v1/admin/users/user-1',
                    '@type' => 'User',
                    'id' => 'user-1',
                    'email' => 'admin@example.com',
                    'role' => 'admin',
                    'must_change_password' => false,
                    'created_at' => '2026-09-18T08:00:00+00:00',
                ],
                [
                    '@id' => '/api/v1/admin/users/user-2',
                    '@type' => 'User',
                    'id' => 'user-2',
                    'email' => 'member@example.com',
                    'role' => 'member',
                    'must_change_password' => true,
                    'created_at' => '2026-09-18T08:30:00+00:00',
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($hydraData, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/ld+json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $users = $client->listUsers();

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/users', $mockResponse->getRequestUrl());
        $this->assertCount(2, $users);

        $this->assertInstanceOf(UserDto::class, $users[0]);
        $this->assertSame('user-1', $users[0]->id);
        $this->assertSame('admin@example.com', $users[0]->email);
        $this->assertSame('admin', $users[0]->getRole());
        $this->assertFalse($users[0]->mustChangePassword);
        $this->assertSame('2026-09-18T08:00:00+00:00', $users[0]->createdAt);

        $this->assertInstanceOf(UserDto::class, $users[1]);
        $this->assertSame('user-2', $users[1]->id);
        $this->assertSame('member@example.com', $users[1]->email);
        $this->assertSame('member', $users[1]->getRole());
        $this->assertTrue($users[1]->mustChangePassword);
    }

    public function testListUsersWithOptions(): void
    {
        $mockResponse = new MockResponse(json_encode(['hydra:member' => []], JSON_THROW_ON_ERROR));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $client->listUsers(['page' => 2, 'itemsPerPage' => 10]);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/users?page=2&itemsPerPage=10', $mockResponse->getRequestUrl());
    }

    public function testGetUser(): void
    {
        $userData = [
            'id' => 'user-123',
            'email' => 'target@example.com',
            'role' => 'admin',
            'must_change_password' => false,
            'created_at' => '2026-09-18T09:00:00+00:00',
        ];

        $mockResponse = new MockResponse(json_encode($userData, JSON_THROW_ON_ERROR));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $user = $client->getUser('user-123');

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/users/user-123', $mockResponse->getRequestUrl());
        $this->assertSame('user-123', $user->id);
        $this->assertSame('target@example.com', $user->email);
        $this->assertSame('admin', $user->getRole());
    }

    public function testCreateUserSuccess(): void
    {
        $createdData = [
            'id' => 'new-user-id',
            'email' => 'new@example.com',
            'role' => 'member',
            'must_change_password' => true,
            'created_at' => '2026-09-18T09:15:00+00:00',
        ];

        $mockResponse = new MockResponse(json_encode($createdData, JSON_THROW_ON_ERROR), [
            'http_code' => 201,
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $user = $client->createUser([
            'email' => 'new@example.com',
            'role' => 'member',
            'password' => 'secretPassword123!',
        ]);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/users', $mockResponse->getRequestUrl());
        $this->assertSame('new-user-id', $user->id);
        $this->assertSame('new@example.com', $user->email);
        $this->assertTrue($user->mustChangePassword);

        /** @var string $body */
        $body = $mockResponse->getRequestOptions()['body'];
        $payload = json_decode($body, true);
        $this->assertSame('new@example.com', $payload['email']);
    }

    public function testCreateUserConflictThrowsConflictException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Conflict',
            'status' => 409,
            'detail' => 'User with this email already exists.',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 409,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('User with this email already exists.');

        $client->createUser(['email' => 'existing@example.com', 'role' => 'admin']);
    }

    public function testUpdateUserSuccess(): void
    {
        $updatedData = [
            'id' => 'user-123',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'must_change_password' => false,
            'created_at' => '2026-09-18T08:00:00+00:00',
        ];

        $mockResponse = new MockResponse(json_encode($updatedData, JSON_THROW_ON_ERROR));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $user = $client->updateUser('user-123', ['role' => 'admin']);

        $this->assertSame('PATCH', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/users/user-123', $mockResponse->getRequestUrl());
        $this->assertSame('admin', $user->getRole());

        $headers = $mockResponse->getRequestOptions()['headers'];
        $this->assertContains('Content-Type: application/merge-patch+json', $headers);
    }

    public function testUpdateUserValidationThrowsValidationException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'Cannot demote the last organization administrator.',
            'violations' => [
                [
                    'propertyPath' => 'role',
                    'message' => 'Cannot demote the last administrator.',
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
            $client->updateUser('user-123', ['role' => 'member']);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame('Cannot demote the last organization administrator.', $e->getMessage());
            $this->assertCount(1, $e->getViolations());
            $this->assertSame('role', $e->getViolations()[0]['propertyPath']);
        }
    }

    public function testDeleteUser(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $client->deleteUser('user-123');

        $this->assertSame('DELETE', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/users/user-123', $mockResponse->getRequestUrl());
    }

    public function testListProjectMembers(): void
    {
        $membersData = [
            'hydra:member' => [
                [
                    'user_id' => 'user-1',
                    'email' => 'user1@example.com',
                    'permission' => 'manager',
                    'assigned_at' => '2026-09-18T08:00:00+00:00',
                ],
                [
                    'user_id' => 'user-2',
                    'email' => 'user2@example.com',
                    'permission' => 'member',
                    'assigned_at' => '2026-09-18T08:30:00+00:00',
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($membersData, JSON_THROW_ON_ERROR));
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $members = $client->listProjectMembers('proj-123');

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/projects/proj-123/members', $mockResponse->getRequestUrl());
        $this->assertCount(2, $members);

        $this->assertInstanceOf(UserProjectAssignmentDto::class, $members[0]);
        $this->assertSame('user-1', $members[0]->userId);
        $this->assertSame('proj-123', $members[0]->projectId);
        $this->assertSame('manager', $members[0]->permission);
        $this->assertSame('user1@example.com', $members[0]->email);

        $this->assertInstanceOf(UserProjectAssignmentDto::class, $members[1]);
        $this->assertSame('user-2', $members[1]->userId);
        $this->assertSame('proj-123', $members[1]->projectId);
        $this->assertSame('member', $members[1]->permission);
    }

    public function testAssignProjectMember(): void
    {
        $responseData = [
            'user_id' => 'user-456',
            'permission' => 'manager',
            'assigned_at' => '2026-09-18T09:00:00+00:00',
            'email' => 'user456@example.com',
        ];

        $mockResponse = new MockResponse(json_encode($responseData, JSON_THROW_ON_ERROR), [
            'http_code' => 201,
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $assignment = $client->assignProjectMember('proj-123', [
            'userId' => 'user-456',
            'permission' => 'manager',
        ]);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/projects/proj-123/members', $mockResponse->getRequestUrl());
        $this->assertSame('user-456', $assignment->userId);
        $this->assertSame('proj-123', $assignment->projectId);
        $this->assertSame('manager', $assignment->permission);
        $this->assertSame('2026-09-18T09:00:00+00:00', $assignment->assignedAt);
        $this->assertSame('user456@example.com', $assignment->email);

        /** @var string $body */
        $body = $mockResponse->getRequestOptions()['body'];
        $sentPayload = json_decode($body, true);
        $this->assertSame('user-456', $sentPayload['user_id']);
        $this->assertArrayNotHasKey('userId', $sentPayload);
        $this->assertSame('manager', $sentPayload['permission']);
    }

    public function testRemoveProjectMember(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AdminClient('https://api.test', 'mock-jwt-token', $httpClient);

        $client->removeProjectMember('proj-123', 'user-456');

        $this->assertSame('DELETE', $mockResponse->getRequestMethod());
        $this->assertSame(
            'https://api.test/api/v1/admin/projects/proj-123/members/user-456',
            $mockResponse->getRequestUrl()
        );
    }
}
