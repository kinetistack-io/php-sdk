<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\AdminClient;
use KinetiStack\Sdk\AdminClientInterface;
use KinetiStack\Sdk\Client\AdminClientInterface as BaseAdminClientInterface;
use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Dto\ApiKeyCreatedDto;
use KinetiStack\Sdk\Dto\ApiKeyDto;
use KinetiStack\Sdk\Dto\AuthTokenDto;
use KinetiStack\Sdk\Dto\OrganizationDto;
use KinetiStack\Sdk\Dto\ProjectDto;
use KinetiStack\Sdk\Dto\RegisterDto;
use KinetiStack\Sdk\Dto\RegisterResponseDto;
use KinetiStack\Sdk\Dto\RegistrationStatusDto;
use KinetiStack\Sdk\Dto\UsageSummaryDto;
use KinetiStack\Sdk\Enum\RegistrationMode;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\ConflictException;
use KinetiStack\Sdk\Exception\NotFoundException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\Transport\SymfonyTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AdminClientTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $client = new AdminClient('https://api.test', 'initial-jwt');
        $this->assertSame('https://api.test', $client->getApiHost());
        $this->assertSame('initial-jwt', $client->getJwtToken());
        $this->assertInstanceOf(\KinetiStack\Sdk\Transport\TransportInterface::class, $client->getTransport());
    }

    public function testImplementsInterfaces(): void
    {
        $client = new AdminClient('https://api.test', 'initial-jwt');
        $this->assertInstanceOf(AdminClientInterface::class, $client);
        $this->assertInstanceOf(BaseAdminClientInterface::class, $client);
    }

    /**
     * S2: withToken('new-jwt') returns a new immutable instance; original is unchanged.
     */
    public function testWithTokenImmutability(): void
    {
        $original = new AdminClient('https://api.test', 'original-token');
        $updated = $original->withToken('updated-token');

        $this->assertNotSame($original, $updated);
        $this->assertSame('original-token', $original->getJwtToken());
        $this->assertSame('updated-token', $updated->getJwtToken());

        // Verify transport headers on both instances
        $mockResponse1 = new MockResponse(json_encode(['id' => 'org-1', 'name' => 'Org 1'], JSON_THROW_ON_ERROR));
        $mockResponse2 = new MockResponse(json_encode(['id' => 'org-1', 'name' => 'Org 1'], JSON_THROW_ON_ERROR));

        $httpClient1 = new MockHttpClient($mockResponse1);
        $httpClient2 = new MockHttpClient($mockResponse2);

        $clientA = new AdminClient('https://api.test', 'token-A', $httpClient1);
        $clientB = $clientA->withToken('token-B');

        $clientA->getOrganization('org-1');
        $this->assertContains('Authorization: Bearer token-A', $mockResponse1->getRequestOptions()['headers']);

        // Set clientB with a separate mock to inspect its header
        $clientBWithMock = new AdminClient('https://api.test', 'token-A', $httpClient2);
        $clientBWithMock = $clientBWithMock->withToken('token-B');
        $clientBWithMock->getOrganization('org-1');
        $this->assertContains('Authorization: Bearer token-B', $mockResponse2->getRequestOptions()['headers']);
    }

    public function testWithTokenWithCustomTransport(): void
    {
        $mockResponse1 = new MockResponse(json_encode(['id' => 'org-1', 'name' => 'Org 1'], JSON_THROW_ON_ERROR));
        $mockResponse2 = new MockResponse(json_encode(['id' => 'org-1', 'name' => 'Org 1'], JSON_THROW_ON_ERROR));

        $client1 = new MockHttpClient($mockResponse1);
        $client2 = new MockHttpClient($mockResponse2);

        $customTransport1 = new SymfonyTransport('https://api.test', 'Authorization', 'Bearer initial-token', $client1);

        $admin = new AdminClient('https://api.test', 'initial-token', $customTransport1);
        $this->assertSame('initial-token', $admin->getJwtToken());

        $adminUpdated = $admin->withToken('updated-token');
        $this->assertNotSame($admin, $adminUpdated);
        $this->assertSame('initial-token', $admin->getJwtToken());
        $this->assertSame('updated-token', $adminUpdated->getJwtToken());

        $admin->getOrganization('org-1');
        $this->assertContains('Authorization: Bearer initial-token', $mockResponse1->getRequestOptions()['headers']);

        $customTransport2 = new SymfonyTransport('https://api.test', 'Authorization', 'Bearer initial-token', $client2);
        $admin2 = new AdminClient('https://api.test', 'initial-token', $customTransport2);
        $admin2Updated = $admin2->withToken('updated-token');
        $admin2Updated->getOrganization('org-1');
        $this->assertContains('Authorization: Bearer updated-token', $mockResponse2->getRequestOptions()['headers']);

        $adminCleared = $admin2Updated->withToken('');
        $this->assertSame('', $adminCleared->getJwtToken());
        $transport = $adminCleared->getTransport();
        $this->assertInstanceOf(SymfonyTransport::class, $transport);
        $this->assertSame('', $transport->getAuthHeaderValue());
    }

    public function testLogin(): void
    {
        $responseBody = json_encode([
            'token' => 'newly-issued-jwt',
            'refresh_token' => null,
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', '', $httpClient);

        $auth = $admin->login('admin@agency.com', 'SuperSecret123!');

        $this->assertInstanceOf(AuthTokenDto::class, $auth);
        $this->assertSame('newly-issued-jwt', $auth->token);
        $this->assertNull($auth->refreshToken);
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/login', $mockResponse->getRequestUrl());

        // Login should NOT send Bearer token since it was unauthenticated
        $headers = $mockResponse->getRequestOptions()['headers'] ?? [];
        foreach ($headers as $h) {
            $this->assertStringStartsNotWith('authorization:', strtolower((string) $h));
        }

        /** @var array<string, mixed> $sentBody */
        $sentBody = json_decode((string) $mockResponse->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('admin@agency.com', $sentBody['email']);
        $this->assertSame('SuperSecret123!', $sentBody['password']);
    }

    public function testRefreshToken(): void
    {
        $responseBody = json_encode([
            'token' => 'refreshed-admin-jwt',
            'refresh_token' => null,
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'valid-admin-jwt', $httpClient);

        $auth = $admin->refreshToken();

        $this->assertInstanceOf(AuthTokenDto::class, $auth);
        $this->assertSame('refreshed-admin-jwt', $auth->token);
        $this->assertNull($auth->refreshToken);
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/token/refresh', $mockResponse->getRequestUrl());
        $this->assertContains('Authorization: Bearer valid-admin-jwt', $mockResponse->getRequestOptions()['headers']);
    }

    public function testRefreshTokenUnauthorizedThrowsAuthenticationException(): void
    {
        $problemJson = json_encode([
            'type' => 'urn:problem-type:unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Invalid or expired JWT token',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($problemJson, [
            'http_code' => 401,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'expired-jwt', $httpClient);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid or expired JWT token');

        $admin->refreshToken();
    }

    public function testRegisterSuccess(): void
    {
        $responseBody = json_encode([
            'organization' => [
                'id' => 'org-uuid-1',
                'name' => 'Acme Agency',
                'billing_tier' => 'free',
            ],
            'user' => [
                'id' => 'user-uuid-1',
                'email' => 'admin@acme.com',
                'roles' => ['ROLE_ADMIN'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 201]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', '', $httpClient);

        $result = $admin->register('Acme Agency', 'admin@acme.com', 'SecurePass1!');

        $this->assertInstanceOf(RegisterResponseDto::class, $result);
        $this->assertSame('org-uuid-1', $result->getOrganizationId());
        $this->assertSame('Acme Agency', $result->getOrganizationName());
        $this->assertSame('free', $result->getBillingTier());
        $this->assertSame('user-uuid-1', $result->getUserId());
        $this->assertSame('admin@acme.com', $result->getUserEmail());
        $this->assertSame(['ROLE_ADMIN'], $result->getUserRoles());

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/register', $mockResponse->getRequestUrl());

        // Must be unauthenticated (no Authorization header)
        $headers = $mockResponse->getRequestOptions()['headers'] ?? [];
        foreach ($headers as $h) {
            $this->assertStringStartsNotWith('authorization:', strtolower((string) $h));
        }

        /** @var array<string, mixed> $sentBody */
        $sentBody = json_decode((string) $mockResponse->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Acme Agency', $sentBody['org_name']);
        $this->assertSame('admin@acme.com', $sentBody['email']);
        $this->assertSame('SecurePass1!', $sentBody['password']);
    }

    public function testRegisterWithArrayPayload(): void
    {
        $responseBody = json_encode([
            'organization' => [
                'id' => 'org-uuid-2',
                'name' => 'Acme',
                'billing_tier' => 'free',
            ],
            'user' => [
                'id' => 'user-uuid-2',
                'email' => 'a@b.com',
                'roles' => ['ROLE_ADMIN'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 201]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', '', $httpClient);

        $result = $admin->register(['org_name' => 'Acme', 'email' => 'a@b.com', 'password' => 'x']);

        $this->assertInstanceOf(RegisterResponseDto::class, $result);
        $this->assertSame('Acme', $result->getOrganizationName());
        $this->assertSame('a@b.com', $result->getUserEmail());

        /** @var array<string, mixed> $sentBody */
        $sentBody = json_decode((string) $mockResponse->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['org_name' => 'Acme', 'email' => 'a@b.com', 'password' => 'x'], $sentBody);
    }

    public function testRegisterWithDto(): void
    {
        $responseBody = json_encode([
            'organization' => [
                'id' => 'org-uuid-3',
                'name' => 'Acme DTO',
                'billing_tier' => 'free',
            ],
            'user' => [
                'id' => 'user-uuid-3',
                'email' => 'dto@acme.com',
                'roles' => ['ROLE_ADMIN'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 201]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', '', $httpClient);

        $dto = new RegisterDto('Acme DTO', 'dto@acme.com', 'dto-secret');
        $result = $admin->register($dto);

        $this->assertInstanceOf(RegisterResponseDto::class, $result);
        $this->assertSame('Acme DTO', $result->getOrganizationName());
        $this->assertSame('dto@acme.com', $result->getUserEmail());

        /** @var array<string, mixed> $sentBody */
        $sentBody = json_decode((string) $mockResponse->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Acme DTO', $sentBody['org_name']);
        $this->assertSame('dto@acme.com', $sentBody['email']);
        $this->assertSame('dto-secret', $sentBody['password']);
    }

    public function testRegisterConflict(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Conflict',
            'status' => 409,
            'detail' => 'An account with email admin@acme.com already exists.',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 409,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', '', $httpClient);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('An account with email admin@acme.com already exists.');

        $admin->register('Acme Agency', 'admin@acme.com', 'SecurePass1!');
    }

    public function testRegisterValidationError(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'Missing or invalid required fields (org_name, email, password).',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 422,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', '', $httpClient);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing or invalid required fields (org_name, email, password).');

        $admin->register(['email' => 'admin@acme.com', 'password' => 'SecurePass1!']);
    }

    public function testRegisterForbidden(): void
    {
        $errorBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Self-registration is closed.',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 403,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', '', $httpClient);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Self-registration is closed.');

        $admin->register('Acme Agency', 'admin@acme.com', 'SecurePass1!');
    }

    public function testRegisterWithEmptyScalarOrgNameThrowsException(): void
    {
        $admin = new AdminClient('https://api.test', '');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('org_name cannot be empty.');

        $admin->register('   ', 'admin@acme.com', 'SecurePass1!');
    }

    public function testRegisterWithEmptyScalarEmailThrowsException(): void
    {
        $admin = new AdminClient('https://api.test', '');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('email cannot be empty.');

        $admin->register('Acme Agency', '   ', 'SecurePass1!');
    }

    public function testRegisterWithEmptyScalarPasswordThrowsException(): void
    {
        $admin = new AdminClient('https://api.test', '');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('password cannot be empty.');

        $admin->register('Acme Agency', 'admin@acme.com', '');
    }

    /**
     * S1: createOrganization() sends POST /api/v1/admin/organizations with Authorization: Bearer <jwt> and returns typed OrganizationDto.
     */
    public function testCreateOrganization(): void
    {
        $responseBody = json_encode([
            'id' => 'org-uuid-100',
            'name' => 'Acme Agency',
            'billing_tier' => 'standard',
            'created_at' => '2026-09-08T12:00:00Z',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 201]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'my-admin-jwt', $httpClient);

        $org = $admin->createOrganization('Acme Agency', 'standard');

        $this->assertInstanceOf(OrganizationDto::class, $org);
        $this->assertSame('org-uuid-100', $org->id);
        $this->assertSame('Acme Agency', $org->name);
        $this->assertSame('standard', $org->billingTier);
        $this->assertSame('2026-09-08T12:00:00Z', $org->createdAt);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/organizations', $mockResponse->getRequestUrl());
        $this->assertContains('Authorization: Bearer my-admin-jwt', $mockResponse->getRequestOptions()['headers']);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $mockResponse->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Acme Agency', $body['name']);
        $this->assertSame('standard', $body['billing_tier']);
    }

    public function testCreateOrganizationWithArray(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'id' => 'org-uuid-101',
            'name' => 'Acme Array',
            'billing_tier' => 'pro',
        ], JSON_THROW_ON_ERROR), ['http_code' => 201]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $org = $admin->createOrganization(['name' => 'Acme Array', 'billing_tier' => 'pro']);
        $this->assertSame('org-uuid-101', $org->id);
        $this->assertSame('Acme Array', $org->name);
        $this->assertSame('pro', $org->billingTier);
    }

    public function testListOrganizations(): void
    {
        $responseBody = json_encode([
            [
                'id' => 'org-1',
                'name' => 'Agency 1',
                'billing_tier' => 'standard',
            ],
            [
                'id' => 'org-2',
                'name' => 'Agency 2',
                'billing_tier' => 'enterprise',
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $list = $admin->listOrganizations(['page' => 1]);

        $this->assertCount(2, $list);
        $this->assertInstanceOf(OrganizationDto::class, $list[0]);
        $this->assertSame('org-1', $list[0]->id);
        $this->assertSame('Agency 2', $list[1]->name);
        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('/api/v1/admin/organizations', $mockResponse->getRequestUrl());
    }

    public function testGetOrganization(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'id' => 'org-42',
            'name' => 'Org 42',
            'billing_tier' => 'free',
        ], JSON_THROW_ON_ERROR));
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $org = $admin->getOrganization('org-42');
        $this->assertSame('org-42', $org->id);
        $this->assertStringEndsWith('/api/v1/admin/organizations/org-42', $mockResponse->getRequestUrl());
    }

    public function testUpdateOrganization(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'id' => 'org-42',
            'name' => 'Updated Org',
            'billing_tier' => 'standard',
        ], JSON_THROW_ON_ERROR));
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $org = $admin->updateOrganization('org-42', ['name' => 'Updated Org']);
        $this->assertSame('Updated Org', $org->name);
        $this->assertSame('PATCH', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/organizations/org-42', $mockResponse->getRequestUrl());
        $this->assertContains('Content-Type: application/merge-patch+json', $mockResponse->getRequestOptions()['headers']);
    }

    public function testCreateProject(): void
    {
        $responseBody = json_encode([
            'id' => 'proj-123',
            'name' => 'Customer Portal',
            'domain' => 'portal.customer.com',
            'webhook_url' => 'https://webhook.customer.com/events',
            'settings' => ['notifications' => true],
            'created_at' => '2026-09-08T12:30:00Z',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 201]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $project = $admin->createProject(
            'Customer Portal',
            'portal.customer.com',
            'https://webhook.customer.com/events',
            ['notifications' => true]
        );

        $this->assertInstanceOf(ProjectDto::class, $project);
        $this->assertSame('proj-123', $project->id);
        $this->assertSame('Customer Portal', $project->name);
        $this->assertSame('portal.customer.com', $project->domain);
        $this->assertSame('https://webhook.customer.com/events', $project->webhookUrl);
        $this->assertSame(['notifications' => true], $project->settings);
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/projects', $mockResponse->getRequestUrl());
    }

    public function testListProjects(): void
    {
        $mockResponse = new MockResponse(json_encode([
            ['id' => 'p1', 'name' => 'Project 1', 'domain' => 'p1.test'],
            ['id' => 'p2', 'name' => 'Project 2', 'domain' => 'p2.test'],
        ], JSON_THROW_ON_ERROR));
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $projects = $admin->listProjects();
        $this->assertCount(2, $projects);
        $this->assertSame('p1', $projects[0]->id);
        $this->assertSame('Project 2', $projects[1]->name);
        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/projects', $mockResponse->getRequestUrl());
    }

    public function testGetAndUpdateAndDeleteProject(): void
    {
        $mockGet = new MockResponse(json_encode(['id' => 'p1', 'name' => 'Project 1', 'domain' => 'p1.test'], JSON_THROW_ON_ERROR));
        $mockPatch = new MockResponse(json_encode(['id' => 'p1', 'name' => 'Updated P1', 'domain' => 'p1.test'], JSON_THROW_ON_ERROR));
        $mockDelete = new MockResponse('', ['http_code' => 204]);

        $httpClient = new MockHttpClient([$mockGet, $mockPatch, $mockDelete]);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $project = $admin->getProject('p1');
        $this->assertSame('p1', $project->id);

        $updated = $admin->updateProject('p1', ['name' => 'Updated P1']);
        $this->assertSame('Updated P1', $updated->name);

        $admin->deleteProject('p1');
        $this->assertSame('DELETE', $mockDelete->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/projects/p1', $mockDelete->getRequestUrl());
    }

    /**
     * S4: createApiKey() returns ApiKeyCreatedDto with plaintext token;
     *     listApiKeys() returns ApiKeyDto[] without token.
     */
    public function testCreateApiKeyAndListApiKeys(): void
    {
        $createResponseBody = json_encode([
            'id' => 'key-uuid-1',
            'name' => 'Production Key',
            'token' => 'kineti_live_abcdef1234567890',
            'token_suffix' => '7890',
            'scope' => 'all',
            'rate_limit_per_minute' => 120,
            'expires_at' => '2027-01-01T00:00:00Z',
            'created_at' => '2026-09-08T14:00:00Z',
        ], JSON_THROW_ON_ERROR);

        $listResponseBody = json_encode([
            [
                'id' => 'key-uuid-1',
                'name' => 'Production Key',
                'token_suffix' => '7890',
                'scope' => 'all',
                'rate_limit_per_minute' => 120,
                'expires_at' => '2027-01-01T00:00:00Z',
                'created_at' => '2026-09-08T14:00:00Z',
            ],
            [
                'id' => 'key-uuid-2',
                'name' => 'Staging Key',
                'token_suffix' => '1111',
                'scope' => 'vision',
                'rate_limit_per_minute' => 60,
                'created_at' => '2026-09-08T14:10:00Z',
            ],
        ], JSON_THROW_ON_ERROR);

        $mockCreateResponse = new MockResponse($createResponseBody, ['http_code' => 201]);
        $mockListResponse = new MockResponse($listResponseBody, ['http_code' => 200]);

        $httpClient = new MockHttpClient([$mockCreateResponse, $mockListResponse]);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        // 1. Create API key
        $createdKey = $admin->createApiKey('proj-1', 'Production Key', 'all', 120, '2027-01-01T00:00:00Z');

        $this->assertInstanceOf(ApiKeyCreatedDto::class, $createdKey);
        $this->assertSame('key-uuid-1', $createdKey->id);
        $this->assertSame('Production Key', $createdKey->name);
        $this->assertSame('kineti_live_abcdef1234567890', $createdKey->token);
        $this->assertSame('7890', $createdKey->tokenSuffix);
        $this->assertSame(120, $createdKey->rateLimitPerMinute);
        $this->assertStringEndsWith('/api/v1/admin/projects/proj-1/api-keys', $mockCreateResponse->getRequestUrl());

        // 2. List API keys
        $keys = $admin->listApiKeys('proj-1');

        $this->assertCount(2, $keys);
        foreach ($keys as $key) {
            $this->assertInstanceOf(ApiKeyDto::class, $key);
            // Strictly assert that ApiKeyDto does NOT expose the plaintext token property
            $this->assertObjectNotHasProperty('token', $key);
        }

        $this->assertSame('key-uuid-1', $keys[0]->id);
        $this->assertSame('7890', $keys[0]->tokenSuffix);
        $this->assertSame('key-uuid-2', $keys[1]->id);
        $this->assertSame('1111', $keys[1]->tokenSuffix);
        $this->assertStringEndsWith('/api/v1/admin/projects/proj-1/api-keys', $mockListResponse->getRequestUrl());
    }

    public function testRevokeApiKey(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $admin->revokeApiKey('proj-1', 'key-uuid-1');

        $this->assertSame('DELETE', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/projects/proj-1/api-keys/key-uuid-1', $mockResponse->getRequestUrl());
    }

    public function testRotateApiKey(): void
    {
        $responseBody = json_encode([
            'id' => 'key-uuid-1',
            'name' => 'Primary Key',
            'token' => 'new-rotated-token-999',
            'token_suffix' => '0999',
            'scope' => 'all',
            'grace_period_until' => '2026-09-08T15:00:00Z',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $rotated = $admin->rotateApiKey('proj-1', 'key-uuid-1');

        $this->assertInstanceOf(ApiKeyCreatedDto::class, $rotated);
        $this->assertSame('new-rotated-token-999', $rotated->token);
        $this->assertSame('0999', $rotated->tokenSuffix);
        $this->assertSame('2026-09-08T15:00:00Z', $rotated->gracePeriodUntil);
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/admin/projects/proj-1/api-keys/key-uuid-1/rotate', $mockResponse->getRequestUrl());
    }

    public function testGetUsage(): void
    {
        $responseBody = json_encode([
            [
                'project_id' => 'p1',
                'project_name' => 'Project Alpha',
                'service' => 'vision',
                'total_tokens' => 150,
                'request_count' => 3,
            ],
            [
                'project_id' => 'p2',
                'project_name' => 'Project Beta',
                'service' => 'search',
                'total_tokens' => 300,
                'request_count' => 1,
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $usage = $admin->getUsage('2026-09-01', '2026-09-05', 'p1');

        $this->assertCount(2, $usage);
        $this->assertInstanceOf(UsageSummaryDto::class, $usage[0]);
        $this->assertSame('p1', $usage[0]->projectId);
        $this->assertSame('Project Alpha', $usage[0]->projectName);
        $this->assertSame('vision', $usage[0]->service);
        $this->assertSame(150, $usage[0]->totalTokens);
        $this->assertSame(3, $usage[0]->requestCount);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('/api/v1/admin/usage', $mockResponse->getRequestUrl());
        $this->assertStringContainsString('from=2026-09-01', $mockResponse->getRequestUrl());
        $this->assertStringContainsString('to=2026-09-05', $mockResponse->getRequestUrl());
        $this->assertStringContainsString('project_id=p1', $mockResponse->getRequestUrl());
    }

    public function testGetAnalytics(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['date' => '2026-09-01', 'vision' => 100, 'search' => 50, 'ingest' => 0, 'rag' => 0],
                ['date' => '2026-09-02', 'vision' => 0, 'search' => 200, 'ingest' => 10, 'rag' => 20],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $analytics = $admin->getAnalytics('day', '2026-09-01', '2026-09-02');

        $this->assertInstanceOf(AnalyticsDto::class, $analytics);
        $this->assertCount(2, $analytics->data);
        $this->assertSame('2026-09-01', $analytics->data[0]['date']);
        $this->assertSame(100, $analytics->data[0]['vision']);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('/api/v1/admin/analytics', $mockResponse->getRequestUrl());
        $this->assertStringContainsString('group_by=day', $mockResponse->getRequestUrl());
    }

    /**
     * S3: Backend 401 maps to AuthenticationException via existing ResponseErrorHandler.
     */
    public function testBackend401MapsToAuthenticationException(): void
    {
        $problemJson = json_encode([
            'type' => 'urn:problem-type:unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Invalid or expired JWT token',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($problemJson, [
            'http_code' => 401,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'invalid-token', $httpClient);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid or expired JWT token');

        $admin->listProjects();
    }

    public function testBackend403MapsToAuthorizationException(): void
    {
        $problemJson = json_encode([
            'type' => 'urn:problem-type:forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Access Denied.',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($problemJson, [
            'http_code' => 403,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Access Denied.');

        $admin->listProjects();
    }

    public function testBackend404MapsToNotFoundException(): void
    {
        $problemJson = json_encode([
            'type' => 'urn:problem-type:not-found',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => 'Project not found.',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($problemJson, [
            'http_code' => 404,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Project not found.');

        $admin->getProject('non-existent');
    }

    public function testBackend422MapsToValidationException(): void
    {
        $problemJson = json_encode([
            'type' => 'urn:problem-type:validation-error',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'Domain already taken.',
            'violations' => [
                ['propertyPath' => 'domain', 'message' => 'Domain already exists in this organization.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($problemJson, [
            'http_code' => 422,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        try {
            $admin->createProject('Site', 'existing.com');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame('Domain already taken.', $e->getMessage());
            $this->assertCount(1, $e->getViolations());
            $this->assertSame('domain', $e->getViolations()[0]['propertyPath']);
        }
    }

    public function testGetRegistrationStatus(): void
    {
        $mockResponse = new MockResponse(json_encode(['mode' => 'whitelist'], JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'jwt', $httpClient);

        $status = $admin->getRegistrationStatus();

        $this->assertInstanceOf(RegistrationStatusDto::class, $status);
        $this->assertSame(RegistrationMode::Whitelist, $status->mode);
        $this->assertTrue($status->isWhitelist());
        $this->assertFalse($status->isOpen());
        $this->assertFalse($status->isClosed());

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/registration-status', $mockResponse->getRequestUrl());
    }

    public function testListProjectJobs(): void
    {
        $payload = [
            'hydra:member' => [
                [
                    'job_id' => '00000000-0000-0000-0000-000000000001',
                    'status' => 'completed',
                    'total_images' => 5,
                    'processed_images' => 5,
                    'results' => [
                        [
                            'external_id' => 'img1',
                            'alt_text' => 'Sample alt text',
                            'tags' => ['nature', 'forest'],
                            'confidence_score' => 0.95,
                        ],
                    ],
                    'created_at' => '2026-09-01T10:00:00+00:00',
                    'completed_at' => '2026-09-01T10:01:00+00:00',
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'test-jwt', $httpClient);

        $jobs = $admin->listProjectJobs('proj-123', 2);

        $this->assertCount(1, $jobs);
        $this->assertSame('00000000-0000-0000-0000-000000000001', $jobs[0]->jobId);
        $this->assertSame(\KinetiStack\Sdk\Enum\JobStatus::Completed, $jobs[0]->status);
        $this->assertSame(5, $jobs[0]->totalImages);
        $this->assertSame(5, $jobs[0]->processedImages);
        $this->assertNotNull($jobs[0]->results);
        $this->assertCount(1, $jobs[0]->results);
        $this->assertSame('Sample alt text', $jobs[0]->results[0]->altText);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/projects/proj-123/jobs?page=2', $mockResponse->getRequestUrl());
    }

    public function testRetryJob(): void
    {
        $payload = [
            'job_id' => '00000000-0000-0000-0000-000000000002',
            'status' => 'pending',
            'total_images' => 3,
            'processed_images' => 0,
            'results' => null,
            'created_at' => '2026-09-02T10:00:00+00:00',
        ];

        $mockResponse = new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'test-jwt', $httpClient);

        $job = $admin->retryJob('00000000-0000-0000-0000-000000000002');

        $this->assertSame('00000000-0000-0000-0000-000000000002', $job->jobId);
        $this->assertSame(\KinetiStack\Sdk\Enum\JobStatus::Pending, $job->status);
        $this->assertSame(3, $job->totalImages);
        $this->assertSame(0, $job->processedImages);
        $this->assertNull($job->results);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/jobs/00000000-0000-0000-0000-000000000002/retry', $mockResponse->getRequestUrl());
    }

    public function testGetJob(): void
    {
        $payload = [
            'job_id' => '00000000-0000-0000-0000-000000000003',
            'status' => 'completed',
            'total_images' => 4,
            'processed_images' => 4,
            'results' => [
                [
                    'external_id' => 'img3',
                    'alt_text' => 'Job alt text',
                    'tags' => ['ai', 'vision'],
                    'confidence_score' => 0.98,
                ],
            ],
            'created_at' => '2026-09-03T10:00:00+00:00',
            'completed_at' => '2026-09-03T10:01:00+00:00',
        ];

        $mockResponse = new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'test-jwt', $httpClient);

        $job = $admin->getJob('00000000-0000-0000-0000-000000000003');

        $this->assertSame('00000000-0000-0000-0000-000000000003', $job->jobId);
        $this->assertSame(\KinetiStack\Sdk\Enum\JobStatus::Completed, $job->status);
        $this->assertSame(4, $job->totalImages);
        $this->assertSame(4, $job->processedImages);
        $this->assertNotNull($job->results);
        $this->assertCount(1, $job->results);
        $this->assertSame('Job alt text', $job->results[0]->altText);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/api/v1/admin/jobs/00000000-0000-0000-0000-000000000003', $mockResponse->getRequestUrl());
    }
}
