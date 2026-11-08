<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\ContextItemDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use PHPUnit\Framework\TestCase;

class SearchQueryDtoTest extends TestCase
{
    public function testFromArrayParsesBooleanStringsCorrectly(): void
    {
        $dtoFalse = SearchQueryDto::fromArray([
            'query' => 'test search',
            'synthesize' => 'false',
            'stream' => '0',
            'rerank' => 'false',
            'distinct_documents' => '0',
        ]);

        $this->assertSame('test search', $dtoFalse->query);
        $this->assertFalse($dtoFalse->synthesizeAnswer);
        $this->assertFalse($dtoFalse->stream);
        $this->assertFalse($dtoFalse->rerank);
        $this->assertFalse($dtoFalse->distinctDocuments);

        $dtoTrue = SearchQueryDto::fromArray([
            'query' => 'test search 2',
            'synthesize_answer' => 'true',
            'stream' => '1',
            'rerank' => '1',
            'distinctDocuments' => 'true',
        ]);

        $this->assertSame('test search 2', $dtoTrue->query);
        $this->assertTrue($dtoTrue->synthesizeAnswer);
        $this->assertTrue($dtoTrue->stream);
        $this->assertTrue($dtoTrue->rerank);
        $this->assertTrue($dtoTrue->distinctDocuments);
    }

    public function testFromArrayWithNativeBooleans(): void
    {
        $dto = SearchQueryDto::fromArray([
            'query' => 'test',
            'synthesize' => false,
            'stream' => false,
            'rerank' => false,
            'distinct_documents' => false,
        ]);

        $this->assertFalse($dto->synthesizeAnswer);
        $this->assertFalse($dto->stream);
        $this->assertFalse($dto->rerank);
        $this->assertFalse($dto->distinctDocuments);
    }

    public function testFluentBuilderWithRerank(): void
    {
        $dto = SearchQueryDto::create('hello')
            ->withRerank(true);

        $this->assertTrue($dto->rerank);

        $array = $dto->toArray();
        $this->assertTrue($array['rerank']);

        $cloned = $dto->withRerank(false);
        $this->assertFalse($cloned->rerank);
        $this->assertTrue($dto->rerank); // immutable
    }

    public function testEmptyQueryThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search query cannot be empty.');

