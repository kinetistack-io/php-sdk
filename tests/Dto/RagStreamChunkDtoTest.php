<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\RagStreamChunkDto;
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
}
