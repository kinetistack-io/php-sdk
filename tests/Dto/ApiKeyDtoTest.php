<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\ApiKeyCreatedDto;
use KinetiStack\Sdk\Dto\ApiKeyDto;
use PHPUnit\Framework\TestCase;

class ApiKeyDtoTest extends TestCase
{
    public function testConstructorAndToArray(): void
    {
        $dto = new ApiKeyDto(
            id: 'key-123',
            name: 'Production Key',
            tokenSuffix: 'abcd',
            scope: 'read_write',
            rateLimitPerMinute: 120,
            expiresAt: '2026-12-31T23:59:59+00:00',
            createdAt: '2026-01-01T00:00:00+00:00',
            revokedAt: null,
            gracePeriodUntil: null,
            dailyTokenQuotaOverride: 50000,
            tokensConsumedToday: 1500,
            requestsToday: 42
        );

        $this->assertSame('key-123', $dto->id);
        $this->assertSame('Production Key', $dto->name);
        $this->assertSame('abcd', $dto->tokenSuffix);
        $this->assertSame('read_write', $dto->scope);
        $this->assertSame(120, $dto->rateLimitPerMinute);
        $this->assertSame('2026-12-31T23:59:59+00:00', $dto->expiresAt);
        $this->assertSame('2026-01-01T00:00:00+00:00', $dto->createdAt);
        $this->assertNull($dto->revokedAt);
        $this->assertNull($dto->gracePeriodUntil);
        $this->assertSame(50000, $dto->dailyTokenQuotaOverride);
        $this->assertSame(1500, $dto->tokensConsumedToday);
        $this->assertSame(42, $dto->requestsToday);

        $array = $dto->toArray();
        $this->assertSame('key-123', $array['id']);
        $this->assertSame('Production Key', $array['name']);
        $this->assertSame('abcd', $array['token_suffix']);
        $this->assertSame('read_write', $array['scope']);
        $this->assertSame(120, $array['rate_limit_per_minute']);
        $this->assertSame('2026-12-31T23:59:59+00:00', $array['expires_at']);
        $this->assertSame(50000, $array['daily_token_quota_override']);
        $this->assertSame(1500, $array['tokens_consumed_today']);
        $this->assertSame(42, $array['requests_today']);
    }

