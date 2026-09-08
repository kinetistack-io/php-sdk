<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ApiKeyCreatedDto extends ApiKeyDto
{
    public function __construct(
        string $id,
        string $name,
        string $tokenSuffix,
        public readonly string $token,
        string $scope = 'all',
        ?int $rateLimitPerMinute = null,
        ?string $expiresAt = null,
        ?string $createdAt = null,
        ?string $revokedAt = null,
        ?string $gracePeriodUntil = null,
    ) {
        if (trim($token) === '') {
            throw new \InvalidArgumentException('token cannot be empty.');
        }

        parent::__construct(
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['token'] = $this->token;

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $base = parent::fromArray($data);

        return new self(
            $base->id,
            $base->name,
            $base->tokenSuffix,
            (string) ($data['token'] ?? ''),
            $base->scope,
            $base->rateLimitPerMinute,
            $base->expiresAt,
            $base->createdAt,
            $base->revokedAt,
            $base->gracePeriodUntil
        );
    }
}
