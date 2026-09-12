<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\RegistrationStatusDto;
use KinetiStack\Sdk\Enum\RegistrationMode;
use PHPUnit\Framework\TestCase;

class RegistrationStatusDtoTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $dto = new RegistrationStatusDto(RegistrationMode::Open);
        $this->assertSame(RegistrationMode::Open, $dto->mode);
        $this->assertTrue($dto->isOpen());
        $this->assertFalse($dto->isWhitelist());
        $this->assertFalse($dto->isClosed());
    }

    public function testFromArrayWithEnum(): void
    {
        $dto = RegistrationStatusDto::fromArray(['mode' => RegistrationMode::Whitelist]);
        $this->assertSame(RegistrationMode::Whitelist, $dto->mode);
        $this->assertFalse($dto->isOpen());
        $this->assertTrue($dto->isWhitelist());
        $this->assertFalse($dto->isClosed());
    }

    public function testFromArrayWithString(): void
    {
        $open = RegistrationStatusDto::fromArray(['mode' => 'open']);
        $this->assertSame(RegistrationMode::Open, $open->mode);
        $this->assertTrue($open->isOpen());

        $whitelist = RegistrationStatusDto::fromArray(['mode' => 'whitelist']);
        $this->assertSame(RegistrationMode::Whitelist, $whitelist->mode);
        $this->assertTrue($whitelist->isWhitelist());

        $closed = RegistrationStatusDto::fromArray(['mode' => 'closed']);
        $this->assertSame(RegistrationMode::Closed, $closed->mode);
        $this->assertTrue($closed->isClosed());
    }

    public function testFromArrayFallbackToClosed(): void
    {
        $missing = RegistrationStatusDto::fromArray([]);
        $this->assertSame(RegistrationMode::Closed, $missing->mode);
        $this->assertTrue($missing->isClosed());

        $unknown = RegistrationStatusDto::fromArray(['mode' => 'unrecognized']);
        $this->assertSame(RegistrationMode::Closed, $unknown->mode);
        $this->assertTrue($unknown->isClosed());
    }

    public function testToArray(): void
    {
        $dto = new RegistrationStatusDto(RegistrationMode::Whitelist);
        $this->assertSame(['mode' => 'whitelist'], $dto->toArray());
    }

    public function testFromArrayWithNonStringValuesFallbackToClosed(): void
    {
        $arrayValue = RegistrationStatusDto::fromArray(['mode' => ['nested' => 'array']]);
        $this->assertSame(RegistrationMode::Closed, $arrayValue->mode);
        $this->assertTrue($arrayValue->isClosed());

        $intValue = RegistrationStatusDto::fromArray(['mode' => 123]);
        $this->assertSame(RegistrationMode::Closed, $intValue->mode);
        $this->assertTrue($intValue->isClosed());

        $objectValue = RegistrationStatusDto::fromArray(['mode' => new \stdClass()]);
        $this->assertSame(RegistrationMode::Closed, $objectValue->mode);
        $this->assertTrue($objectValue->isClosed());
    }
}
