<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\BatchJobDto;
use KinetiStack\Sdk\Dto\BatchJobItemResultDto;
use KinetiStack\Sdk\Enum\JobStatus;
use KinetiStack\Sdk\Enum\WebhookStatus;
use PHPUnit\Framework\TestCase;

class BatchJobDtoTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new BatchJobItemResultDto(
            externalId: 'img-1',
            altText: 'A photo',
            caption: 'Caption',
            tags: ['nature', 'forest'],
            confidenceScore: 0.95,
            error: null
        );

        $dto = new BatchJobDto(
            jobId: 'job-123',
            status: JobStatus::Processing,
            totalImages: 10,
            processedImages: 5,
            webhookStatus: WebhookStatus::Pending,
            results: [$result],
            createdAt: '2026-09-01T12:00:00Z',
            completedAt: null,
            pollUrl: 'https://api.test/api/v1/jobs/job-123'
        );

        $this->assertSame('job-123', $dto->jobId);
        $this->assertSame(JobStatus::Processing, $dto->status);
        $this->assertSame(10, $dto->totalImages);
        $this->assertSame(5, $dto->processedImages);
        $this->assertSame(WebhookStatus::Pending, $dto->webhookStatus);
        $this->assertNotNull($dto->results);
        $this->assertCount(1, $dto->results);
        $this->assertSame('2026-09-01T12:00:00Z', $dto->createdAt);
        $this->assertNull($dto->completedAt);
        $this->assertSame('https://api.test/api/v1/jobs/job-123', $dto->pollUrl);
    }

    public function testFromArrayHydratesEnums(): void
    {
        $data = [
            'job_id' => 'job-456',
            'status' => 'completed',
            'total_images' => 4,
            'processed_images' => 4,
            'webhook_status' => 'delivered',
            'results' => [
                [
                    'external_id' => 'img-1',
                    'altText' => 'Tree',
                    'caption' => 'Green tree',
                    'tags' => ['tree', 'green'],
                    'confidence_score' => 0.98,
                ],
            ],
            'created_at' => '2026-09-01T10:00:00Z',
            'completed_at' => '2026-09-01T10:01:00Z',
            'poll_url' => 'https://api.test/api/v1/jobs/job-456',
        ];

        $dto = BatchJobDto::fromArray($data);

        $this->assertSame('job-456', $dto->jobId);
        $this->assertSame(JobStatus::Completed, $dto->status);
        $this->assertSame(4, $dto->totalImages);
        $this->assertSame(4, $dto->processedImages);
        $this->assertSame(WebhookStatus::Delivered, $dto->webhookStatus);
        $this->assertNotNull($dto->results);
        $this->assertCount(1, $dto->results);
        $this->assertSame('img-1', $dto->results[0]->externalId);
        $this->assertSame('Tree', $dto->results[0]->altText);
        $this->assertSame('Green tree', $dto->results[0]->caption);
        $this->assertSame(['tree', 'green'], $dto->results[0]->tags);
        $this->assertSame(0.98, $dto->results[0]->confidenceScore);
        $this->assertTrue($dto->isCompleted());
        $this->assertFalse($dto->isFailed());
        $this->assertFalse($dto->isProcessing());
        $this->assertFalse($dto->isPending());
    }

    public function testFromArrayWithDirectEnumInstancesAndCamelCase(): void
    {
        $data = [
            'jobId' => 'job-789',
            'status' => JobStatus::Processing,
            'totalImages' => 8,
            'processedImages' => 2,
            'webhookStatus' => WebhookStatus::Pending,
            'createdAt' => '2026-09-01T11:00:00Z',
            'completedAt' => null,
            'pollUrl' => 'https://api.test/api/v1/jobs/job-789',
        ];

        $dto = BatchJobDto::fromArray($data);

        $this->assertSame('job-789', $dto->jobId);
        $this->assertSame(JobStatus::Processing, $dto->status);
        $this->assertSame(8, $dto->totalImages);
        $this->assertSame(2, $dto->processedImages);
        $this->assertSame(WebhookStatus::Pending, $dto->webhookStatus);
        $this->assertNull($dto->results);
        $this->assertFalse($dto->isCompleted());
        $this->assertTrue($dto->isProcessing());
    }

    public function testFromArrayDefaultsAndUnknownStatusFallback(): void
    {
        $data = [
            'job_id' => 'job-unknown',
            'status' => 'unknown_future_status',
            'webhook_status' => 'unknown_webhook_status',
        ];

        $dto = BatchJobDto::fromArray($data);

        $this->assertSame(JobStatus::Pending, $dto->status);
        $this->assertNull($dto->webhookStatus);
        $this->assertNull($dto->totalImages);
        $this->assertNull($dto->processedImages);
    }

    public function testToArraySerializesEnumsToStrings(): void
    {
        $result = new BatchJobItemResultDto(
            externalId: 'img-1',
            altText: 'Photo',
            caption: 'Landscape',
            tags: ['landscape'],
            confidenceScore: 0.99,
            error: null
        );

        $dto = new BatchJobDto(
            jobId: 'job-100',
            status: JobStatus::Processing,
            totalImages: 10,
            processedImages: 3,
            webhookStatus: WebhookStatus::Delivered,
            results: [$result],
            createdAt: '2026-09-01T08:00:00Z',
            completedAt: null,
            pollUrl: 'https://api.test/jobs/job-100'
        );

        $array = $dto->toArray();

        $this->assertSame('job-100', $array['job_id']);
        $this->assertSame('processing', $array['status']);
        $this->assertSame(10, $array['total_images']);
        $this->assertSame(3, $array['processed_images']);
        $this->assertSame('delivered', $array['webhook_status']);
        $this->assertSame('2026-09-01T08:00:00Z', $array['created_at']);
        $this->assertArrayNotHasKey('completed_at', $array);
        $this->assertSame('https://api.test/jobs/job-100', $array['poll_url']);
        $this->assertIsArray($array['results']);
        $this->assertSame('img-1', $array['results'][0]['external_id']);
        $this->assertSame('Photo', $array['results'][0]['alt_text']);
    }

    public function testToArrayOmitsNullOptionalFields(): void
    {
        $dto = new BatchJobDto(
            jobId: 'job-minimal',
            status: JobStatus::Pending
        );

        $array = $dto->toArray();

        $this->assertSame([
            'job_id' => 'job-minimal',
            'status' => 'pending',
        ], $array);
    }

    public function testStatusHelpers(): void
    {
        $pendingDto = new BatchJobDto('1', JobStatus::Pending);
        $this->assertTrue($pendingDto->isPending());
        $this->assertFalse($pendingDto->isProcessing());
        $this->assertFalse($pendingDto->isCompleted());
        $this->assertFalse($pendingDto->isFailed());

        $processingDto = new BatchJobDto('2', JobStatus::Processing);
        $this->assertFalse($processingDto->isPending());
        $this->assertTrue($processingDto->isProcessing());
        $this->assertFalse($processingDto->isCompleted());
        $this->assertFalse($processingDto->isFailed());

        $completedDto = new BatchJobDto('3', JobStatus::Completed);
        $this->assertFalse($completedDto->isPending());
        $this->assertFalse($completedDto->isProcessing());
        $this->assertTrue($completedDto->isCompleted());
        $this->assertFalse($completedDto->isFailed());

        $failedDto = new BatchJobDto('4', JobStatus::Failed);
        $this->assertFalse($failedDto->isPending());
        $this->assertFalse($failedDto->isProcessing());
        $this->assertTrue($failedDto->isCompleted());
        $this->assertTrue($failedDto->isFailed());
    }

    public function testGetProgressPercentage(): void
    {
        $dtoNoTotal = new BatchJobDto('1', JobStatus::Processing);
        $this->assertSame(0.0, $dtoNoTotal->getProgressPercentage());

        $dtoZeroTotal = new BatchJobDto('2', JobStatus::Processing, totalImages: 0);
        $this->assertSame(0.0, $dtoZeroTotal->getProgressPercentage());

        $dtoNegativeTotal = new BatchJobDto('2b', JobStatus::Processing, totalImages: -5);
        $this->assertSame(0.0, $dtoNegativeTotal->getProgressPercentage());

        $dtoHalf = new BatchJobDto('3', JobStatus::Processing, totalImages: 10, processedImages: 5);
        $this->assertSame(50.0, $dtoHalf->getProgressPercentage());

        $dtoThird = new BatchJobDto('4', JobStatus::Processing, totalImages: 3, processedImages: 1);
        $this->assertSame(33.33, $dtoThird->getProgressPercentage());

        $dtoOver = new BatchJobDto('5', JobStatus::Processing, totalImages: 10, processedImages: 12);
        $this->assertSame(100.0, $dtoOver->getProgressPercentage());

        $dtoNullProcessed = new BatchJobDto('6', JobStatus::Processing, totalImages: 10, processedImages: null);
        $this->assertSame(0.0, $dtoNullProcessed->getProgressPercentage());

        $dtoNegativeProcessed = new BatchJobDto('7', JobStatus::Processing, totalImages: 10, processedImages: -2);
        $this->assertSame(0.0, $dtoNegativeProcessed->getProgressPercentage());
    }

    public function testBatchJobItemResultDtoToArray(): void
    {
        $item = new BatchJobItemResultDto(
            externalId: 'ext-42',
            altText: 'Sunset over mountains',
            caption: 'Golden hour in the Alps',
            tags: ['sunset', 'mountain', 'nature'],
            confidenceScore: 0.97,
            error: null
        );

        $array = $item->toArray();

        $this->assertSame('ext-42', $array['external_id']);
        $this->assertSame('Sunset over mountains', $array['alt_text']);
        $this->assertSame('Golden hour in the Alps', $array['caption']);
        $this->assertSame(['sunset', 'mountain', 'nature'], $array['tags']);
        $this->assertSame(0.97, $array['confidence_score']);
        $this->assertArrayNotHasKey('error', $array);

        $errorItem = new BatchJobItemResultDto(
            externalId: 'ext-err',
            altText: null,
            caption: null,
            tags: [],
            confidenceScore: null,
            error: 'Download timed out'
        );

        $errArray = $errorItem->toArray();
        $this->assertSame('ext-err', $errArray['external_id']);
        $this->assertSame('Download timed out', $errArray['error']);
        $this->assertArrayNotHasKey('alt_text', $errArray);
        $this->assertArrayNotHasKey('caption', $errArray);
        $this->assertArrayNotHasKey('confidence_score', $errArray);
    }
}
