<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\RegisterDto;
use PHPUnit\Framework\TestCase;

class RegisterDtoTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $dto = new RegisterDto('Acme Agency', 'admin@acme.com', 'SecurePass1!');
        $this->assertSame('Acme Agency', $dto->orgName);
        $this->assertSame('admin@acme.com', $dto->email);
        $this->assertSame('SecurePass1!', $dto->password);
    }

    public function testToArray(): void
    {
        $dto = new RegisterDto('Acme Agency', 'admin@acme.com', 'SecurePass1!');
        $expected = [
            'org_name' => 'Acme Agency',
            'email' => 'admin@acme.com',
            'password' => 'SecurePass1!',
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    public function testFromArrayWithSnakeCase(): void
    {
        $data = [
            'org_name' => 'Acme Agency',
            'email' => 'admin@acme.com',
            'password' => 'SecurePass1!',
        ];

        $dto = RegisterDto::fromArray($data);
        $this->assertSame('Acme Agency', $dto->orgName);
        $this->assertSame('admin@acme.com', $dto->email);
        $this->assertSame('SecurePass1!', $dto->password);
    }

    public function testFromArrayWithCamelCase(): void
    {
        $data = [
            'orgName' => 'Acme Camel',
            'email' => 'camel@acme.com',
            'password' => 'SecretPass2@',
        ];

        $dto = RegisterDto::fromArray($data);
        $this->assertSame('Acme Camel', $dto->orgName);
        $this->assertSame('camel@acme.com', $dto->email);
        $this->assertSame('SecretPass2@', $dto->password);
    }

    public function testFromArrayWithNameAlias(): void
    {
        $data = [
            'name' => 'Acme Alias',
            'email' => 'alias@acme.com',
            'password' => 'SecretPass3#',
        ];

        $dto = RegisterDto::fromArray($data);
        $this->assertSame('Acme Alias', $dto->orgName);
        $this->assertSame('alias@acme.com', $dto->email);
        $this->assertSame('SecretPass3#', $dto->password);
    }

    public function testEmptyOrgNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('org_name cannot be empty.');
        new RegisterDto('   ', 'admin@acme.com', 'pass');
    }

    public function testEmptyEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('email cannot be empty.');
        new RegisterDto('Acme', '   ', 'pass');
    }

    public function testEmptyPasswordThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('password cannot be empty.');
        new RegisterDto('Acme', 'admin@acme.com', '');
    }
}
