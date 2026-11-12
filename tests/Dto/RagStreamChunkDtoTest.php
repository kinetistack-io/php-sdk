<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use PHPUnit\Framework\TestCase;

class RagStreamChunkDtoTest extends TestCase
{
    public function testConstructAndProperties(): void
    {
        $dto = new RagStreamChunkDto(
            text: 'Hello world',
            isDone: false,
            citations: ['node:1', 'node:2'],
            metadata: ['model' => 'llama3.1']
        );

        $this->assertSame('Hello world', $dto->text);
        $this->assertSame('Hello world', $dto->chunk);
        $this->assertSame('Hello world', $dto->getText());
        $this->assertSame('Hello world', $dto->getChunk());
        $this->assertFalse($dto->isDone);
        $this->assertFalse($dto->isDone());
        $this->assertSame(['node:1', 'node:2'], $dto->citations);
        $this->assertSame(['model' => 'llama3.1'], $dto->metadata);
        $this->assertSame('Hello world', (string) $dto);
    }

    public function testCreateHelper(): void
    {
        $dto = RagStreamChunkDto::create('Chunk text', true);

        $this->assertSame('Chunk text', $dto->text);
        $this->assertTrue($dto->isDone);
    }

    public function testToArrayAndFromArray(): void
    {
        $data = [
            'chunk' => 'Partial response',
            'is_done' => false,
            'citations' => ['doc:1'],
            'metadata' => ['tokens' => 12],
        ];

        $dto = RagStreamChunkDto::fromArray($data);

        $this->assertSame('Partial response', $dto->text);
        $this->assertSame('Partial response', $dto->chunk);
        $this->assertFalse($dto->isDone);
        $this->assertSame(['doc:1'], $dto->citations);
        $this->assertSame(['tokens' => 12], $dto->metadata);

        $array = $dto->toArray();
        $this->assertSame('Partial response', $array['text']);
        $this->assertSame('Partial response', $array['chunk']);
        $this->assertFalse($array['is_done']);
        $this->assertSame(['doc:1'], $array['citations']);
        $this->assertSame(['tokens' => 12], $array['metadata']);
    }

    public function testFromArrayWithTextKey(): void
    {
        $data = [
            'text' => 'Using text key',
            'done' => true,
        ];

        $dto = RagStreamChunkDto::fromArray($data);

        $this->assertSame('Using text key', $dto->text);
        $this->assertSame('Using text key', $dto->chunk);
        $this->assertTrue($dto->isDone);
        $this->assertSame([], $dto->citations);
        $this->assertSame([], $dto->metadata);
    }

    public function testRagStreamChunkDtoWithTokensConsumed(): void
    {
        $dto = new RagStreamChunkDto(
            text: 'Final chunk',
            isDone: true,
            tokensConsumed: 75,
        );

        $this->assertSame(75, $dto->tokensConsumed);
        $this->assertSame(75, $dto->toArray()['tokens_consumed']);

        $created = RagStreamChunkDto::create('Final chunk', true, 75);
        $this->assertSame(75, $created->tokensConsumed);

        // From top-level tokens_consumed
        $fromSnake = RagStreamChunkDto::fromArray([
            'chunk' => 'Done',
            'tokens_consumed' => 60,
        ]);
        $this->assertSame(60, $fromSnake->tokensConsumed);

        // From top-level tokensConsumed
        $fromCamel = RagStreamChunkDto::fromArray([
            'chunk' => 'Done',
            'tokensConsumed' => 65,
        ]);
        $this->assertSame(65, $fromCamel->tokensConsumed);

        // From metadata tokens_consumed
        $fromMetaSnake = RagStreamChunkDto::fromArray([
            'chunk' => 'Done',
            'metadata' => ['tokens_consumed' => 80],
        ]);
        $this->assertSame(80, $fromMetaSnake->tokensConsumed);

        // From metadata usage object
        $fromMetaUsage = RagStreamChunkDto::fromArray([
            'chunk' => 'Done',
            'metadata' => ['usage' => ['total_tokens' => 90]],
        ]);
        $this->assertSame(90, $fromMetaUsage->tokensConsumed);

        // Without tokens consumed
        $fromNull = RagStreamChunkDto::fromArray([
            'chunk' => 'In progress',
        ]);
        $this->assertNull($fromNull->tokensConsumed);
        $this->assertArrayNotHasKey('tokens_consumed', $fromNull->toArray());
    }

