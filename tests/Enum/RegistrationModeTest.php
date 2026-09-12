<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Enum;

use KinetiStack\Sdk\Enum\RegistrationMode;
use PHPUnit\Framework\TestCase;

class RegistrationModeTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('open', RegistrationMode::Open->value);
        $this->assertSame('whitelist', RegistrationMode::Whitelist->value);
        $this->assertSame('closed', RegistrationMode::Closed->value);
    }

    public function testTryFromValidValues(): void
    {
        $this->assertSame(RegistrationMode::Open, RegistrationMode::tryFrom('open'));
        $this->assertSame(RegistrationMode::Whitelist, RegistrationMode::tryFrom('whitelist'));
        $this->assertSame(RegistrationMode::Closed, RegistrationMode::tryFrom('closed'));
    }

    public function testTryFromInvalidValuesReturnsNull(): void
    {
        /** @var string $invalid */
        $invalid = 'invalid';
        /** @var string $empty */
        $empty = '';
        /** @var string $upper */
        $upper = 'OPEN';

        $this->assertNull(RegistrationMode::tryFrom($invalid));
        $this->assertNull(RegistrationMode::tryFrom($empty));
        $this->assertNull(RegistrationMode::tryFrom($upper));
    }
}
