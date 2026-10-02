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

    public function testOrganizationDtoWithMonthlyQuotaCap(): void
    {
        $dto = new OrganizationDto('org-uuid-1', 'Acme Corp', 'standard', '2026-09-01T00:00:00Z', 50000);
        $this->assertSame(50000, $dto->monthlyQuotaCap);
        $this->assertSame(50000, $dto->getMonthlyQuotaCap());

        $array = $dto->toArray();
        $this->assertSame(50000, $array['monthly_quota_cap']);

        $fromSnake = OrganizationDto::fromArray([
            'id' => 'org-2',
            'name' => 'Agency 2',
            'monthly_quota_cap' => 75000,
        ]);
        $this->assertSame(75000, $fromSnake->monthlyQuotaCap);
        $this->assertSame(75000, $fromSnake->getMonthlyQuotaCap());

        $fromCamel = OrganizationDto::fromArray([
            'id' => 'org-3',
            'name' => 'Agency 3',
            'monthlyQuotaCap' => 100000,
        ]);
        $this->assertSame(100000, $fromCamel->monthlyQuotaCap);
        $this->assertSame(100000, $fromCamel->getMonthlyQuotaCap());

        $fromNull = OrganizationDto::fromArray([
            'id' => 'org-4',
            'name' => 'Agency 4',
            'monthly_quota_cap' => null,
        ]);
        $this->assertNull($fromNull->monthlyQuotaCap);
        $this->assertNull($fromNull->getMonthlyQuotaCap());
        $this->assertArrayNotHasKey('monthly_quota_cap', $fromNull->toArray());
    }

    public function testOrganizationDtoNegativeMonthlyQuotaCapThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('monthlyQuotaCap must be greater than or equal to 0.');
        new OrganizationDto('org-id', 'Name', 'standard', null, -1);
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
        $this->assertNull($dto->monthlyQuotaCap);
        $this->assertNull($dto->getMonthlyQuotaCap());

        $array = $dto->toArray();
        $this->assertSame('proj-1', $array['id']);
        $this->assertSame('https://webhook.test/events', $array['webhook_url']);
        $this->assertSame(['theme' => 'dark'], $array['settings']);
        $this->assertArrayNotHasKey('monthly_quota_cap', $array);

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
        $this->assertNull($fromArray->monthlyQuotaCap);
        $this->assertNull($fromArray->getMonthlyQuotaCap());

        $this->expectException(\InvalidArgumentException::class);
        new ProjectDto('', 'Site', 'site.com');
    }

    public function testProjectDtoWithMonthlyQuotaCap(): void
    {
        $dto = new ProjectDto(
            'proj-1',
            'Main Site',
            'example.com',
            null,
            null,
            null,
            25000
        );

        $this->assertSame(25000, $dto->monthlyQuotaCap);
        $this->assertSame(25000, $dto->getMonthlyQuotaCap());

        $array = $dto->toArray();
        $this->assertSame(25000, $array['monthly_quota_cap']);

        $fromSnake = ProjectDto::fromArray([
            'id' => 'proj-2',
            'name' => 'Site 2',
            'domain' => 'site2.com',
            'monthly_quota_cap' => 30000,
        ]);
        $this->assertSame(30000, $fromSnake->monthlyQuotaCap);
        $this->assertSame(30000, $fromSnake->getMonthlyQuotaCap());

        $fromCamel = ProjectDto::fromArray([
            'id' => 'proj-3',
            'name' => 'Site 3',
            'domain' => 'site3.com',
            'monthlyQuotaCap' => 45000,
        ]);
        $this->assertSame(45000, $fromCamel->monthlyQuotaCap);
        $this->assertSame(45000, $fromCamel->getMonthlyQuotaCap());

        $fromNull = ProjectDto::fromArray([
            'id' => 'proj-4',
            'name' => 'Site 4',
            'domain' => 'site4.com',
            'monthly_quota_cap' => null,
        ]);
        $this->assertNull($fromNull->monthlyQuotaCap);
        $this->assertNull($fromNull->getMonthlyQuotaCap());
        $this->assertArrayNotHasKey('monthly_quota_cap', $fromNull->toArray());
    }

    public function testProjectDtoNegativeMonthlyQuotaCapThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('monthlyQuotaCap must be greater than or equal to 0.');
        new ProjectDto('proj-id', 'Name', 'domain.com', null, null, null, -100);
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

    public function testApiKeyDtoWithDailyTokenQuotaOverride(): void
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
            null,
            50000
        );

        $this->assertSame(50000, $dto->dailyTokenQuotaOverride);

        $array = $dto->toArray();
        $this->assertSame(50000, $array['daily_token_quota_override']);

        $fromSnake = ApiKeyDto::fromArray([
            'id' => 'key-2',
            'name' => 'Key 2',
            'token_suffix' => 'wxyz',
            'daily_token_quota_override' => 75000,
        ]);
        $this->assertSame(75000, $fromSnake->dailyTokenQuotaOverride);

        $fromCamel = ApiKeyDto::fromArray([
            'id' => 'key-3',
            'name' => 'Key 3',
            'token_suffix' => '1234',
            'dailyTokenQuotaOverride' => 80000,
        ]);
        $this->assertSame(80000, $fromCamel->dailyTokenQuotaOverride);

        $fromNull = ApiKeyDto::fromArray([
            'id' => 'key-4',
            'name' => 'Key 4',
            'token_suffix' => '5678',
            'daily_token_quota_override' => null,
        ]);
        $this->assertNull($fromNull->dailyTokenQuotaOverride);
        $this->assertArrayNotHasKey('daily_token_quota_override', $fromNull->toArray());
    }

    public function testApiKeyDtoNegativeDailyTokenQuotaOverrideThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dailyTokenQuotaOverride must be greater than 0.');
        new ApiKeyDto(
            id: 'key-1',
            name: 'Key',
            tokenSuffix: 'abcd',
            dailyTokenQuotaOverride: -100,
        );
    }

    public function testApiKeyDtoZeroDailyTokenQuotaOverrideThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dailyTokenQuotaOverride must be greater than 0.');
        new ApiKeyDto(
            id: 'key-1',
            name: 'Key',
            tokenSuffix: 'abcd',
            dailyTokenQuotaOverride: 0,
        );
    }

    public function testApiKeyDtoWithUsageProperties(): void
    {
        $dto = new ApiKeyDto(
            id: 'key-1',
            name: 'Drupal Key',
            tokenSuffix: 'abcd',
            tokensConsumedToday: 1500,
            requestsToday: 25,
        );

        $this->assertSame(1500, $dto->tokensConsumedToday);
        $this->assertSame(25, $dto->requestsToday);

        $array = $dto->toArray();
        $this->assertSame(1500, $array['tokens_consumed_today']);
        $this->assertSame(25, $array['requests_today']);

        $fromSnake = ApiKeyDto::fromArray([
            'id' => 'key-2',
            'name' => 'Key 2',
            'token_suffix' => 'wxyz',
            'tokens_consumed_today' => 2000,
            'requests_today' => 40,
        ]);
        $this->assertSame(2000, $fromSnake->tokensConsumedToday);
        $this->assertSame(40, $fromSnake->requestsToday);

        $fromCamel = ApiKeyDto::fromArray([
            'id' => 'key-3',
            'name' => 'Key 3',
            'token_suffix' => '1234',
            'tokensConsumedToday' => 3500,
            'requestsToday' => 50,
        ]);
        $this->assertSame(3500, $fromCamel->tokensConsumedToday);
        $this->assertSame(50, $fromCamel->requestsToday);

        $fromNull = ApiKeyDto::fromArray([
            'id' => 'key-4',
            'name' => 'Key 4',
            'token_suffix' => '5678',
            'tokens_consumed_today' => null,
            'requests_today' => null,
        ]);
        $this->assertNull($fromNull->tokensConsumedToday);
        $this->assertNull($fromNull->requestsToday);
        $this->assertArrayNotHasKey('tokens_consumed_today', $fromNull->toArray());
        $this->assertArrayNotHasKey('requests_today', $fromNull->toArray());
    }

    public function testApiKeyDtoNegativeTokensConsumedTodayThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tokensConsumedToday must be greater than or equal to 0.');
        new ApiKeyDto(
            id: 'key-1',
            name: 'Key',
            tokenSuffix: 'abcd',
            tokensConsumedToday: -1,
        );
    }

    public function testApiKeyDtoNegativeRequestsTodayThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requestsToday must be greater than or equal to 0.');
        new ApiKeyDto(
            id: 'key-1',
            name: 'Key',
            tokenSuffix: 'abcd',
            requestsToday: -5,
        );
    }

    /**
     * @dataProvider emptyNameProvider
     * @param array<string, mixed> $payload
     */
    public function testApiKeyDtoFromArrayDefaultsEmptyNameToApiKey(array $payload): void
    {
        $dto = ApiKeyDto::fromArray($payload);

        $this->assertSame('API Key', $dto->name);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function emptyNameProvider(): array
    {
        return [
            'empty string' => [['id' => 'key-123', 'name' => '', 'token_suffix' => 'abcd']],
            'whitespace only' => [['id' => 'key-123', 'name' => '   ', 'token_suffix' => 'abcd']],
            'null name' => [['id' => 'key-123', 'name' => null, 'token_suffix' => 'abcd']],
            'missing name key' => [['id' => 'key-123', 'token_suffix' => 'abcd']],
        ];
    }

    public function testApiKeyCreatedDtoExposesToken(): void
    {
        $dto = new ApiKeyCreatedDto(
            id: 'key-1',
            name: 'Drupal Key',
            tokenSuffix: 'abcd',
            token: 'raw-plaintext-token-12345',
            rateLimitPerMinute: 60,
            dailyTokenQuotaOverride: 30000,
        );

        $this->assertInstanceOf(ApiKeyDto::class, $dto);
        $this->assertSame('raw-plaintext-token-12345', $dto->token);
        $this->assertSame('abcd', $dto->tokenSuffix);
        $this->assertSame(30000, $dto->dailyTokenQuotaOverride);

        $array = $dto->toArray();
        $this->assertArrayHasKey('token', $array);
        $this->assertSame('raw-plaintext-token-12345', $array['token']);
        $this->assertSame(30000, $array['daily_token_quota_override']);

        $fromArray = ApiKeyCreatedDto::fromArray([
            'id' => 'key-3',
            'name' => 'Key 3',
            'token_suffix' => '9999',
            'token' => 'plain-token-9999',
            'daily_token_quota_override' => 45000,
        ]);
        $this->assertSame('plain-token-9999', $fromArray->token);
        $this->assertSame(45000, $fromArray->dailyTokenQuotaOverride);

        $this->expectException(\InvalidArgumentException::class);
        new ApiKeyCreatedDto('id', 'Name', 'suffix', '');
    }

    public function testApiKeyCreatedDtoWithUsageProperties(): void
    {
        $dto = new ApiKeyCreatedDto(
            id: 'key-1',
            name: 'Drupal Key',
            tokenSuffix: 'abcd',
            token: 'raw-token',
            tokensConsumedToday: 600,
            requestsToday: 12,
        );

        $this->assertSame(600, $dto->tokensConsumedToday);
        $this->assertSame(12, $dto->requestsToday);

        $array = $dto->toArray();
        $this->assertSame(600, $array['tokens_consumed_today']);
        $this->assertSame(12, $array['requests_today']);

        $fromArray = ApiKeyCreatedDto::fromArray([
            'id' => 'key-3',
            'name' => 'Key 3',
            'token_suffix' => '9999',
            'token' => 'plain-token-9999',
            'tokens_consumed_today' => 800,
            'requests_today' => 15,
        ]);
        $this->assertSame(800, $fromArray->tokensConsumedToday);
        $this->assertSame(15, $fromArray->requestsToday);
        $this->assertSame('plain-token-9999', $fromArray->token);
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
