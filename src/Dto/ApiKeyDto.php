<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ApiKeyDto
{
    use ExtractsResponseDataTrait;

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $tokenSuffix,
        public readonly string $scope = 'all',
        public readonly ?int $rateLimitPerMinute = null,
        public readonly ?string $expiresAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $revokedAt = null,
        public readonly ?string $gracePeriodUntil = null,
        public readonly ?int $dailyTokenQuotaOverride = null,
        public readonly ?int $tokensConsumedToday = null,
        public readonly ?int $requestsToday = null,
    ) {
        if (trim($this->id) === '') {
            throw new \InvalidArgumentException('id cannot be empty.');
        }
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('name cannot be empty.');
        }
        if ($this->dailyTokenQuotaOverride !== null && $this->dailyTokenQuotaOverride <= 0) {
            throw new \InvalidArgumentException('dailyTokenQuotaOverride must be greater than 0.');
        }
        if ($this->tokensConsumedToday !== null && $this->tokensConsumedToday < 0) {
            throw new \InvalidArgumentException('tokensConsumedToday must be greater than or equal to 0.');
        }
        if ($this->requestsToday !== null && $this->requestsToday < 0) {
            throw new \InvalidArgumentException('requestsToday must be greater than or equal to 0.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'token_suffix' => $this->tokenSuffix,
            'scope' => $this->scope,
        ];

        if ($this->rateLimitPerMinute !== null) {
            $data['rate_limit_per_minute'] = $this->rateLimitPerMinute;
        }

        if ($this->expiresAt !== null) {
            $data['expires_at'] = $this->expiresAt;
        }

        if ($this->createdAt !== null) {
            $data['created_at'] = $this->createdAt;
        }

        if ($this->revokedAt !== null) {
            $data['revoked_at'] = $this->revokedAt;
        }

        if ($this->gracePeriodUntil !== null) {
            $data['grace_period_until'] = $this->gracePeriodUntil;
        }

        if ($this->dailyTokenQuotaOverride !== null) {
            $data['daily_token_quota_override'] = $this->dailyTokenQuotaOverride;
        }

        if ($this->tokensConsumedToday !== null) {
            $data['tokens_consumed_today'] = $this->tokensConsumedToday;
        }

        if ($this->requestsToday !== null) {
            $data['requests_today'] = $this->requestsToday;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = self::extractString($data['id'] ?? '');
        $name = self::extractString($data['name'] ?? '');
        if (trim($name) === '') {
            $name = 'API Key';
        }
        $tokenSuffix = self::extractString($data['token_suffix'] ?? $data['tokenSuffix'] ?? '');
        $scope = self::extractString($data['scope'] ?? null, 'all');
        $rateLimitPerMinute = self::extractNullableInt($data['rate_limit_per_minute'] ?? $data['rateLimitPerMinute'] ?? null);
        $expiresAt = self::extractNullableString($data['expires_at'] ?? $data['expiresAt'] ?? null);
        $createdAt = self::extractNullableString($data['created_at'] ?? $data['createdAt'] ?? null);
        $revokedAt = self::extractNullableString($data['revoked_at'] ?? $data['revokedAt'] ?? null);
        $gracePeriodUntil = self::extractNullableString($data['grace_period_until'] ?? $data['gracePeriodUntil'] ?? null);
        $dailyTokenQuotaOverride = self::extractNullableInt($data['daily_token_quota_override'] ?? $data['dailyTokenQuotaOverride'] ?? null);
        $tokensConsumedToday = self::extractNullableInt($data['tokens_consumed_today'] ?? $data['tokensConsumedToday'] ?? null);
        $requestsToday = self::extractNullableInt($data['requests_today'] ?? $data['requestsToday'] ?? null);

        return new self(
            $id,
            $name,
            $tokenSuffix,
            $scope,
            $rateLimitPerMinute,
            $expiresAt,
            $createdAt,
            $revokedAt,
            $gracePeriodUntil,
            $dailyTokenQuotaOverride,
            $tokensConsumedToday,
            $requestsToday
        );
    }
}