        SearchQueryDto::create('   ');
    }

    public function testPagePropertyAndMethodsDoNotExist(): void
    {
        $ref = new \ReflectionClass(SearchQueryDto::class);
        $this->assertFalse($ref->hasProperty('page'));
        $this->assertFalse($ref->hasMethod('withPage'));
        $this->assertFalse($ref->hasMethod('setPage'));
        $this->assertFalse($ref->hasMethod('getPage'));
    }

    public function testScenario1SerializingByocContextItems(): void
    {
        $dto = SearchQueryDto::create('What does Solr do?')
            ->withContextItems([
                new ContextItemDto(externalId: 'node/1', title: 'Test', content: 'Body'),
            ]);

        $this->assertCount(1, $dto->getContextItems() ?? []);
        $this->assertSame('node/1', ($dto->getContextItems() ?? [])[0]->externalId);

        $array = $dto->toArray();
        $this->assertArrayHasKey('context_items', $array);
        $this->assertIsArray($array['context_items']);
        /** @var list<array<string, mixed>> $contextItemsList */
        $contextItemsList = $array['context_items'];
        $this->assertCount(1, $contextItemsList);
        $this->assertSame([
            'external_id' => 'node/1',
            'title' => 'Test',
            'content' => 'Body',
            'chunk_index' => 0,
            'score' => 0.0,
        ], $contextItemsList[0]);
    }

    public function testWithContextItemsAcceptsArraysAndConvertsToDto(): void
    {
        $dto = SearchQueryDto::create('BYOC raw array')
            ->withContextItems([
                [
                    'external_id' => 'raw/1',
                    'title' => 'Raw Title',
                    'content' => 'Raw Content',
                    'chunk_index' => 1,
                    'score' => 0.9,
                ],
            ]);

        $this->assertNotNull($dto->getContextItems());
        $this->assertInstanceOf(ContextItemDto::class, $dto->getContextItems()[0]);
        $this->assertSame('raw/1', $dto->getContextItems()[0]->externalId);

        // withContextItems(null) resets to null
        $reset = $dto->withContextItems(null);
        $this->assertNull($reset->getContextItems());
        $this->assertArrayNotHasKey('context_items', $reset->toArray());
    }

    public function testWithContextItemsAcceptsAssociativeKeyedArray(): void
    {
        // Callers passing entity-keyed arrays (e.g. from Drupal or array_filter)
        $dto = SearchQueryDto::create('BYOC associative keyed')
            ->withContextItems([
                'node_42' => new ContextItemDto(
                    externalId: 'node:42',
                    title: 'Node 42',
                    content: 'Node body',
                ),
                'node_99' => [
                    'external_id' => 'node:99',
                    'title' => 'Node 99',
                    'content' => 'Node 99 body',
                ],
            ]);

        $items = $dto->getContextItems();
        $this->assertNotNull($items);
        $this->assertCount(2, $items);
        $this->assertSame('node:42', $items[0]->externalId);
        $this->assertSame('node:99', $items[1]->externalId);
    }

    public function testConstructorNormalizesContextItems(): void
    {
        $dto = new SearchQueryDto(
            query: 'Direct constructor',
            contextItems: [
                [
                    'external_id' => 'item:1',
                    'title' => 'Item 1',
                    'content' => 'Content 1',
                ],
            ]
        );

        $this->assertNotNull($dto->contextItems);
        $this->assertCount(1, $dto->contextItems);
        $this->assertInstanceOf(ContextItemDto::class, $dto->contextItems[0]);
        $this->assertSame('item:1', $dto->contextItems[0]->externalId);
    }

    public function testConstructorThrowsOnInvalidContextItem(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each context item must be an instance of ContextItemDto or an associative array.');

        new SearchQueryDto(
            query: 'Direct constructor bad items',
            contextItems: ['not-a-dto-or-array'] // @phpstan-ignore argument.type
        );
    }

    public function testWithContextItemsThrowsOnInvalidItem(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each context item must be an instance of ContextItemDto or an associative array.');

        /** @phpstan-ignore argument.type */
        SearchQueryDto::create('bad items')->withContextItems(['not-a-dto']);
    }

    public function testWithIncludeResults(): void
    {
        $dto = SearchQueryDto::create('Include results query')
            ->withIncludeResults(true);

        $this->assertTrue($dto->getIncludeResults());
        $this->assertTrue($dto->shouldIncludeResults());

        $array = $dto->toArray();
        $this->assertArrayHasKey('include_results', $array);
        $this->assertTrue($array['include_results']);

        $toggled = $dto->withIncludeResults(false);
        $this->assertFalse($toggled->getIncludeResults());
        $this->assertFalse($toggled->shouldIncludeResults());
        $this->assertFalse($toggled->toArray()['include_results']);
        $this->assertTrue($dto->shouldIncludeResults(), 'Original must remain immutable');
    }

    public function testFromArrayWithContextItemsAndIncludeResults(): void
    {
        $data = [
            'query' => 'Deserializing BYOC',
            'include_results' => true,
            'context_items' => [
                [
                    'external_id' => 'parsed/1',
                    'title' => 'Parsed Title',
                    'content' => 'Parsed Content',
                    'chunk_index' => 2,
                    'score' => 0.88,
                ],
            ],
        ];

        $dto = SearchQueryDto::fromArray($data);

        $this->assertSame('Deserializing BYOC', $dto->query);
        $this->assertTrue($dto->shouldIncludeResults());
        $this->assertNotNull($dto->getContextItems());
        $this->assertCount(1, $dto->getContextItems());
        $this->assertSame('parsed/1', $dto->getContextItems()[0]->externalId);
        $this->assertSame('Parsed Title', $dto->getContextItems()[0]->title);
        $this->assertSame('Parsed Content', $dto->getContextItems()[0]->content);
        $this->assertSame(2, $dto->getContextItems()[0]->chunkIndex);
        $this->assertSame(0.88, $dto->getContextItems()[0]->score);
    }
}
