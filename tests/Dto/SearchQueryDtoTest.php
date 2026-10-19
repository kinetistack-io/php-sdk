<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

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
}
