<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\ExtractsResponseDataTrait;
use PHPUnit\Framework\TestCase;

class ExtractsResponseDataTraitTest extends TestCase
{
    use ExtractsResponseDataTrait;

    public function testExtractStringWithValidTypes(): void
    {
        $this->assertSame('hello', self::extractString('hello'));
        $this->assertSame('123', self::extractString(123));
        $this->assertSame('45.67', self::extractString(45.67));

        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-output';
            }
        };
        $this->assertSame('stringable-output', self::extractString($stringable));
    }

    public function testExtractStringExcludesBooleansAndReturnsDefault(): void
    {
        $this->assertSame('default_val', self::extractString(true, 'default_val'));
        $this->assertSame('default_val', self::extractString(false, 'default_val'));
        $this->assertSame('', self::extractString(true));
        $this->assertSame('', self::extractString(false));
    }

    public function testExtractStringWithNonConvertibleTypesReturnsDefault(): void
    {
        $this->assertSame('default_val', self::extractString(null, 'default_val'));
        $this->assertSame('default_val', self::extractString(['array'], 'default_val'));
        $this->assertSame('default_val', self::extractString(new \stdClass(), 'default_val'));
    }

    public function testExtractNullableStringWithValidTypes(): void
    {
        $this->assertSame('hello', self::extractNullableString('hello'));
        $this->assertSame('123', self::extractNullableString(123));
        $this->assertSame('45.67', self::extractNullableString(45.67));

        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-output';
            }
        };
        $this->assertSame('stringable-output', self::extractNullableString($stringable));
    }

    public function testExtractNullableStringExcludesBooleansAndReturnsDefault(): void
    {
        $this->assertNull(self::extractNullableString(true));
        $this->assertNull(self::extractNullableString(false));
        $this->assertSame('fallback', self::extractNullableString(true, 'fallback'));
        $this->assertSame('fallback', self::extractNullableString(false, 'fallback'));
    }

    public function testExtractNullableStringWithNullAndNonConvertibleTypes(): void
    {
        $this->assertNull(self::extractNullableString(null));
        $this->assertSame('fallback', self::extractNullableString(null, 'fallback'));
        $this->assertNull(self::extractNullableString(['array']));
        $this->assertSame('fallback', self::extractNullableString(['array'], 'fallback'));
        $this->assertNull(self::extractNullableString(new \stdClass()));
    }

    public function testExtractStringListWithValidTypes(): void
    {
        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-item';
            }
        };

        $result = self::extractStringList(['hello', 123, 45.67, $stringable]);
        $this->assertSame(['hello', '123', '45.67', 'stringable-item'], $result);
    }

    public function testExtractStringListExcludesBooleans(): void
    {
        $result = self::extractStringList(['alpha', true, false, 'beta', 10]);
        $this->assertSame(['alpha', 'beta', '10'], $result);
    }

    public function testExtractStringListWithNonArrayReturnsDefault(): void
    {
        $this->assertSame([], self::extractStringList(null));
        $this->assertSame([], self::extractStringList('not-an-array'));
        $this->assertSame(['fallback'], self::extractStringList(123, ['fallback']));
    }
}
