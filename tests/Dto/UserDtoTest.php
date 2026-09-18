<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\UserDto;
use KinetiStack\Sdk\Dto\UserProjectAssignmentDto;
use PHPUnit\Framework\TestCase;

class UserDtoTest extends TestCase
{
    public function testUserDtoConstructorAndGetters(): void
    {
        $dto = new UserDto(
            'user-123',
            'admin@example.com',
            ['admin', 'member'],
            true,
            null,
            '2026-09-18T00:00:00Z'
        );

        $this->assertSame('user-123', $dto->id);
        $this->assertSame('admin@example.com', $dto->email);
        $this->assertSame(['admin', 'member'], $dto->roles);
        $this->assertTrue($dto->mustChangePassword);
        $this->assertNull($dto->deletedAt);
        $this->assertSame('2026-09-18T00:00:00Z', $dto->createdAt);
        $this->assertSame('admin', $dto->getRole());
        $this->assertTrue($dto->hasRole('admin'));
        $this->assertTrue($dto->hasRole('member'));
        $this->assertFalse($dto->hasRole('super_admin'));
    }

    public function testUserDtoEmptyIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id cannot be empty.');
        new UserDto('', 'admin@example.com');
    }

    public function testUserDtoEmptyEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('email cannot be empty.');
        new UserDto('user-123', '   ');
    }

    public function testUserDtoToArray(): void
    {
        $dto = new UserDto(
            'user-123',
            'user@example.com',
            ['member'],
            false,
            '2026-09-18T10:00:00Z',
            '2026-09-18T00:00:00Z'
        );

        $array = $dto->toArray();
        $this->assertSame('user-123', $array['id']);
        $this->assertSame('user@example.com', $array['email']);
        $this->assertSame(['member'], $array['roles']);
        $this->assertFalse($array['must_change_password']);
        $this->assertSame('2026-09-18T10:00:00Z', $array['deleted_at']);
        $this->assertSame('2026-09-18T00:00:00Z', $array['created_at']);
    }

    public function testUserDtoFromArrayWithRolesArray(): void
    {
        $data = [
            'id' => 'u-1',
            'email' => 'u1@example.com',
            'roles' => ['admin'],
            'must_change_password' => true,
            'created_at' => '2026-09-18T08:00:00Z',
        ];

        $dto = UserDto::fromArray($data);
        $this->assertSame('u-1', $dto->id);
        $this->assertSame('u1@example.com', $dto->email);
        $this->assertSame(['admin'], $dto->roles);
        $this->assertTrue($dto->mustChangePassword);
        $this->assertNull($dto->deletedAt);
        $this->assertSame('2026-09-18T08:00:00Z', $dto->createdAt);
    }

    public function testUserDtoFromArrayWithSingleRoleString(): void
    {
        $data = [
            'id' => 'u-2',
            'email' => 'u2@example.com',
            'role' => 'member',
            'mustChangePassword' => false,
            'deletedAt' => '2026-09-18T09:00:00Z',
            'createdAt' => '2026-09-18T08:00:00Z',
        ];

        $dto = UserDto::fromArray($data);
        $this->assertSame('u-2', $dto->id);
        $this->assertSame('u2@example.com', $dto->email);
        $this->assertSame(['member'], $dto->roles);
        $this->assertSame('member', $dto->getRole());
        $this->assertFalse($dto->mustChangePassword);
        $this->assertSame('2026-09-18T09:00:00Z', $dto->deletedAt);
        $this->assertSame('2026-09-18T08:00:00Z', $dto->createdAt);
    }

    public function testUserProjectAssignmentDtoConstructorAndGetters(): void
    {
        $dto = new UserProjectAssignmentDto(
            'user-456',
            'proj-123',
            'manager',
            '2026-09-18T08:00:00Z',
            'member@example.com'
        );

        $this->assertSame('user-456', $dto->userId);
        $this->assertSame('proj-123', $dto->projectId);
        $this->assertSame('manager', $dto->permission);
        $this->assertSame('2026-09-18T08:00:00Z', $dto->assignedAt);
        $this->assertSame('member@example.com', $dto->email);
    }

    public function testUserProjectAssignmentDtoEmptyUserIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('userId cannot be empty.');
        new UserProjectAssignmentDto('   ');
    }

    public function testUserProjectAssignmentDtoToArray(): void
    {
        $dto = new UserProjectAssignmentDto(
            'user-456',
            'proj-123',
            'member',
            '2026-09-18T08:00:00Z',
            'test@example.com'
        );

        $array = $dto->toArray();
        $this->assertSame('user-456', $array['user_id']);
        $this->assertSame('proj-123', $array['project_id']);
        $this->assertSame('member', $array['permission']);
        $this->assertSame('2026-09-18T08:00:00Z', $array['assigned_at']);
        $this->assertSame('test@example.com', $array['email']);
    }

    public function testUserProjectAssignmentDtoFromArray(): void
    {
        $data = [
            'user_id' => 'user-456',
            'permission' => 'manager',
            'assigned_at' => '2026-09-18T08:00:00Z',
            'email' => 'manager@example.com',
        ];

        $dto = UserProjectAssignmentDto::fromArray($data, 'proj-123');
        $this->assertSame('user-456', $dto->userId);
        $this->assertSame('proj-123', $dto->projectId);
        $this->assertSame('manager', $dto->permission);
        $this->assertSame('2026-09-18T08:00:00Z', $dto->assignedAt);
        $this->assertSame('manager@example.com', $dto->email);
    }
}
