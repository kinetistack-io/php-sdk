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
}
