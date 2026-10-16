<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class UserDto
{
    use ExtractsResponseDataTrait;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly array $roles = [],
        public readonly bool $mustChangePassword = false,
        public readonly ?string $deletedAt = null,
        public readonly string $createdAt = '',
        public readonly bool $isVerified = false,
    ) {
        if (trim($this->id) === '') {
            throw new \InvalidArgumentException('id cannot be empty.');
        }
        if (trim($this->email) === '') {
            throw new \InvalidArgumentException('email cannot be empty.');
        }
    }

    public function getRole(): string
    {
        return $this->roles[0] ?? 'member';
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->roles,
            'must_change_password' => $this->mustChangePassword,
            'is_verified' => $this->isVerified,
            'created_at' => $this->createdAt,
        ];

        if ($this->deletedAt !== null) {
            $data['deleted_at'] = $this->deletedAt;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = self::extractString($data['id'] ?? null, '');
        $email = self::extractString($data['email'] ?? null, '');

        /** @var list<string> $roles */
        $roles = [];
        if (isset($data['roles']) && is_array($data['roles'])) {
            $roles = self::extractStringList($data['roles']);
        } elseif (isset($data['role']) && is_string($data['role']) && trim($data['role']) !== '') {
            $roles = [trim($data['role'])];
        }

        $mustChangePassword = self::extractBool($data['must_change_password'] ?? $data['mustChangePassword'] ?? null, false);
        $isVerified = self::extractBool($data['is_verified'] ?? $data['isVerified'] ?? null, false);
        $deletedAt = self::extractNullableString($data['deleted_at'] ?? $data['deletedAt'] ?? null);
        $createdAt = self::extractString($data['created_at'] ?? $data['createdAt'] ?? null, '');

        return new self(
            $id,
            $email,
            $roles,
            $mustChangePassword,
            $deletedAt,
            $createdAt,
            $isVerified
        );
    }
}
