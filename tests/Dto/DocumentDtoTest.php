<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\DocumentCollectionDto;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentResponseDto;
use KinetiStack\Sdk\Dto\DocumentSummaryDto;
use PHPUnit\Framework\TestCase;

class DocumentDtoTest extends TestCase
{
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

    public function testDocumentResponseDtoFromArray(): void
    {
        $dto = DocumentResponseDto::fromArray([
            'document_id' => 'uuid-1234',
            'external_id' => 'node:1',
            'chunks_generated' => 5,
            'status' => 'indexed',
        ]);

        $this->assertSame('uuid-1234', $dto->documentId);
        $this->assertSame('node:1', $dto->externalId);
        $this->assertSame(5, $dto->chunksGenerated);
        $this->assertSame('indexed', $dto->status);
    }

    public function testDocumentSummaryDtoFromArray(): void
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

    public function testDocumentCollectionDtoFromHydraArray(): void
    {
        $data = [
            'hydra:member' => [
                ['external_id' => 'doc:1', 'title' => 'Doc 1'],
                ['external_id' => 'doc:2', 'title' => 'Doc 2'],
            ],
            'hydra:totalItems' => 10,
        ];

        $collection = DocumentCollectionDto::fromArray($data);
        $this->assertSame(10, $collection->total);
        $this->assertCount(2, $collection);
        $this->assertSame('doc:1', $collection->items[0]->externalId);
        $this->assertSame('doc:2', $collection->items[1]->externalId);
    }

    public function testDocumentCollectionDtoFromPlainList(): void
    {
        $data = [
            ['external_id' => 'doc:1', 'title' => 'Doc 1'],
            ['external_id' => 'doc:2', 'title' => 'Doc 2'],
            ['external_id' => 'doc:3', 'title' => 'Doc 3'],
        ];

        $collection = DocumentCollectionDto::fromArray($data);
        $this->assertSame(3, $collection->total);
        $this->assertCount(3, $collection);

        $titles = [];
        foreach ($collection as $item) {
            $titles[] = $item->title;
        }
        $this->assertSame(['Doc 1', 'Doc 2', 'Doc 3'], $titles);
    }
}
