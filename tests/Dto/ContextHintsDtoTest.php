<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\ContextHintsDto;
use PHPUnit\Framework\TestCase;

class ContextHintsDtoTest extends TestCase
{
    public function testDefaultValuesAndIsEmpty(): void
    {
        $dto = ContextHintsDto::create();

        $this->assertNull($dto->pageTitle);
        $this->assertNull($dto->taxonomy);
        $this->assertNull($dto->surroundingText);
        $this->assertNull($dto->custom);
        $this->assertTrue($dto->isEmpty());
        $this->assertSame([], $dto->toArray());
    }

    public function testFluentBuilder(): void
    {
        $dto = ContextHintsDto::create('Breaking News')
            ->withTaxonomy(['Politics', 'World'])
            ->withSurroundingText('A major announcement occurred yesterday.')
            ->withCustom('author', 'Jane Doe');

        $this->assertFalse($dto->isEmpty());
        $this->assertSame('Breaking News', $dto->pageTitle);
        $this->assertSame(['Politics', 'World'], $dto->taxonomy);
        $this->assertSame('A major announcement occurred yesterday.', $dto->surroundingText);
        $this->assertSame(['author' => 'Jane Doe'], $dto->custom);

        $array = $dto->toArray();
        $this->assertSame('Breaking News', $array['page_title']);
        $this->assertSame(['Politics', 'World'], $array['taxonomy']);
        $this->assertSame('A major announcement occurred yesterday.', $array['surrounding_text']);
        $this->assertSame('Jane Doe', $array['author']);
    }

    public function testWithTaxonomyString(): void
    {
        $dto = ContextHintsDto::create()->withTaxonomy('Sports');

        $this->assertSame(['Sports'], $dto->taxonomy);
        $this->assertSame(['taxonomy' => ['Sports']], $dto->toArray());
    }

    public function testWithCustomHints(): void
    {
        $dto = ContextHintsDto::create()
            ->withCustomHints(['field1' => 'val1', 'field2' => 'val2']);

        $this->assertSame(['field1' => 'val1', 'field2' => 'val2'], $dto->custom);
        $this->assertSame([
            'field1' => 'val1',
            'field2' => 'val2',
        ], $dto->toArray());
    }

    public function testFromArraySnakeCase(): void
    {
        $dto = ContextHintsDto::fromArray([
            'page_title' => 'Article Title',
            'taxonomy' => ['News', 'Local'],
            'surrounding_text' => 'Context paragraph',
            'extra_tag' => 'urgent',
        ]);

        $this->assertSame('Article Title', $dto->pageTitle);
        $this->assertSame(['News', 'Local'], $dto->taxonomy);
        $this->assertSame('Context paragraph', $dto->surroundingText);
        $this->assertSame(['extra_tag' => 'urgent'], $dto->custom);
    }

    public function testFromArrayCamelCase(): void
    {
        $dto = ContextHintsDto::fromArray([
            'pageTitle' => 'Camel Title',
            'taxonomy' => 'SingleTag',
            'surroundingText' => 'Camel text',
        ]);

        $this->assertSame('Camel Title', $dto->pageTitle);
        $this->assertSame(['SingleTag'], $dto->taxonomy);
        $this->assertSame('Camel text', $dto->surroundingText);
    }
}
