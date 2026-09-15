<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use KinetiStack\Sdk\AdminClient;
use KinetiStack\Sdk\Dto\ApiKeyCreatedDto;
use KinetiStack\Sdk\Dto\ApiKeyDto;
use KinetiStack\Sdk\Dto\AuthTokenDto;
use KinetiStack\Sdk\Dto\OrganizationDto;
use KinetiStack\Sdk\Dto\ProjectDto;
use KinetiStack\Sdk\Dto\RegistrationStatusDto;
use KinetiStack\Sdk\Enum\RegistrationMode;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Transport\HttpTransport;
use KinetiStack\Sdk\Transport\Psr18Transport;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class AdminClientPsr18Test extends TestCase
{
    public function testAdminClientWithGuzzlePsr18(): void
    {
        /** @var list<array{request: RequestInterface, response: Response}> $container */
        $container = [];
        $history = Middleware::history($container);

        $mock = new MockHandler([
            // 1. login
            new Response(200, ['Content-Type' => 'application/json'], '{"token": "jwt-psr18-token", "refresh_token": null}'),
            // 2. createOrganization
            new Response(201, ['Content-Type' => 'application/json'], '{"id": "org-psr18", "name": "PSR-18 Agency", "billing_tier": "standard"}'),
            // 3. createProject
            new Response(201, ['Content-Type' => 'application/json'], '{"id": "proj-psr18", "name": "PSR-18 Project", "domain": "psr18.test"}'),
            // 4. createApiKey
            new Response(201, ['Content-Type' => 'application/json'], '{"id": "k1", "name": "Key 1", "token": "plain-secret-key", "token_suffix": "cret", "scope": "all"}'),
            // 5. listApiKeys
            new Response(200, ['Content-Type' => 'application/json'], '[{"id": "k1", "name": "Key 1", "token_suffix": "cret", "scope": "all"}]'),
            // 6. getUsage
            new Response(200, ['Content-Type' => 'application/json'], '[{"project_id": "p1", "project_name": "P1", "service": "vision", "total_tokens": 100, "request_count": 2}]'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $guzzleClient = new Client(['handler' => $stack]);
        $admin = new AdminClient('https://api.test', 'jwt-psr18-token', $guzzleClient);

        // Verify underlying transport is Psr18Transport
        $transport = $admin->getTransport();
        $this->assertInstanceOf(HttpTransport::class, $transport);
        $this->assertInstanceOf(Psr18Transport::class, $transport->getDelegate());

        // 1. login
        $auth = $admin->login('admin@test.com', 'pass');
        $this->assertSame('jwt-psr18-token', $auth->token);

        // 2. createOrganization
        $org = $admin->createOrganization('PSR-18 Agency', 'standard');
        $this->assertInstanceOf(OrganizationDto::class, $org);
        $this->assertSame('org-psr18', $org->id);

        // 3. createProject
        $project = $admin->createProject('PSR-18 Project', 'psr18.test');
        $this->assertInstanceOf(ProjectDto::class, $project);
        $this->assertSame('proj-psr18', $project->id);

        // 4. createApiKey
        $apiKeyCreated = $admin->createApiKey('proj-psr18', 'Key 1');
        $this->assertInstanceOf(ApiKeyCreatedDto::class, $apiKeyCreated);
        $this->assertSame('plain-secret-key', $apiKeyCreated->token);

        // 5. listApiKeys
        $keys = $admin->listApiKeys('proj-psr18');
        $this->assertCount(1, $keys);
        $this->assertInstanceOf(ApiKeyDto::class, $keys[0]);
        $this->assertObjectNotHasProperty('token', $keys[0]);

        // 6. getUsage
        $usage = $admin->getUsage('2026-09-01', '2026-09-05');
        $this->assertCount(1, $usage);
        $this->assertSame(100, $usage[0]->totalTokens);

        // Verify that authenticated requests sent Authorization: Bearer jwt-psr18-token
        assert(is_array($container));
        $this->assertCount(6, $container);
        $createOrgRequest = $container[1]['request'];
        $this->assertTrue($createOrgRequest->hasHeader('Authorization'));
        $this->assertSame('Bearer jwt-psr18-token', $createOrgRequest->getHeaderLine('Authorization'));
    }

    public function testGetRegistrationStatusWithPsr18(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"mode": "open"}'),
        ]);
        $stack = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $stack]);
        $admin = new AdminClient('https://api.test', 'jwt-psr18-token', $guzzleClient);

        $status = $admin->getRegistrationStatus();
        $this->assertInstanceOf(RegistrationStatusDto::class, $status);
        $this->assertSame(RegistrationMode::Open, $status->mode);
        $this->assertTrue($status->isOpen());
    }

    public function testRefreshTokenWithPsr18(): void
    {
        /** @var list<array{request: RequestInterface, response: Response}> $container */
        $container = [];
        $history = Middleware::history($container);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"token": "refreshed-psr18-jwt", "refresh_token": null}'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $guzzleClient = new Client(['handler' => $stack]);
        $admin = new AdminClient('https://api.test', 'initial-psr18-jwt', $guzzleClient);

        $auth = $admin->refreshToken();

        $this->assertInstanceOf(AuthTokenDto::class, $auth);
        $this->assertSame('refreshed-psr18-jwt', $auth->token);
        $this->assertNull($auth->refreshToken);

        assert(is_array($container));
        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/admin/token/refresh', $request->getUri()->getPath());
        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertSame('Bearer initial-psr18-jwt', $request->getHeaderLine('Authorization'));
    }

    public function testRefreshTokenUnauthorizedWithPsr18(): void
    {
        $problemJson = json_encode([
            'type' => 'urn:problem-type:unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Expired JWT Token',
        ], JSON_THROW_ON_ERROR);

        $mock = new MockHandler([
            new Response(401, ['Content-Type' => 'application/problem+json'], $problemJson),
        ]);

        $stack = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $stack]);
        $admin = new AdminClient('https://api.test', 'expired-psr18-jwt', $guzzleClient);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Expired JWT Token');

        $admin->refreshToken();
    }
}
