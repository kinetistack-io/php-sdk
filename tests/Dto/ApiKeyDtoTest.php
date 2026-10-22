<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

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
}
