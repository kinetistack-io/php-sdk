<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\DocumentCollectionDto;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentResponseDto;
use KinetiStack\Sdk\Dto\DocumentSummaryDto;
use KinetiStack\Sdk\Tests\FixtureTrait;
use PHPUnit\Framework\TestCase;

class DocumentDtoTest extends TestCase
{
    use FixtureTrait;

    public function testDocumentDtoConstructorValid(): void
    {
        $dto = new DocumentDto(
            externalId: 'node:123:en',
            title: 'Sample Title',
            content: 'Sample Content',
            locale: 'da',
            permissions: ['role_admin'],
            metadata: ['category' => 'tech']
        );

        $this->assertSame('node:123:en', $dto->externalId);
        $this->assertSame('Sample Title', $dto->title);
        $this->assertSame('Sample Content', $dto->content);
        $this->assertSame('da', $dto->locale);
        $this->assertSame(['role_admin'], $dto->permissions);
        $this->assertSame(['category' => 'tech'], $dto->metadata);

        $array = $dto->toArray();
        $this->assertSame('node:123:en', $array['external_id']);
        $this->assertSame('Sample Title', $array['title']);
        $this->assertSame('Sample Content', $array['content']);
        $this->assertSame('da', $array['locale']);
        $this->assertSame(['role_admin'], $array['permissions']);
        $this->assertSame(['category' => 'tech'], $array['metadata']);
    }

    public function testDocumentDtoToArrayOmitsNullOptionalFields(): void
    {
        $dto = new DocumentDto('node:123', 'Title', 'Content');
        $array = $dto->toArray();

        $this->assertArrayNotHasKey('permissions', $array);
        $this->assertArrayNotHasKey('metadata', $array);
        $this->assertSame('en', $array['locale']);
    }

    public function testDocumentDtoFromArray(): void
    {
        $data = [
            'external_id' => 'media:10',
            'title' => 'PDF Report',
            'content' => 'Text inside PDF',
            'locale' => 'en',
            'permissions' => ['staff'],
            'metadata' => ['filesize' => 1024],
        ];

        $dto = DocumentDto::fromArray($data);
        $this->assertSame('media:10', $dto->externalId);
        $this->assertSame('PDF Report', $dto->title);
        $this->assertSame('Text inside PDF', $dto->content);
        $this->assertSame(['staff'], $dto->permissions);
        $this->assertSame(['filesize' => 1024], $dto->metadata);
    }

    public function testDocumentDtoEmptyExternalIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('externalId cannot be empty.');
        new DocumentDto('   ', 'Title', 'Content');
    }

    public function testDocumentDtoEmptyTitleThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('title cannot be empty.');
        new DocumentDto('node:1', ' ', 'Content');
    }

    public function testDocumentDtoEmptyContentThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('content cannot be empty.');
        new DocumentDto('node:1', 'Title', '');
    }

    public function testDocumentDtoAcceptsFlatScalarArrayMetadata(): void
    {
        $metadata = [
            'tags' => ['php', 'symfony'],
            'content-type' => 'article',
            'views' => 1500,
            'rating' => 4.8,
            'is_published' => true,
            'empty_list' => [],
            'nullable_field' => null,
        ];

        $dto = new DocumentDto('node:100', 'Title', 'Content', metadata: $metadata);

        $this->assertSame($metadata, $dto->metadata);
        $array = $dto->toArray();
        $this->assertSame($metadata, $array['metadata']);
    }

    public function testDocumentDtoRejectsInvalidMetadataKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid metadata key "invalid.key!". Keys must be 1-64 characters matching /\A[a-zA-Z0-9_-]{1,64}\z/.');

        new DocumentDto('node:1', 'Title', 'Content', metadata: ['invalid.key!' => 'value']);
    }

    public function testDocumentDtoRejectsEmptyMetadataKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid metadata key "". Keys must be 1-64 characters matching /\A[a-zA-Z0-9_-]{1,64}\z/.');

        new DocumentDto('node:1', 'Title', 'Content', metadata: ['' => 'value']);
    }

    public function testDocumentDtoRejectsTooLongMetadataKey(): void
    {
        $longKey = str_repeat('a', 65);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Invalid metadata key "%s". Keys must be 1-64 characters matching /\A[a-zA-Z0-9_-]{1,64}\z/.', $longKey));

        new DocumentDto('node:1', 'Title', 'Content', metadata: [$longKey => 'value']);
    }

    public function testDocumentDtoRejectsAssociativeArrayMetadataValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata value for key "nested" cannot be an associative array.');

        new DocumentDto('node:1', 'Title', 'Content', metadata: ['nested' => ['foo' => 'bar']]);
    }

    public function testDocumentDtoRejectsNestedArrayInScalarList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata array element at index 1 for key "tags" must be a scalar value.');

        new DocumentDto('node:1', 'Title', 'Content', metadata: ['tags' => ['php', ['nested']]]);
    }

    public function testDocumentDtoRejectsNullElementInMetadataList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata array element at index 1 for key "tags" must be a scalar value.');

        new DocumentDto('node:1', 'Title', 'Content', metadata: ['tags' => ['php', null]]);
    }

    public function testDocumentDtoRejectsObjectInMetadata(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata value for key "obj" must be a scalar or flat array of scalars.');

        new DocumentDto('node:1', 'Title', 'Content', metadata: ['obj' => new \stdClass()]);
    }

    public function testDocumentDtoFromArrayWithValidAndInvalidMetadata(): void
    {
        $validData = [
            'external_id' => 'doc:1',
            'title' => 'Doc Title',
            'content' => 'Doc Content',
            'metadata' => [
                'tags' => ['unit-test', 'coverage'],
                'priority' => 1,
            ],
        ];

        $dto = DocumentDto::fromArray($validData);
        $this->assertSame(['tags' => ['unit-test', 'coverage'], 'priority' => 1], $dto->metadata);

        $invalidData = [
            'external_id' => 'doc:2',
            'title' => 'Doc Title',
            'content' => 'Doc Content',
            'metadata' => [
                'bad.key' => 'error',
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid metadata key "bad.key".');
        DocumentDto::fromArray($invalidData);
    }

    public function testDocumentResponseDtoFromArrayAndFixture(): void
    {
        $data = $this->loadFixtureArray('Documents/document_created_201.json');
        /** @var array<string, mixed> $data */
        $dto = DocumentResponseDto::fromArray($data);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $dto->documentId);
        $this->assertSame('node:42:en', $dto->externalId);
        $this->assertSame(4, $dto->chunksGenerated);
        $this->assertSame('indexed', $dto->status);

        $array = $dto->toArray();
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $array['document_id']);
        $this->assertSame('node:42:en', $array['external_id']);
        $this->assertSame(4, $array['chunks_generated']);
        $this->assertSame('indexed', $array['status']);
        $this->assertNull($dto->tokensConsumed);
        $this->assertArrayNotHasKey('tokens_consumed', $array);
    }

    public function testDocumentResponseDtoWithTokensConsumed(): void
    {
        $dto = new DocumentResponseDto(
            documentId: 'doc-1',
            externalId: 'ext-1',
            chunksGenerated: 5,
            status: 'indexed',
            tokensConsumed: 120,
        );

        $this->assertSame(120, $dto->tokensConsumed);
        $this->assertSame(120, $dto->toArray()['tokens_consumed']);

        // From snake_case tokens_consumed
        $fromSnake = DocumentResponseDto::fromArray([
            'document_id' => 'doc-1',
            'external_id' => 'ext-1',
            'chunks_generated' => 5,
            'tokens_consumed' => 85,
        ]);
        $this->assertSame(85, $fromSnake->tokensConsumed);

        // From camelCase tokensConsumed
        $fromCamel = DocumentResponseDto::fromArray([
            'document_id' => 'doc-1',
            'external_id' => 'ext-1',
            'chunks_generated' => 5,
            'tokensConsumed' => 95,
        ]);
        $this->assertSame(95, $fromCamel->tokensConsumed);

        // From usage array with total_tokens
        $fromUsageTotal = DocumentResponseDto::fromArray([
            'document_id' => 'doc-1',
            'external_id' => 'ext-1',
            'chunks_generated' => 5,
            'usage' => ['total_tokens' => 150],
        ]);
        $this->assertSame(150, $fromUsageTotal->tokensConsumed);

        // From usage array with tokens_consumed
        $fromUsageConsumed = DocumentResponseDto::fromArray([
            'document_id' => 'doc-1',
            'external_id' => 'ext-1',
            'chunks_generated' => 5,
            'usage' => ['tokens_consumed' => 175],
        ]);
        $this->assertSame(175, $fromUsageConsumed->tokensConsumed);

        // From usage numeric scalar
        $fromUsageScalar = DocumentResponseDto::fromArray([
            'document_id' => 'doc-1',
            'external_id' => 'ext-1',
            'chunks_generated' => 5,
            'usage' => 200,
        ]);
        $this->assertSame(200, $fromUsageScalar->tokensConsumed);

        // Without tokens consumed
        $fromNull = DocumentResponseDto::fromArray([
            'document_id' => 'doc-1',
            'external_id' => 'ext-1',
            'chunks_generated' => 5,
        ]);
        $this->assertNull($fromNull->tokensConsumed);
        $this->assertArrayNotHasKey('tokens_consumed', $fromNull->toArray());
    }

    public function testDocumentSummaryDtoFromArrayAndToArray(): void
    {
        $dto = DocumentSummaryDto::fromArray([
            'id' => 'uuid-5678',
            'external_id' => 'node:2:en',
            'title' => 'Article Title',
            'locale' => 'en',
            'permissions' => ['public'],
            'metadata' => ['key' => 'val'],
            'chunk_count' => 3,
            'chunks_generated' => 3,
            'status' => 'indexed',
            'created_at' => '2026-01-01T12:00:00Z',
            'updated_at' => '2026-01-02T12:00:00Z',
        ]);

        $this->assertSame('uuid-5678', $dto->id);
        $this->assertSame('node:2:en', $dto->externalId);
        $this->assertSame('Article Title', $dto->title);
        $this->assertSame('en', $dto->locale);
        $this->assertSame(['public'], $dto->permissions);
        $this->assertSame(['key' => 'val'], $dto->metadata);
        $this->assertSame(3, $dto->chunkCount);
        $this->assertSame(3, $dto->chunksGenerated);
        $this->assertSame('indexed', $dto->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->createdAt);
        $this->assertSame('2026-01-01T12:00:00+00:00', $dto->createdAt->format(\DateTimeInterface::ATOM));
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->updatedAt);
        $this->assertSame('2026-01-02T12:00:00+00:00', $dto->updatedAt->format(\DateTimeInterface::ATOM));

        $array = $dto->toArray();
        $this->assertSame('uuid-5678', $array['id']);
        $this->assertSame('node:2:en', $array['external_id']);
        $this->assertSame('Article Title', $array['title']);
        $this->assertSame('2026-01-01T12:00:00+00:00', $array['created_at']);
    }

    public function testDocumentSummaryDtoFromArrayHandlesNullAndInvalidDates(): void
    {
        $dto = DocumentSummaryDto::fromArray([
            'external_id' => 'doc:1',
            'title' => 'No dates',
            'created_at' => 'invalid-date-string',
            'updated_at' => null,
        ]);

        $this->assertNull($dto->createdAt);
        $this->assertNull($dto->updatedAt);
    }

    public function testDocumentCollectionDtoFromArrayEmpty(): void
    {
        $collection = DocumentCollectionDto::fromArray([]);
        $this->assertSame(0, $collection->total);
        $this->assertCount(0, $collection);
        $this->assertSame([], $collection->items);
    }

    public function testDocumentCollectionDtoFromHydraFixture(): void
    {
        $data = $this->loadFixtureArray('Documents/document_list_hydra_200.json');
        /** @var array<string, mixed> $data */
        $collection = DocumentCollectionDto::fromArray($data);

        $this->assertSame(2, $collection->total);
        $this->assertCount(2, $collection);
        $this->assertSame('node:1:en', $collection->items[0]->externalId);
        $this->assertSame('node:2:en', $collection->items[1]->externalId);
    }

    public function testDocumentCollectionDtoFromPlainListFixture(): void
    {
        $data = $this->loadFixtureArray('Documents/document_list_plain_200.json');
        /** @var list<array<string, mixed>> $data */
        $collection = DocumentCollectionDto::fromArray($data);

        $this->assertSame(2, $collection->total);
        $this->assertCount(2, $collection);

        $titles = [];
        foreach ($collection as $item) {
            $titles[] = $item->title;
        }
        $this->assertSame(['Article 1', 'Article 2'], $titles);

        $array = $collection->toArray();
        $this->assertSame(2, $array['total']);
        $this->assertIsArray($array['items']);
        $this->assertCount(2, $array['items']);
    }
}