    public function testFromArrayHydratesCorrectly(): void
    {
        $payload = [
            'id' => 'key-456',
            'name' => 'Staging Key',
            'token_suffix' => 'wxyz',
            'scope' => 'all',
            'rate_limit_per_minute' => 60,
            'expires_at' => '2025-01-01T00:00:00+00:00',
            'created_at' => '2024-01-01T00:00:00+00:00',
        ];

        $dto = ApiKeyDto::fromArray($payload);

        $this->assertSame('key-456', $dto->id);
        $this->assertSame('Staging Key', $dto->name);
        $this->assertSame('wxyz', $dto->tokenSuffix);
        $this->assertSame('all', $dto->scope);
        $this->assertSame(60, $dto->rateLimitPerMinute);
        $this->assertSame('2025-01-01T00:00:00+00:00', $dto->expiresAt);
        $this->assertTrue($dto->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenExpiresAtIsNull(): void
    {
        $dto = new ApiKeyDto('key-1', 'Key', 'abcd', expiresAt: null);
        $this->assertFalse($dto->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenExpiresAtIsEmpty(): void
    {
        $dto = new ApiKeyDto('key-1', 'Key', 'abcd', expiresAt: '   ');
        $this->assertFalse($dto->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenPastDate(): void
    {
        // 2025-01-01 is always in the past relative to current system time (2026)
        $dto = new ApiKeyDto('key-1', 'Key', 'abcd', expiresAt: '2025-01-01T00:00:00+00:00');
        $this->assertTrue($dto->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenFutureDate(): void
    {
        // 2099-01-01 is always in the future
        $dto = new ApiKeyDto('key-1', 'Key', 'abcd', expiresAt: '2099-01-01T00:00:00+00:00');
        $this->assertFalse($dto->isExpired());
    }

    public function testIsExpiredReturnsFalseOnMalformedDate(): void
    {
        $dto = new ApiKeyDto('key-1', 'Key', 'abcd', expiresAt: 'not-a-valid-date-string');
        $this->assertFalse($dto->isExpired());
    }

    public function testIsExpiredWithExplicitNowArgument(): void
    {
        $dto = new ApiKeyDto('key-1', 'Key', 'abcd', expiresAt: '2025-06-01T12:00:00+00:00');

        $before = new \DateTimeImmutable('2025-06-01T11:59:59+00:00');
        $exact = new \DateTimeImmutable('2025-06-01T12:00:00+00:00');
        $after = new \DateTimeImmutable('2025-06-01T12:00:01+00:00');

        $this->assertFalse($dto->isExpired($before));
        $this->assertTrue($dto->isExpired($exact));
        $this->assertTrue($dto->isExpired($after));
    }

    public function testConstructorAndToArrayWithAllowedAssertedRoles(): void
    {
        // 1. Array of strings
        $dtoWithRoles = new ApiKeyDto('key-1', 'Key', 'abcd', allowedAssertedRoles: ['authenticated', 'editor']);
        $this->assertSame(['authenticated', 'editor'], $dtoWithRoles->allowedAssertedRoles);
        $array = $dtoWithRoles->toArray();
        $this->assertArrayHasKey('allowed_asserted_roles', $array);
        $this->assertSame(['authenticated', 'editor'], $array['allowed_asserted_roles']);

        // 2. Empty array
        $dtoEmpty = new ApiKeyDto('key-2', 'Key', 'abcd', allowedAssertedRoles: []);
        $this->assertSame([], $dtoEmpty->allowedAssertedRoles);
        $arrayEmpty = $dtoEmpty->toArray();
        $this->assertArrayHasKey('allowed_asserted_roles', $arrayEmpty);
        $this->assertSame([], $arrayEmpty['allowed_asserted_roles']);

        // 3. Null (default / explicit)
        $dtoNull = new ApiKeyDto('key-3', 'Key', 'abcd', allowedAssertedRoles: null);
        $this->assertNull($dtoNull->allowedAssertedRoles);
        $arrayNull = $dtoNull->toArray();
        $this->assertArrayNotHasKey('allowed_asserted_roles', $arrayNull);
    }

    public function testFromArrayWithAllowedAssertedRoles(): void
    {
        // 1. Array of strings via snake_case
        $dtoSnake = ApiKeyDto::fromArray([
            'id' => 'k1',
            'name' => 'Key 1',
            'token_suffix' => '1111',
            'allowed_asserted_roles' => ['admin', 'manager'],
        ]);
        $this->assertSame(['admin', 'manager'], $dtoSnake->allowedAssertedRoles);
        $this->assertSame(['admin', 'manager'], $dtoSnake->toArray()['allowed_asserted_roles']);

        // 2. Array of strings via camelCase
        $dtoCamel = ApiKeyDto::fromArray([
            'id' => 'k2',
            'name' => 'Key 2',
            'token_suffix' => '2222',
            'allowedAssertedRoles' => ['viewer', 'editor'],
        ]);
        $this->assertSame(['viewer', 'editor'], $dtoCamel->allowedAssertedRoles);
        $this->assertSame(['viewer', 'editor'], $dtoCamel->toArray()['allowed_asserted_roles']);

        // 3. Empty array
        $dtoEmpty = ApiKeyDto::fromArray([
            'id' => 'k3',
            'name' => 'Key 3',
            'token_suffix' => '3333',
            'allowed_asserted_roles' => [],
        ]);
        $this->assertSame([], $dtoEmpty->allowedAssertedRoles);
        $this->assertSame([], $dtoEmpty->toArray()['allowed_asserted_roles']);

        // 4. Null
        $dtoNull = ApiKeyDto::fromArray([
            'id' => 'k4',
            'name' => 'Key 4',
            'token_suffix' => '4444',
            'allowed_asserted_roles' => null,
        ]);
        $this->assertNull($dtoNull->allowedAssertedRoles);
        $this->assertArrayNotHasKey('allowed_asserted_roles', $dtoNull->toArray());

        // 5. Absent
        $dtoAbsent = ApiKeyDto::fromArray([
            'id' => 'k5',
            'name' => 'Key 5',
            'token_suffix' => '5555',
        ]);
        $this->assertNull($dtoAbsent->allowedAssertedRoles);
        $this->assertArrayNotHasKey('allowed_asserted_roles', $dtoAbsent->toArray());
    }

    public function testFromArrayWithCoercibleItemsInAllowedAssertedRoles(): void
    {
        $dto = ApiKeyDto::fromArray([
            'id' => 'k-coercible',
            'name' => 'Coercible Key',
            'token_suffix' => '7777',
            'allowed_asserted_roles' => ['admin', 123, null, ['nested'], (object) ['foo' => 'bar']],
        ]);

        $this->assertSame(['admin', '123'], $dto->allowedAssertedRoles);
        $this->assertSame(['admin', '123'], $dto->toArray()['allowed_asserted_roles']);
    }

    public function testApiKeyCreatedDtoPassesThroughAllowedAssertedRoles(): void
    {
        // Constructor and toArray
        $dto = new ApiKeyCreatedDto(
            id: 'created-1',
            name: 'Created Key',
            tokenSuffix: '1234',
            token: 'secret-token-val',
            allowedAssertedRoles: ['authenticated', 'moderator']
        );
        $this->assertSame(['authenticated', 'moderator'], $dto->allowedAssertedRoles);
        $this->assertSame('secret-token-val', $dto->token);
        $array = $dto->toArray();
        $this->assertSame(['authenticated', 'moderator'], $array['allowed_asserted_roles']);
        $this->assertSame('secret-token-val', $array['token']);

        // fromArray with allowed_asserted_roles
        $fromArray = ApiKeyCreatedDto::fromArray([
            'id' => 'created-2',
            'name' => 'Created Key 2',
            'token_suffix' => '5678',
            'token' => 'plain-token-2',
            'allowed_asserted_roles' => ['guest'],
        ]);
        $this->assertSame(['guest'], $fromArray->allowedAssertedRoles);
        $this->assertSame('plain-token-2', $fromArray->token);
        $this->assertSame(['guest'], $fromArray->toArray()['allowed_asserted_roles']);

        // fromArray without allowed_asserted_roles
        $fromArrayAbsent = ApiKeyCreatedDto::fromArray([
            'id' => 'created-3',
            'name' => 'Created Key 3',
            'token_suffix' => '9999',
            'token' => 'plain-token-3',
        ]);
        $this->assertNull($fromArrayAbsent->allowedAssertedRoles);
        $this->assertArrayNotHasKey('allowed_asserted_roles', $fromArrayAbsent->toArray());
    }
}
