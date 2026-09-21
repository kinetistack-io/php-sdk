<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Enum;

use KinetiStack\Sdk\Enum\AnalyticsGrouping;
use PHPUnit\Framework\TestCase;

class AnalyticsGroupingTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('day', AnalyticsGrouping::DAY->value);
        $this->assertSame('week', AnalyticsGrouping::WEEK->value);
        $this->assertSame('month', AnalyticsGrouping::MONTH->value);
    }

    public function testTryFromValidValues(): void
    {
        $this->assertSame(AnalyticsGrouping::DAY, AnalyticsGrouping::tryFrom('day'));
        $this->assertSame(AnalyticsGrouping::WEEK, AnalyticsGrouping::tryFrom('week'));
        $this->assertSame(AnalyticsGrouping::MONTH, AnalyticsGrouping::tryFrom('month'));
    }

    public function testTryFromInvalidValuesReturnsNull(): void
    {
        /** @var string $year */
        $year = 'year';
        /** @var string $quarter */
        $quarter = 'quarter';
        /** @var string $empty */
        $empty = '';
        /** @var string $dayUpper */
        $dayUpper = 'DAY';

        $this->assertNull(AnalyticsGrouping::tryFrom($year));
        $this->assertNull(AnalyticsGrouping::tryFrom($quarter));
        $this->assertNull(AnalyticsGrouping::tryFrom($empty));
        $this->assertNull(AnalyticsGrouping::tryFrom($dayUpper));
    }

    public function testCasesCount(): void
    {
        $cases = AnalyticsGrouping::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(AnalyticsGrouping::DAY, $cases);
        $this->assertContains(AnalyticsGrouping::WEEK, $cases);
        $this->assertContains(AnalyticsGrouping::MONTH, $cases);
    }
}
