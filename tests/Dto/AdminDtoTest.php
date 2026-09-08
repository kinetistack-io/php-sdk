<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Dto\ApiKeyCreatedDto;
use KinetiStack\Sdk\Dto\ApiKeyDto;
use KinetiStack\Sdk\Dto\AuthTokenDto;
use KinetiStack\Sdk\Dto\OrganizationDto;
use KinetiStack\Sdk\Dto\ProjectDto;
use KinetiStack\Sdk\Dto\UsageSummaryDto;
use PHPUnit\Framework\TestCase;

class AdminDtoTest extends TestCase
{
    public function testAuthTokenDto(): void
    {
        $dto = new AuthTokenDto('jwt-token-123', 'refresh-token-456');
        $this->assertSame('jwt-token-123', $dto->token);
        $this->assertSame('refresh-token-456', $dto->refreshToken);

        $array = $dto->toArray();
        $this->assertSame('jwt-token-123', $array['token']);
        $this->assertSame('refresh-token-456', $array['refresh_token']);

        $reconstructed = AuthTokenDto::fromArray($array);
        $this->assertSame('jwt-token-123', $reconstructed->token);
        $this->assertSame('refresh-token-456', $reconstructed->refreshToken);

        $reconstructedCamel = AuthTokenDto::fromArray([
            'token' => 'jwt-token-789',
            'refreshToken' => 'refresh-camel',
        ]);
        $this->assertSame('jwt-token-789', $reconstructedCamel->token);
        $this->assertSame('refresh-camel', $reconstructedCamel->refreshToken);

        $noRefresh = new AuthTokenDto('only-token');
        $this->assertNull($noRefresh->refreshToken);
        $this->assertArrayNotHasKey('refresh_token', $noRefresh->toArray());

        $this->expectException(\InvalidArgumentException::class);
        new AuthTokenDto('');
    }

    public function testOrganizationDto(): void
    {
        $dto = new OrganizationDto('org-uuid-1', 'Acme Corp', 'standard', '2026-09-01T00:00:00Z');
        $this->assertSame('org-uuid-1', $dto->id);
        $this->assertSame('Acme Corp', $dto->name);
        $this->assertSame('standard', $dto->billingTier);
        $this->assertSame('2026-09-01T00:00:00Z', $dto->createdAt);

        $array = $dto->toArray();
        $this->assertSame('org-uuid-1', $array['id']);
        $this->assertSame('Acme Corp', $array['name']);
        $this->assertSame('standard', $array['billing_tier']);
        $this->assertSame('2026-09-01T00:00:00Z', $array['created_at']);

        $fromArray = OrganizationDto::fromArray([
            'id' => 'org-2',
            'name' => 'Agency 2',
            'billingTier' => 'pro',
            'createdAt' => '2026-09-02T00:00:00Z',
        ]);
        $this->assertSame('org-2', $fromArray->id);
        $this->assertSame('Agency 2', $fromArray->name);
        $this->assertSame('pro', $fromArray->billingTier);
        $this->assertSame('2026-09-02T00:00:00Z', $fromArray->createdAt);

        $this->expectException(\InvalidArgumentException::class);
        new OrganizationDto('', 'Name');
    }

    public function testOrganizationDtoEmptyNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OrganizationDto('id-1', '   ');
    }

    public function testProjectDto(): void
    {
        $dto = new ProjectDto(
            'proj-1',
            'Main Site',
            'example.com',
            'https://webhook.test/events',
            ['theme' => 'dark'],
            '2026-09-01T00:00:00Z'
        );

        $this->assertSame('proj-1', $dto->id);
        $this->assertSame('Main Site', $dto->name);
        $this->assertSame('example.com', $dto->domain);
        $this->assertSame('https://webhook.test/events', $dto->webhookUrl);
        $this->assertSame(['theme' => 'dark'], $dto->settings);
        $this->assertSame('2026-09-01T00:00:00Z', $dto->createdAt);

        $array = $dto->toArray();
        $this->assertSame('proj-1', $array['id']);
        $this->assertSame('https://webhook.test/events', $array['webhook_url']);
        $this->assertSame(['theme' => 'dark'], $array['settings']);

        $fromArray = ProjectDto::fromArray([
            'id' => 'proj-2',
            'name' => 'Site 2',
            'domain' => 'site2.com',
            'webhookUrl' => 'https://site2.com/wh',
            'settings' => ['k' => 'v'],
            'createdAt' => '2026-09-02T00:00:00Z',
        ]);
        $this->assertSame('https://site2.com/wh', $fromArray->webhookUrl);
        $this->assertSame(['k' => 'v'], $fromArray->settings);

        $this->expectException(\InvalidArgumentException::class);
        new ProjectDto('', 'Site', 'site.com');
    }

    public function testProjectDtoValidationExceptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProjectDto('id', '', 'domain.com');
    }

    public function testProjectDtoEmptyDomainThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProjectDto('id', 'Name', '');
    }

    public function testApiKeyDtoDoesNotExposeToken(): void
    {
        $dto = new ApiKeyDto(
            'key-1',
            'Drupal Key',
            'abcd',
            'all',
            60,
            '2027-01-01T00:00:00Z',
            '2026-09-01T00:00:00Z',
            null,
            null
        );

        $this->assertSame('key-1', $dto->id);
        $this->assertSame('Drupal Key', $dto->name);
        $this->assertSame('abcd', $dto->tokenSuffix);
        $this->assertSame('all', $dto->scope);
        $this->assertSame(60, $dto->rateLimitPerMinute);
        $this->assertObjectNotHasProperty('token', $dto);

        $array = $dto->toArray();
        $this->assertArrayNotHasKey('token', $array);
        $this->assertSame('abcd', $array['token_suffix']);

        $fromArray = ApiKeyDto::fromArray([
            'id' => 'key-2',
            'name' => 'Key 2',
            'token_suffix' => 'wxyz',
            'rate_limit_per_minute' => 120,
            'token' => 'should-be-ignored',
        ]);
        $this->assertSame('key-2', $fromArray->id);
        $this->assertSame('wxyz', $fromArray->tokenSuffix);
        $this->assertSame(120, $fromArray->rateLimitPerMinute);
        $this->assertObjectNotHasProperty('token', $fromArray);

        $this->expectException(\InvalidArgumentException::class);
        new ApiKeyDto('', 'Name', 'abcd');
    }

    public function testApiKeyCreatedDtoExposesToken(): void
    {
        $dto = new ApiKeyCreatedDto(
            'key-1',
            'Drupal Key',
            'abcd',
            'raw-plaintext-token-12345',
            'all',
            60
        );

        $this->assertInstanceOf(ApiKeyDto::class, $dto);
        $this->assertSame('raw-plaintext-token-12345', $dto->token);
        $this->assertSame('abcd', $dto->tokenSuffix);

        $array = $dto->toArray();
        $this->assertArrayHasKey('token', $array);
        $this->assertSame('raw-plaintext-token-12345', $array['token']);

        $fromArray = ApiKeyCreatedDto::fromArray([
            'id' => 'key-3',
            'name' => 'Key 3',
            'token_suffix' => '9999',
            'token' => 'plain-token-9999',
        ]);
        $this->assertSame('plain-token-9999', $fromArray->token);

        $this->expectException(\InvalidArgumentException::class);
        new ApiKeyCreatedDto('id', 'Name', 'suffix', '');
    }

    public function testUsageSummaryDto(): void
    {
        $dto = new UsageSummaryDto('proj-1', 'Acme Project', 'vision', 150, 3);
        $this->assertSame('proj-1', $dto->projectId);
        $this->assertSame('Acme Project', $dto->projectName);
        $this->assertSame('vision', $dto->service);
        $this->assertSame(150, $dto->totalTokens);
        $this->assertSame(3, $dto->requestCount);

        $array = $dto->toArray();
        $this->assertSame('proj-1', $array['project_id']);
        $this->assertSame('Acme Project', $array['project_name']);
        $this->assertSame('vision', $array['service']);
        $this->assertSame(150, $array['total_tokens']);
        $this->assertSame(3, $array['request_count']);

        $fromArray = UsageSummaryDto::fromArray([
            'project_id' => 'proj-2',
            'project_name' => 'Beta',
            'service' => 'search',
            'total_tokens' => 300,
            'request_count' => 1,
        ]);
        $this->assertSame('proj-2', $fromArray->projectId);
        $this->assertSame('Beta', $fromArray->projectName);
        $this->assertSame(300, $fromArray->totalTokens);
    }

    public function testAnalyticsDto(): void
    {
        $data = [
            ['date' => '2026-09-01', 'vision' => 100, 'search' => 0],
            ['date' => '2026-09-02', 'vision' => 0, 'search' => 200],
        ];

        $dto = new AnalyticsDto($data);
        $this->assertSame($data, $dto->data);
        $this->assertSame(['data' => $data], $dto->toArray());

        $fromArray = AnalyticsDto::fromArray(['data' => $data]);
        $this->assertSame($data, $fromArray->data);

        $fromListDirectly = AnalyticsDto::fromArray($data);
        $this->assertSame($data, $fromListDirectly->data);
    }
}
