<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\ContextItemDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use PHPUnit\Framework\TestCase;

class ContextItemDtoTest extends TestCase
{
    public function testConstructWithRequiredFieldsOnly(): void
    {
        $dto = new ContextItemDto(externalId: 'node/42');

        $this->assertSame('node/42', $dto->externalId);
        $this->assertSame('', $dto->title);
        $this->assertSame('', $dto->content);
        $this->assertSame(0, $dto->chunkIndex);
        $this->assertSame(0.0, $dto->score);
        $this->assertNull($dto->metadata);
    }

    public function testConstructWithAllFields(): void
    {
        $metadata = ['category' => 'tech', 'source' => 'solr'];
        $dto = new ContextItemDto(
            externalId: 'solr:doc:123',
            title: 'Solr Integration Article',
            content: 'Full body content of article.',
            chunkIndex: 2,
            score: 0.94,
            metadata: $metadata,
        );

        $this->assertSame('solr:doc:123', $dto->externalId);
        $this->assertSame('Solr Integration Article', $dto->title);
        $this->assertSame('Full body content of article.', $dto->content);
        $this->assertSame(2, $dto->chunkIndex);
        $this->assertSame(0.94, $dto->score);
        $this->assertSame($metadata, $dto->metadata);
    }

    public function testEmptyExternalIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Context item externalId cannot be empty.');

        new ContextItemDto(externalId: '   ');
    }

    public function testToArrayOmitsMetadataWhenNull(): void
    {
        $dto = new ContextItemDto(
            externalId: 'node/1',
            title: 'Test Title',
            content: 'Body text',
            chunkIndex: 1,
            score: 0.85,
        );

        $array = $dto->toArray();

        $this->assertSame([
            'external_id' => 'node/1',
            'title' => 'Test Title',
            'content' => 'Body text',
            'chunk_index' => 1,
            'score' => 0.85,
        ], $array);
        $this->assertArrayNotHasKey('metadata', $array);
    }

    public function testToArrayIncludesMetadataWhenPresent(): void
    {
        $dto = new ContextItemDto(
            externalId: 'node/2',
            title: 'Meta Title',
            content: 'Meta Body',
            chunkIndex: 0,
            score: 0.99,
            metadata: ['tags' => ['rag', 'solr']],
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('metadata', $array);
        $this->assertSame(['tags' => ['rag', 'solr']], $array['metadata']);
    }

    public function testFromArrayWithSnakeCase(): void
    {
        $data = [
            'external_id' => 'doc/100',
            'title' => 'Snake Title',
            'content' => 'Snake Content',
            'chunk_index' => 4,
            'score' => 0.91,
            'metadata' => ['env' => 'production'],
        ];

        $dto = ContextItemDto::fromArray($data);

        $this->assertSame('doc/100', $dto->externalId);
        $this->assertSame('Snake Title', $dto->title);
        $this->assertSame('Snake Content', $dto->content);
        $this->assertSame(4, $dto->chunkIndex);
        $this->assertSame(0.91, $dto->score);
        $this->assertSame(['env' => 'production'], $dto->metadata);
    }

    public function testFromArrayWithCamelCaseAndSnippetFallback(): void
    {
        $data = [
            'externalId' => 'doc/200',
            'title' => 'Camel Title',
            'snippet' => 'Snippet as content',
            'chunkIndex' => 5,
            'score' => 0.77,
        ];

        $dto = ContextItemDto::fromArray($data);

        $this->assertSame('doc/200', $dto->externalId);
        $this->assertSame('Camel Title', $dto->title);
        $this->assertSame('Snippet as content', $dto->content);
        $this->assertSame(5, $dto->chunkIndex);
        $this->assertSame(0.77, $dto->score);
        $this->assertNull($dto->metadata);
    }

    public function testFromSearchResultItem(): void
    {
        $searchResult = new SearchResultItemDto(
            externalId: 'search:item:1',
            title: 'Pinned Result',
            content: 'Pinned content snippet',
            chunkIndex: 3,
            score: 0.98,
            metadata: ['pinned' => true],
        );

        $dto = ContextItemDto::fromSearchResultItem($searchResult);

        $this->assertSame('search:item:1', $dto->externalId);
        $this->assertSame('Pinned Result', $dto->title);
        $this->assertSame('Pinned content snippet', $dto->content);
        $this->assertSame(3, $dto->chunkIndex);
        $this->assertSame(0.98, $dto->score);
        $this->assertSame(['pinned' => true], $dto->metadata);
    }
}
