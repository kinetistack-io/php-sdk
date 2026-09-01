<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Enum;

use KinetiStack\Sdk\Enum\JobStatus;
use PHPUnit\Framework\TestCase;

class JobStatusTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('pending', JobStatus::Pending->value);
        $this->assertSame('processing', JobStatus::Processing->value);
        $this->assertSame('completed', JobStatus::Completed->value);
        $this->assertSame('failed', JobStatus::Failed->value);
    }

    public function testTryFromValidValues(): void
    {
        $this->assertSame(JobStatus::Pending, JobStatus::tryFrom('pending'));
        $this->assertSame(JobStatus::Processing, JobStatus::tryFrom('processing'));
        $this->assertSame(JobStatus::Completed, JobStatus::tryFrom('completed'));
        $this->assertSame(JobStatus::Failed, JobStatus::tryFrom('failed'));
    }

    public function testTryFromInvalidValuesReturnsNull(): void
    {
        /** @var string $unknown */
        $unknown = 'unknown';
        /** @var string $queued */
        $queued = 'queued';
        /** @var string $empty */
        $empty = '';

        $this->assertNull(JobStatus::tryFrom($unknown));
        $this->assertNull(JobStatus::tryFrom($queued));
        $this->assertNull(JobStatus::tryFrom($empty));
    }

    public function testIsTerminal(): void
    {
        $this->assertFalse(JobStatus::Pending->isTerminal());
        $this->assertFalse(JobStatus::Processing->isTerminal());
        $this->assertTrue(JobStatus::Completed->isTerminal());
        $this->assertTrue(JobStatus::Failed->isTerminal());
    }
}
