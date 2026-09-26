<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ApiKeyDto
{
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
    ) {
        if (trim($this->id) === '') {
            throw new \InvalidArgumentException('id cannot be empty.');
        }
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('name cannot be empty.');
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

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? '');
        $name = (string) ($data['name'] ?? '');
        if (trim($name) === '') {
            $name = 'API Key';
        }
        $tokenSuffix = (string) ($data['token_suffix'] ?? $data['tokenSuffix'] ?? '');
        $scope = (string) ($data['scope'] ?? 'all');
        $rateLimitPerMinute = isset($data['rate_limit_per_minute'])
            ? (int) $data['rate_limit_per_minute']
            : (isset($data['rateLimitPerMinute']) ? (int) $data['rateLimitPerMinute'] : null);
        $expiresAt = isset($data['expires_at']) && is_string($data['expires_at'])
            ? $data['expires_at']
            : (isset($data['expiresAt']) && is_string($data['expiresAt']) ? $data['expiresAt'] : null);
        $createdAt = isset($data['created_at']) && is_string($data['created_at'])
            ? $data['created_at']
            : (isset($data['createdAt']) && is_string($data['createdAt']) ? $data['createdAt'] : null);
        $revokedAt = isset($data['revoked_at']) && is_string($data['revoked_at'])
            ? $data['revoked_at']
            : (isset($data['revokedAt']) && is_string($data['revokedAt']) ? $data['revokedAt'] : null);
        $gracePeriodUntil = isset($data['grace_period_until']) && is_string($data['grace_period_until'])
            ? $data['grace_period_until']
            : (isset($data['gracePeriodUntil']) && is_string($data['gracePeriodUntil']) ? $data['gracePeriodUntil'] : null);

        return new self(
            $id,
            $name,
            $tokenSuffix,
            $scope,
            $rateLimitPerMinute,
            $expiresAt,
            $createdAt,
            $revokedAt,
            $gracePeriodUntil
        );
    }
}
