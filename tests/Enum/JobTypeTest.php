<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Enum;

use KinetiStack\Sdk\Enum\JobType;
use PHPUnit\Framework\TestCase;

class JobTypeTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('vision_single', JobType::VisionSingle->value);
        $this->assertSame('vision_batch', JobType::VisionBatch->value);
        $this->assertSame('document_ingest', JobType::DocumentIngest->value);
    }

    public function testTryFromValidValues(): void
    {
        $this->assertSame(JobType::VisionSingle, JobType::tryFrom('vision_single'));
        $this->assertSame(JobType::VisionBatch, JobType::tryFrom('vision_batch'));
        $this->assertSame(JobType::DocumentIngest, JobType::tryFrom('document_ingest'));
    }

    public function testTryFromInvalidValuesReturnsNull(): void
    {
        /** @var string $unknown */
        $unknown = 'unknown';
        /** @var string $queued */
        $queued = 'queued';
        /** @var string $empty */
        $empty = '';

        $this->assertNull(JobType::tryFrom($unknown));
        $this->assertNull(JobType::tryFrom($queued));
        $this->assertNull(JobType::tryFrom($empty));
    }

    public function testCases(): void
    {
        $cases = JobType::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(JobType::VisionSingle, $cases);
        $this->assertContains(JobType::VisionBatch, $cases);
        $this->assertContains(JobType::DocumentIngest, $cases);
    }

    public function testDomainHelpers(): void
    {
        $single = JobType::VisionSingle;
        $this->assertTrue($single->isVision());
        $this->assertTrue($single->isSingleVision());
        $this->assertFalse($single->isBatch());
        $this->assertFalse($single->isDocument());

        $batch = JobType::VisionBatch;
        $this->assertTrue($batch->isVision());
        $this->assertFalse($batch->isSingleVision());
        $this->assertTrue($batch->isBatch());
        $this->assertFalse($batch->isDocument());

        $document = JobType::DocumentIngest;
        $this->assertFalse($document->isVision());
        $this->assertFalse($document->isSingleVision());
        $this->assertFalse($document->isBatch());
        $this->assertTrue($document->isDocument());
    }
}