    public function testRagStreamChunkDtoWithResults(): void
    {
        $resultItem = new SearchResultItemDto(
            externalId: 'res:1',
            title: 'Doc Title',
            content: 'Doc Content',
            chunkIndex: 0,
            score: 0.95,
        );

        $dto = new RagStreamChunkDto(
            text: '',
            results: [$resultItem],
        );

        $this->assertSame('', $dto->text);
        $this->assertTrue($dto->hasResults());
        $this->assertCount(1, $dto->getResults());
        $this->assertSame('res:1', $dto->getResults()[0]->externalId);

        $array = $dto->toArray();
        $this->assertArrayHasKey('results', $array);
        $this->assertIsArray($array['results']);
        /** @var list<array<string, mixed>> $resultsList */
        $resultsList = $array['results'];
        $this->assertCount(1, $resultsList);
        $this->assertSame('res:1', $resultsList[0]['external_id']);

        // Test fromArray() with raw results array
        $fromData = RagStreamChunkDto::fromArray([
            'results' => [
                [
                    'external_id' => 'res:2',
                    'title' => 'Title 2',
                    'content' => 'Content 2',
                    'chunk_index' => 1,
                    'score' => 0.89,
                ],
            ],
        ]);

        $this->assertTrue($fromData->hasResults());
        $this->assertCount(1, $fromData->results);
        $this->assertInstanceOf(SearchResultItemDto::class, $fromData->results[0]);
        $this->assertSame('res:2', $fromData->results[0]->externalId);
        $this->assertSame('Title 2', $fromData->results[0]->title);
    }

    public function testRagStreamChunkDtoWithFallbackReason(): void
    {
        $dto = new RagStreamChunkDto(
            text: 'Interrupted',
            isDone: true,
            fallbackReason: 'Backend timeout',
        );

        $this->assertSame('Backend timeout', $dto->fallbackReason);
        $this->assertSame('Backend timeout', $dto->getFallbackReason());
        $array = $dto->toArray();
        $this->assertSame('Backend timeout', $array['fallback_reason']);

        // From data top-level fallback_reason
        $fromSnake = RagStreamChunkDto::fromArray([
            'chunk' => 'Interrupted',
            'fallback_reason' => 'Rate limit exceeded',
        ]);
        $this->assertSame('Rate limit exceeded', $fromSnake->fallbackReason);
        $this->assertSame('Rate limit exceeded', $fromSnake->getFallbackReason());

        // From data top-level fallbackReason
        $fromCamel = RagStreamChunkDto::fromArray([
            'chunk' => 'Interrupted',
            'fallbackReason' => 'Quota exhausted',
        ]);
        $this->assertSame('Quota exhausted', $fromCamel->fallbackReason);

        // From metadata fallback_reason
        $fromMetaSnake = RagStreamChunkDto::fromArray([
            'chunk' => 'Interrupted',
            'metadata' => ['fallback_reason' => 'Token limit'],
        ]);
        $this->assertSame('Token limit', $fromMetaSnake->fallbackReason);

        // From metadata fallbackReason
        $fromMetaCamel = RagStreamChunkDto::fromArray([
            'chunk' => 'Interrupted',
            'metadata' => ['fallbackReason' => 'Context overflow'],
        ]);
        $this->assertSame('Context overflow', $fromMetaCamel->fallbackReason);

        // Helper create()
        $created = RagStreamChunkDto::create('Text', true, null, 'Error reason');
        $this->assertSame('Error reason', $created->getFallbackReason());
    }
}
