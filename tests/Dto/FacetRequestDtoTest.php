<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\FacetRequestDto;
use PHPUnit\Framework\TestCase;

class FacetRequestDtoTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $dto = new FacetRequestDto();

        $this->assertSame([], $dto->fields);
        $this->assertSame([], $dto->filters);
        $this->assertSame(['fields' => []], $dto->toArray());
    }

    public function testConstructorWithValues(): void
    {
        $dto = new FacetRequestDto(['type', 'language'], ['type' => ['article']]);

        $this->assertSame(['type', 'language'], $dto->fields);
        $this->assertSame(['type' => ['article']], $dto->filters);
    }

    public function testWithFilterImmutability(): void
    {
        $req = new FacetRequestDto(['type']);
        $req2 = $req->withFilter('type', ['article']);

        $this->assertNotSame($req, $req2);
        $this->assertSame([], $req->filters);
        $this->assertSame(['type' => ['article']], $req2->filters);
        $this->assertSame(['type'], $req2->fields);

        // Test with string value normalized to array
        $req3 = $req2->withFilter('language', 'en');
        $this->assertSame(['en'], $req3->filters['language']);
    }

    public function testToArrayOmitsEmptyFilters(): void
    {
        $req = new FacetRequestDto(['category']);
        $array = $req->toArray();

        $this->assertSame(['fields' => ['category']], $array);
        $this->assertArrayNotHasKey('filters', $array);
    }

    public function testFromArrayRoundTrip(): void
    {
        $input = [
            'fields' => ['type', 'language'],
            'filters' => [
                'type' => ['article'],
            ],
        ];

        $dto = FacetRequestDto::fromArray($input);

        $this->assertSame(['type', 'language'], $dto->fields);
        $this->assertSame(['type' => ['article']], $dto->filters);
        $this->assertSame($input, $dto->toArray());
    }

    public function testFromArrayEmptyEdgeCase(): void
    {
        $dto = FacetRequestDto::fromArray([]);

        $this->assertSame([], $dto->fields);
        $this->assertSame([], $dto->filters);
        $this->assertSame(['fields' => []], $dto->toArray());
    }

    public function testFromArraySkipsNonArrayFilters(): void
    {
        $data = [
            'fields' => ['tag'],
            'filters' => [
                'invalid' => 'not_an_array',
                'valid' => ['tag1', 'tag2'],
            ],
        ];

        $dto = FacetRequestDto::fromArray($data);

        $this->assertSame(['tag'], $dto->fields);
        $this->assertArrayNotHasKey('invalid', $dto->filters);
        $this->assertSame(['valid' => ['tag1', 'tag2']], $dto->filters);
    }

    public function testFromArrayFiltersOutNonScalarValues(): void
    {
        $data = [
            'fields' => ['tag', ['nested_array'], new \stdClass(), 123],
            'filters' => [
                'type' => ['article', ['nested'], null, 456],
            ],
        ];

        $dto = FacetRequestDto::fromArray($data);

        $this->assertSame(['tag', '123'], $dto->fields);
        $this->assertSame(['type' => ['article', '456']], $dto->filters);
    }

    public function testWithFilterFiltersOutNonScalarValues(): void
    {
        $dto = new FacetRequestDto();
        $filtered = $dto->withFilter('type', ['article', ['invalid'], 100]);

        $this->assertSame(['type' => ['article', '100']], $filtered->filters);
    }
}
