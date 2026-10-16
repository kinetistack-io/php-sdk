<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\BatchJobItemResultDto;
use KinetiStack\Sdk\Dto\DocumentResponseDto;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Dto\VisionResponseDto;
use KinetiStack\Sdk\Enum\JobStatus;
use KinetiStack\Sdk\Enum\JobType;
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
        $itemResults = $dto->getItemResults();
        $this->assertNotNull($itemResults);
        $this->assertSame('img-1', $itemResults[0]->externalId);
        $this->assertSame('Tree', $itemResults[0]->altText);
        $this->assertSame('Green tree', $itemResults[0]->caption);
        $this->assertSame(['tree', 'green'], $itemResults[0]->tags);
        $this->assertSame(0.98, $itemResults[0]->confidenceScore);
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
        $this->assertIsArray($array['results']);
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
        $this->assertIsArray($array['results']);
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

    public function testJobDtoWithTokensConsumed(): void
    {
        $dto = new JobDto(
            jobId: 'job-1',
            status: JobStatus::Completed,
            tokensConsumed: 450,
        );

        $this->assertSame(450, $dto->tokensConsumed);
        $this->assertSame(450, $dto->toArray()['tokens_consumed']);

        // From top-level snake_case tokens_consumed
        $fromSnake = JobDto::fromArray([
            'job_id' => 'job-1',
            'status' => 'completed',
            'tokens_consumed' => 300,
        ]);
        $this->assertSame(300, $fromSnake->tokensConsumed);

        // From top-level camelCase tokensConsumed
        $fromCamel = JobDto::fromArray([
            'job_id' => 'job-1',
            'status' => 'completed',
            'tokensConsumed' => 350,
        ]);
        $this->assertSame(350, $fromCamel->tokensConsumed);

        // From top-level usage array
        $fromUsage = JobDto::fromArray([
            'job_id' => 'job-1',
            'status' => 'completed',
            'usage' => ['total_tokens' => 400],
        ]);
        $this->assertSame(400, $fromUsage->tokensConsumed);

        // From nested results tokens_consumed (e.g. document ingest job result)
        $fromNestedResults = JobDto::fromArray([
            'job_id' => 'job-1',
            'status' => 'completed',
            'results' => [
                'document_id' => 'doc-1',
                'external_id' => 'ext-1',
                'chunks_generated' => 5,
                'tokens_consumed' => 520,
            ],
        ]);
        $this->assertSame(520, $fromNestedResults->tokensConsumed);

        // From nested results usage object
        $fromNestedUsage = JobDto::fromArray([
            'job_id' => 'job-1',
            'status' => 'completed',
            'results' => [
                'document_id' => 'doc-1',
                'external_id' => 'ext-1',
                'usage' => ['tokens_consumed' => 600],
            ],
        ]);
        $this->assertSame(600, $fromNestedUsage->tokensConsumed);

        // Without tokens consumed
        $fromNull = JobDto::fromArray([
            'job_id' => 'job-1',
            'status' => 'completed',
        ]);
        $this->assertNull($fromNull->tokensConsumed);
        $this->assertArrayNotHasKey('tokens_consumed', $fromNull->toArray());
    }

    public function testToVisionResponseReturnsHydratedDto(): void
    {
        $dto = new JobDto(
            jobId: 'vision-job-1',
            status: JobStatus::Completed,
            result: [
                'alt_text' => 'Solar panels',
                'caption' => 'Rooftop solar panel installation',
                'tags' => ['solar', 'energy', 'clean'],
                'confidence_score' => 0.98,
                'model_used' => 'qwen2-vl',
                'tokens_consumed' => 42,
            ],
        );

        $vision = $dto->toVisionResponse();

        $this->assertInstanceOf(VisionResponseDto::class, $vision);
        $this->assertSame('Solar panels', $vision->altText);
        $this->assertSame('Rooftop solar panel installation', $vision->caption);
        $this->assertSame(['solar', 'energy', 'clean'], $vision->tags);
        $this->assertSame(0.98, $vision->confidenceScore);
        $this->assertSame('qwen2-vl', $vision->modelUsed);
        $this->assertSame(42, $vision->tokensConsumed);

        $this->assertSame($dto->results, $dto->result);
    }

    public function testToVisionResponseAndToDocumentResponseReturnNullWhenResultIsNull(): void
    {
        $pendingDto = new JobDto(
            jobId: 'pending-job-1',
            status: JobStatus::Pending,
            result: null,
        );

        $this->assertNull($pendingDto->toVisionResponse());
        $this->assertNull($pendingDto->toDocumentResponse());

        $completedWithoutResult = new JobDto(
            jobId: 'empty-job-1',
            status: JobStatus::Completed,
            results: null,
        );

        $this->assertNull($completedWithoutResult->toVisionResponse());
        $this->assertNull($completedWithoutResult->toDocumentResponse());
    }

    public function testToDocumentResponseReturnsHydratedDto(): void
    {
        $dto = new JobDto(
            jobId: 'doc-job-1',
            status: JobStatus::Completed,
            result: [
                'document_id' => '550e8400-e29b-41d4-a716-446655440000',
                'external_id' => 'node:42:en',
                'chunks_generated' => 4,
                'status' => 'indexed',
                'tokens_consumed' => 120,
            ],
        );

        $document = $dto->toDocumentResponse();

        $this->assertInstanceOf(DocumentResponseDto::class, $document);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $document->documentId);
        $this->assertSame('node:42:en', $document->externalId);
        $this->assertSame(4, $document->chunksGenerated);
        $this->assertSame('indexed', $document->status);
        $this->assertSame(120, $document->tokensConsumed);
    }

    public function testConstructorAcceptsResultAndResultsAliases(): void
    {
        $payload = ['alt_text' => 'Solar panels', 'caption' => 'Panels', 'tags' => ['solar']];

        $dtoWithResult = new JobDto(
            jobId: 'alias-1',
            status: JobStatus::Completed,
            result: $payload,
        );
        $this->assertSame($payload, $dtoWithResult->result);
        $this->assertSame($payload, $dtoWithResult->results);

        $dtoWithResults = new JobDto(
            jobId: 'alias-2',
            status: JobStatus::Completed,
            results: $payload,
        );
        $this->assertSame($payload, $dtoWithResults->result);
        $this->assertSame($payload, $dtoWithResults->results);
    }

    public function testFromArrayAcceptsResultOrResultsKey(): void
    {
        $fromSingleResult = JobDto::fromArray([
            'job_id' => 'job-single-1',
            'status' => 'completed',
            'result' => [
                'alt_text' => 'Solar panels',
                'tags' => ['solar'],
            ],
        ]);
        $this->assertNotNull($fromSingleResult->result);
        $this->assertSame('Solar panels', $fromSingleResult->toVisionResponse()?->altText);

        $fromResults = JobDto::fromArray([
            'job_id' => 'job-single-2',
            'status' => 'completed',
            'results' => [
                'alt_text' => 'Wind turbines',
                'tags' => ['wind'],
            ],
        ]);
        $this->assertNotNull($fromResults->result);
        $this->assertSame('Wind turbines', $fromResults->toVisionResponse()?->altText);
    }

    public function testToVisionResponseAndToDocumentResponseReturnNullForBatchAndEmptyResults(): void
    {
        // Batch job payload (list of item results)
        $batchDto = new JobDto(
            jobId: 'batch-1',
            status: JobStatus::Completed,
            results: [
                ['external_id' => 'img-1', 'alt_text' => 'First image'],
                ['external_id' => 'img-2', 'alt_text' => 'Second image'],
            ],
        );

        $this->assertNull($batchDto->toVisionResponse());
        $this->assertNull($batchDto->toDocumentResponse());

        // Empty array result
        $emptyResultDto = new JobDto(
            jobId: 'empty-1',
            status: JobStatus::Completed,
            result: [],
        );

        $this->assertNull($emptyResultDto->toVisionResponse());
        $this->assertNull($emptyResultDto->toDocumentResponse());
    }

    public function testJobTypeEnumCasesAndHelpers(): void
    {
        $this->assertSame('vision_single', JobType::VisionSingle->value);
        $this->assertSame('vision_batch', JobType::VisionBatch->value);
        $this->assertSame('document_ingest', JobType::DocumentIngest->value);

        $this->assertTrue(JobType::VisionSingle->isVision());
        $this->assertTrue(JobType::VisionSingle->isSingleVision());
        $this->assertFalse(JobType::VisionSingle->isDocument());
        $this->assertFalse(JobType::VisionSingle->isBatch());

        $this->assertTrue(JobType::VisionBatch->isVision());
        $this->assertFalse(JobType::VisionBatch->isSingleVision());
        $this->assertFalse(JobType::VisionBatch->isDocument());
        $this->assertTrue(JobType::VisionBatch->isBatch());

        $this->assertFalse(JobType::DocumentIngest->isVision());
        $this->assertFalse(JobType::DocumentIngest->isSingleVision());
        $this->assertTrue(JobType::DocumentIngest->isDocument());
        $this->assertFalse(JobType::DocumentIngest->isBatch());
    }

    public function testJobDtoAcceptsJobTypeEnumAndResolvesString(): void
    {
        $dtoWithEnum = new JobDto(
            jobId: 'type-enum-1',
            status: JobStatus::Completed,
            type: JobType::VisionSingle,
        );

        $this->assertSame('vision_single', $dtoWithEnum->type);
        $this->assertSame(JobType::VisionSingle, $dtoWithEnum->getJobType());

        $dtoWithString = new JobDto(
            jobId: 'type-str-1',
            status: JobStatus::Completed,
            type: 'document_ingest',
        );

        $this->assertSame('document_ingest', $dtoWithString->type);
        $this->assertSame(JobType::DocumentIngest, $dtoWithString->getJobType());

        $dtoWithUnknown = new JobDto(
            jobId: 'type-unknown-1',
            status: JobStatus::Completed,
            type: 'custom_future_type',
        );

        $this->assertSame('custom_future_type', $dtoWithUnknown->type);
        $this->assertNull($dtoWithUnknown->getJobType());

        $dtoWithNull = new JobDto(
            jobId: 'type-null-1',
            status: JobStatus::Completed,
        );

        $this->assertNull($dtoWithNull->type);
        $this->assertNull($dtoWithNull->getJobType());
    }

    public function testJobDtoFromArrayHandlesJobTypeAndString(): void
    {
        $fromEnum = JobDto::fromArray([
            'job_id' => 'enum-array-1',
            'type' => JobType::DocumentIngest,
        ]);
        $this->assertSame('document_ingest', $fromEnum->type);
        $this->assertSame(JobType::DocumentIngest, $fromEnum->getJobType());

        $fromString = JobDto::fromArray([
            'job_id' => 'str-array-1',
            'type' => 'vision_single',
        ]);
        $this->assertSame('vision_single', $fromString->type);
        $this->assertSame(JobType::VisionSingle, $fromString->getJobType());
    }

    public function testToVisionResponseGuardsAgainstTypeMismatchAndBatchType(): void
    {
        $visionPayload = [
            'alt_text' => 'Alpine mountains',
            'tags' => ['nature', 'mountain'],
        ];

        // Valid vision job matches and hydrates
        $validDto = new JobDto(
            jobId: 'vision-valid-1',
            status: JobStatus::Completed,
            type: JobType::VisionSingle,
            result: $visionPayload,
        );
        $this->assertInstanceOf(VisionResponseDto::class, $validDto->toVisionResponse());

        // Document job with vision payload must NOT hydrate to vision response
        $docJobWithVisionPayload = new JobDto(
            jobId: 'cross-doc-1',
            status: JobStatus::Completed,
            type: 'document_ingest',
            result: $visionPayload,
        );
        $this->assertNull($docJobWithVisionPayload->toVisionResponse());

        // Document job with JobType enum must NOT hydrate to vision response
        $docJobWithEnum = new JobDto(
            jobId: 'cross-doc-2',
            status: JobStatus::Completed,
            type: JobType::DocumentIngest,
            result: $visionPayload,
        );
        $this->assertNull($docJobWithEnum->toVisionResponse());

        // Batch job type must NOT hydrate to single vision response
        $batchJobWithType = new JobDto(
            jobId: 'batch-vision-1',
            status: JobStatus::Completed,
            type: JobType::VisionBatch,
            result: $visionPayload,
        );
        $this->assertNull($batchJobWithType->toVisionResponse());

        // Generic 'document' type must NOT hydrate to vision response
        $genericDocJob = new JobDto(
            jobId: 'generic-doc-1',
            status: JobStatus::Completed,
            type: 'document',
            result: $visionPayload,
        );
        $this->assertNull($genericDocJob->toVisionResponse());
    }

    public function testToDocumentResponseGuardsAgainstTypeMismatchAndBatchType(): void
    {
        $docPayload = [
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'external_id' => 'article:100',
            'chunks_generated' => 3,
        ];

        // Valid document job matches and hydrates
        $validDto = new JobDto(
            jobId: 'doc-valid-1',
            status: JobStatus::Completed,
            type: JobType::DocumentIngest,
            result: $docPayload,
        );
        $this->assertInstanceOf(DocumentResponseDto::class, $validDto->toDocumentResponse());

        // Vision job with document payload must NOT hydrate to document response
        $visionJobWithDocPayload = new JobDto(
            jobId: 'cross-vision-1',
            status: JobStatus::Completed,
            type: 'vision_single',
            result: $docPayload,
        );
        $this->assertNull($visionJobWithDocPayload->toDocumentResponse());

        // Vision job with JobType enum must NOT hydrate to document response
        $visionJobWithEnum = new JobDto(
            jobId: 'cross-vision-2',
            status: JobStatus::Completed,
            type: JobType::VisionSingle,
            result: $docPayload,
        );
        $this->assertNull($visionJobWithEnum->toDocumentResponse());

        // Batch job type must NOT hydrate to document response
        $batchJobWithType = new JobDto(
            jobId: 'batch-vision-2',
            status: JobStatus::Completed,
            type: JobType::VisionBatch,
            result: $docPayload,
        );
        $this->assertNull($batchJobWithType->toDocumentResponse());

        // Generic 'vision' type must NOT hydrate to document response
        $genericVisionJob = new JobDto(
            jobId: 'generic-vision-1',
            status: JobStatus::Completed,
            type: 'vision',
            result: $docPayload,
        );
        $this->assertNull($genericVisionJob->toDocumentResponse());
    }

    public function testToVisionResponseAndToDocumentResponseGuardAgainstUntypedPayloadCrossHydration(): void
    {
        // Untyped job with document payload should NOT cross-hydrate to VisionResponseDto
        $docPayloadJob = new JobDto(
            jobId: 'untyped-doc-1',
            status: JobStatus::Completed,
            type: null,
            result: [
                'document_id' => 'doc-uuid-1234',
                'external_id' => 'doc-ext-1',
                'chunks_generated' => 5,
            ],
        );

        $this->assertNull($docPayloadJob->toVisionResponse());
        $this->assertInstanceOf(DocumentResponseDto::class, $docPayloadJob->toDocumentResponse());

        // Untyped job with vision payload should NOT cross-hydrate to DocumentResponseDto
        $visionPayloadJob = new JobDto(
            jobId: 'untyped-vision-1',
            status: JobStatus::Completed,
            type: null,
            result: [
                'alt_text' => 'A photo of the ocean',
                'caption' => 'Pacific waves crashing against cliffs',
                'tags' => ['ocean', 'water'],
            ],
        );

        $this->assertNull($visionPayloadJob->toDocumentResponse());
        $this->assertInstanceOf(VisionResponseDto::class, $visionPayloadJob->toVisionResponse());
    }
}
