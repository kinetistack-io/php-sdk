<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class RegisterResponseDto
{
    /**
     * @param array{id: string, name: string, billing_tier: string} $organization
     * @param array{id: string, email: string, roles: list<string>} $user
     */
    public function __construct(
        public readonly array $organization,
        public readonly array $user,
    ) {
    }

    public function getOrganizationId(): string
    {
        return $this->organization['id'];
    }

    public function getOrganizationName(): string
    {
        return $this->organization['name'];
    }

    public function getBillingTier(): string
    {
        return $this->organization['billing_tier'];
    }

    public function getUserId(): string
    {
        return $this->user['id'];
    }

    public function getUserEmail(): string
    {
        return $this->user['email'];
    }

    /**
     * @return list<string>
     */
    public function getUserRoles(): array
    {
        return $this->user['roles'];
    }

    /**
     * @return array{organization: array{id: string, name: string, billing_tier: string}, user: array{id: string, email: string, roles: list<string>}}
     */
    public function toArray(): array
    {
        return [
            'organization' => $this->organization,
            'user' => $this->user,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $org */
        $org = isset($data['organization']) && is_array($data['organization'])
            ? $data['organization']
            : [];

        /** @var array<string, mixed> $user */
        $user = isset($data['user']) && is_array($data['user'])
            ? $data['user']
            : [];

        /** @var list<string> $roles */
        $roles = [];
        if (isset($user['roles']) && is_array($user['roles'])) {
            foreach ($user['roles'] as $role) {
                $roles[] = (string) $role;
            }
        }

        return new self(
            [
                'id' => (string) ($org['id'] ?? ''),
                'name' => (string) ($org['name'] ?? ''),
                'billing_tier' => (string) ($org['billing_tier'] ?? $org['billingTier'] ?? 'free'),
            ],
            [
                'id' => (string) ($user['id'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'roles' => $roles,
            ]
        );
    }
}
