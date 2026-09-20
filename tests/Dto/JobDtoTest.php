<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\BatchJobItemResultDto;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Enum\JobStatus;
use KinetiStack\Sdk\Enum\WebhookStatus;
use PHPUnit\Framework\TestCase;

class JobDtoTest extends TestCase
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

        $dto = new JobDto(
            jobId: 'job-123',
            status: JobStatus::Processing,
            type: 'vision_batch',
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
        $this->assertSame('vision_batch', $dto->type);
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
            'type' => 'vision_batch',
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

        $dto = JobDto::fromArray($data);

        $this->assertSame('job-456', $dto->jobId);
        $this->assertSame(JobStatus::Completed, $dto->status);
        $this->assertSame('vision_batch', $dto->type);
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

        $dto = JobDto::fromArray($data);

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

        $dto = JobDto::fromArray($data);

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

        $dto = new JobDto(
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
        $dto = new JobDto(
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
        $pendingDto = new JobDto('1', JobStatus::Pending);
        $this->assertTrue($pendingDto->isPending());
        $this->assertFalse($pendingDto->isProcessing());
        $this->assertFalse($pendingDto->isCompleted());
        $this->assertFalse($pendingDto->isFailed());

        $processingDto = new JobDto('2', JobStatus::Processing);
        $this->assertFalse($processingDto->isPending());
        $this->assertTrue($processingDto->isProcessing());
        $this->assertFalse($processingDto->isCompleted());
        $this->assertFalse($processingDto->isFailed());

        $completedDto = new JobDto('3', JobStatus::Completed);
        $this->assertFalse($completedDto->isPending());
        $this->assertFalse($completedDto->isProcessing());
        $this->assertTrue($completedDto->isCompleted());
        $this->assertFalse($completedDto->isFailed());

        $failedDto = new JobDto('4', JobStatus::Failed);
        $this->assertFalse($failedDto->isPending());
        $this->assertFalse($failedDto->isProcessing());
        $this->assertTrue($failedDto->isCompleted());
        $this->assertTrue($failedDto->isFailed());
    }

    public function testGetProgressPercentage(): void
    {
        $dtoNoTotal = new JobDto('1', JobStatus::Processing);
        $this->assertSame(0.0, $dtoNoTotal->getProgressPercentage());

        $dtoZeroTotal = new JobDto('2', JobStatus::Processing, totalImages: 0);
        $this->assertSame(0.0, $dtoZeroTotal->getProgressPercentage());

        $dtoNegativeTotal = new JobDto('2b', JobStatus::Processing, totalImages: -5);
        $this->assertSame(0.0, $dtoNegativeTotal->getProgressPercentage());

        $dtoHalf = new JobDto('3', JobStatus::Processing, totalImages: 10, processedImages: 5);
        $this->assertSame(50.0, $dtoHalf->getProgressPercentage());

        $dtoThird = new JobDto('4', JobStatus::Processing, totalImages: 3, processedImages: 1);
        $this->assertSame(33.33, $dtoThird->getProgressPercentage());

        $dtoOver = new JobDto('5', JobStatus::Processing, totalImages: 10, processedImages: 12);
        $this->assertSame(100.0, $dtoOver->getProgressPercentage());

        $dtoNullProcessed = new JobDto('6', JobStatus::Processing, totalImages: 10, processedImages: null);
        $this->assertSame(0.0, $dtoNullProcessed->getProgressPercentage());

        $dtoNegativeProcessed = new JobDto('7', JobStatus::Processing, totalImages: 10, processedImages: -2);
        $this->assertSame(0.0, $dtoNegativeProcessed->getProgressPercentage());
    }

    public function testFromArrayWithVisionSingleJobPayload(): void
    {
        $data = [
            'job_id' => '00000000-0000-0000-0000-000000000001',
            'type' => 'vision_single',
            'status' => 'completed',
            'results' => [
                'alt_text' => 'A cute sleeping kitten',
                'caption' => 'A small orange kitten sleeping peacefully on a fluffy white blanket',
                'confidence_score' => 0.96,
                'model_used' => 'qwen2-vl:7b',
                'tags' => ['kitten', 'cat', 'sleeping', 'orange'],
            ],
            'created_at' => '2026-09-19T10:00:00+00:00',
            'completed_at' => '2026-09-19T10:00:03+00:00',
            'poll_url' => '/api/v1/jobs/00000000-0000-0000-0000-000000000001',
        ];

        $dto = JobDto::fromArray($data);

        $this->assertSame('00000000-0000-0000-0000-000000000001', $dto->jobId);
        $this->assertSame('vision_single', $dto->type);
        $this->assertSame(JobStatus::Completed, $dto->status);
        $this->assertTrue($dto->isCompleted());
        $this->assertIsArray($dto->results);
        $this->assertSame('A cute sleeping kitten', $dto->results['alt_text']);
        $this->assertSame('qwen2-vl:7b', $dto->results['model_used']);
        $this->assertSame(['kitten', 'cat', 'sleeping', 'orange'], $dto->results['tags']);
        $this->assertSame(0.96, $dto->results['confidence_score']);

        $array = $dto->toArray();
        $this->assertSame('vision_single', $array['type']);
        $this->assertSame('A cute sleeping kitten', $array['results']['alt_text']);
    }

    public function testFromArrayWithDocumentIngestJobPayload(): void
    {
        $data = [
            'job_id' => '00000000-0000-0000-0000-000000000002',
            'type' => 'document_ingest',
            'status' => 'completed',
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'external_id' => 'node:42:en',
            'chunks_generated' => 4,
            'results' => [
                'document_id' => '550e8400-e29b-41d4-a716-446655440000',
                'external_id' => 'node:42:en',
                'chunks_generated' => 4,
                'status' => 'indexed',
            ],
            'created_at' => '2026-09-19T10:00:00+00:00',
            'completed_at' => '2026-09-19T10:00:02+00:00',
            'poll_url' => '/api/v1/jobs/00000000-0000-0000-0000-000000000002',
        ];

        $dto = JobDto::fromArray($data);

        $this->assertSame('00000000-0000-0000-0000-000000000002', $dto->jobId);
        $this->assertSame('document_ingest', $dto->type);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $dto->documentId);
        $this->assertSame('node:42:en', $dto->externalId);
        $this->assertSame(4, $dto->chunksGenerated);
        $this->assertTrue($dto->isCompleted());
        $this->assertIsArray($dto->results);
        $this->assertArrayHasKey('chunks_generated', $dto->results);
        $this->assertSame(4, $dto->results['chunks_generated']);
        $this->assertArrayHasKey('status', $dto->results);
        $this->assertSame('indexed', $dto->results['status']);

        $array = $dto->toArray();
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $array['document_id']);
        $this->assertSame('node:42:en', $array['external_id']);
        $this->assertSame(4, $array['chunks_generated']);
        $this->assertSame('indexed', $array['results']['status']);
    }

    public function testFromArrayWithDocumentIngestPendingResponse(): void
    {
        $data = [
            'job_id' => 'job-doc-pending',
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'external_id' => 'node:42:en',
            'chunks_generated' => 0,
            'status' => 'pending',
            'poll_url' => '/api/v1/jobs/job-doc-pending',
        ];

        $dto = JobDto::fromArray($data);

        $this->assertSame('job-doc-pending', $dto->jobId);
        $this->assertSame(JobStatus::Pending, $dto->status);
        $this->assertTrue($dto->isPending());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $dto->documentId);
        $this->assertSame('node:42:en', $dto->externalId);
        $this->assertSame(0, $dto->chunksGenerated);
        $this->assertNull($dto->results);
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
