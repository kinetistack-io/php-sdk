<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\RegisterResponseDto;
use PHPUnit\Framework\TestCase;

class RegisterResponseDtoTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $org = [
            'id' => 'org-uuid-1',
            'name' => 'Acme Agency',
            'billing_tier' => 'pro',
        ];
        $user = [
            'id' => 'user-uuid-1',
            'email' => 'admin@acme.com',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ];

        $dto = new RegisterResponseDto($org, $user);

        $this->assertSame($org, $dto->organization);
        $this->assertSame($user, $dto->user);
        $this->assertSame('org-uuid-1', $dto->getOrganizationId());
        $this->assertSame('Acme Agency', $dto->getOrganizationName());
        $this->assertSame('pro', $dto->getBillingTier());
        $this->assertSame('user-uuid-1', $dto->getUserId());
        $this->assertSame('admin@acme.com', $dto->getUserEmail());
        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $dto->getUserRoles());
    }

    public function testToArray(): void
    {
        $org = [
            'id' => 'org-uuid-2',
            'name' => 'Beta Corp',
            'billing_tier' => 'enterprise',
        ];
        $user = [
            'id' => 'user-uuid-2',
            'email' => 'admin@betacorp.com',
            'roles' => ['ROLE_ADMIN'],
        ];

        $dto = new RegisterResponseDto($org, $user);
        $array = $dto->toArray();

        $this->assertSame([
            'organization' => $org,
            'user' => $user,
        ], $array);
    }

    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            'organization' => [
                'id' => 'org-uuid-123',
                'name' => 'Complete Agency',
                'billing_tier' => 'standard',
            ],
            'user' => [
                'id' => 'user-uuid-456',
                'email' => 'lead@agency.com',
                'roles' => ['ROLE_ADMIN'],
            ],
        ];

        $dto = RegisterResponseDto::fromArray($data);

        $this->assertSame('org-uuid-123', $dto->getOrganizationId());
        $this->assertSame('Complete Agency', $dto->getOrganizationName());
        $this->assertSame('standard', $dto->getBillingTier());
        $this->assertSame('user-uuid-456', $dto->getUserId());
        $this->assertSame('lead@agency.com', $dto->getUserEmail());
        $this->assertSame(['ROLE_ADMIN'], $dto->getUserRoles());
    }

    public function testFromArrayWithDefaults(): void
    {
        $data = [
            'organization' => [
                'id' => 'org-uuid-999',
                'name' => 'Default Agency',
            ],
            'user' => [
                'id' => 'user-uuid-999',
                'email' => 'default@agency.com',
            ],
        ];

        $dto = RegisterResponseDto::fromArray($data);

        $this->assertSame('org-uuid-999', $dto->getOrganizationId());
        $this->assertSame('Default Agency', $dto->getOrganizationName());
        $this->assertSame('free', $dto->getBillingTier());
        $this->assertSame('user-uuid-999', $dto->getUserId());
        $this->assertSame('default@agency.com', $dto->getUserEmail());
        $this->assertSame([], $dto->getUserRoles());
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = RegisterResponseDto::fromArray([]);

        $this->assertSame('', $dto->getOrganizationId());
        $this->assertSame('', $dto->getOrganizationName());
        $this->assertSame('free', $dto->getBillingTier());
        $this->assertSame('', $dto->getUserId());
        $this->assertSame('', $dto->getUserEmail());
        $this->assertSame([], $dto->getUserRoles());
    }
}
