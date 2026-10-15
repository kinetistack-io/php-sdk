<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\RegisterResponseDto;
use PHPUnit\Framework\TestCase;

class RegisterResponseDtoTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $dto = new RegisterResponseDto(
            'If this address can be registered, a verification email has been sent.',
            'admin@acme.com'
        );

        $this->assertSame('If this address can be registered, a verification email has been sent.', $dto->message);
        $this->assertSame('admin@acme.com', $dto->email);
        $this->assertSame('If this address can be registered, a verification email has been sent.', $dto->getMessage());
        $this->assertSame('admin@acme.com', $dto->getEmail());
    }

    public function testToArray(): void
    {
        $dto = new RegisterResponseDto('Verification email sent.', 'admin@betacorp.com');
        $array = $dto->toArray();

        $this->assertSame([
            'message' => 'Verification email sent.',
            'email' => 'admin@betacorp.com',
        ], $array);
    }

    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            'message' => 'Verification email sent.',
            'email' => 'lead@agency.com',
        ];

        $dto = RegisterResponseDto::fromArray($data);

        $this->assertSame('Verification email sent.', $dto->getMessage());
        $this->assertSame('lead@agency.com', $dto->getEmail());
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = RegisterResponseDto::fromArray([]);

        $this->assertSame('', $dto->getMessage());
        $this->assertSame('', $dto->getEmail());
    }
}
