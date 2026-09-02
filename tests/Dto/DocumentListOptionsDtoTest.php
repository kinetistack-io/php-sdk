<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\DocumentListOptionsDto;
use PHPUnit\Framework\TestCase;

class DocumentListOptionsDtoTest extends TestCase
{
    public function testDefaultValuesAndIsEmpty(): void
    {
        $dto = DocumentListOptionsDto::create();

        $this->assertNull($dto->page);
        $this->assertNull($dto->itemsPerPage);
        $this->assertNull($dto->locale);
        $this->assertNull($dto->externalId);
        $this->assertNull($dto->createdAt);
        $this->assertNull($dto->updatedAt);
        $this->assertNull($dto->order);
        $this->assertNull($dto->custom);
        $this->assertTrue($dto->isEmpty());
        $this->assertSame([], $dto->toArray());
    }

    public function testFluentBuilder(): void
    {
        $dto = DocumentListOptionsDto::create()
            ->withPage(2)
            ->withItemsPerPage(25)
            ->withLocale('da')
            ->withExternalId('node:')
            ->withCreatedAt(['after' => '2026-01-01T00:00:00Z'])
            ->withUpdatedAt('2026-01-02T00:00:00Z')
            ->withOrder('created_at', 'desc')
            ->withCustom('tag', 'marketing');

        $this->assertFalse($dto->isEmpty());
        $this->assertSame(2, $dto->page);
        $this->assertSame(25, $dto->itemsPerPage);
        $this->assertSame('da', $dto->locale);
        $this->assertSame('node:', $dto->externalId);
        $this->assertSame(['after' => '2026-01-01T00:00:00Z'], $dto->createdAt);
        $this->assertSame('2026-01-02T00:00:00Z', $dto->updatedAt);
        $this->assertSame(['created_at' => 'desc'], $dto->order);
        $this->assertSame(['tag' => 'marketing'], $dto->custom);

        $array = $dto->toArray();
        $this->assertSame(2, $array['page']);
        $this->assertSame(25, $array['itemsPerPage']);
        $this->assertSame('da', $array['locale']);
        $this->assertSame('node:', $array['external_id']);
        $this->assertSame(['after' => '2026-01-01T00:00:00Z'], $array['created_at']);
        $this->assertSame('2026-01-02T00:00:00Z', $array['updated_at']);
        $this->assertSame(['created_at' => 'desc'], $array['order']);
        $this->assertSame('marketing', $array['tag']);
    }

    public function testWithLimitAlias(): void
    {
        $dto = DocumentListOptionsDto::create()->withLimit(50);
        $this->assertSame(50, $dto->itemsPerPage);
        $this->assertSame(['itemsPerPage' => 50], $dto->toArray());
    }

    public function testWithOrderArray(): void
    {
        $dto = DocumentListOptionsDto::create()
            ->withOrder(['title' => 'asc', 'created_at' => 'desc']);

        $this->assertSame(['title' => 'asc', 'created_at' => 'desc'], $dto->order);
    }

    public function testWithCustomFilters(): void
    {
        $dto = DocumentListOptionsDto::create()
            ->withCustomFilters(['author' => 'Alice']);

        $this->assertSame(['author' => 'Alice'], $dto->custom);
        $this->assertSame(['author' => 'Alice'], $dto->toArray());
    }

    public function testFromArraySnakeCase(): void
    {
        $dto = DocumentListOptionsDto::fromArray([
            'page' => 3,
            'items_per_page' => 10,
            'locale' => 'en',
            'external_id' => 'article:',
            'created_at' => '2026-05-01',
            'updated_at' => '2026-05-02',
            'order' => ['title' => 'asc'],
            'search_term' => 'php',
        ]);

        $this->assertSame(3, $dto->page);
        $this->assertSame(10, $dto->itemsPerPage);
        $this->assertSame('en', $dto->locale);
        $this->assertSame('article:', $dto->externalId);
        $this->assertSame('2026-05-01', $dto->createdAt);
        $this->assertSame('2026-05-02', $dto->updatedAt);
        $this->assertSame(['title' => 'asc'], $dto->order);
        $this->assertSame(['search_term' => 'php'], $dto->custom);
    }

    public function testFromArrayCamelCase(): void
    {
        $dto = DocumentListOptionsDto::fromArray([
            'page' => 1,
            'itemsPerPage' => 20,
            'externalId' => 'doc:1',
            'createdAt' => '2026-06-01',
            'updatedAt' => '2026-06-02',
        ]);

        $this->assertSame(1, $dto->page);
        $this->assertSame(20, $dto->itemsPerPage);
        $this->assertSame('doc:1', $dto->externalId);
        $this->assertSame('2026-06-01', $dto->createdAt);
        $this->assertSame('2026-06-02', $dto->updatedAt);
    }
}
