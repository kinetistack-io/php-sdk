<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\FacetBucketDto;
use PHPUnit\Framework\TestCase;

class FacetBucketDtoTest extends TestCase
{
    public function testToArrayShape(): void
    {
        $dto = new FacetBucketDto('article', 47);

        $this->assertSame('article', $dto->value);
        $this->assertSame(47, $dto->count);
        $this->assertSame(['value' => 'article', 'count' => 47], $dto->toArray());
    }

    public function testFromArrayHappyPath(): void
    {
        $dto = FacetBucketDto::fromArray(['value' => 'page', 'count' => 12]);

        $this->assertSame('page', $dto->value);
        $this->assertSame(12, $dto->count);
        $this->assertSame(['value' => 'page', 'count' => 12], $dto->toArray());
    }

    public function testFromArrayDefaultsOnEmpty(): void
    {
        $dto = FacetBucketDto::fromArray([]);

        $this->assertSame('', $dto->value);
        $this->assertSame(0, $dto->count);
        $this->assertSame(['value' => '', 'count' => 0], $dto->toArray());
    }

    public function testFromArrayTypeCoercion(): void
    {
        $dto = FacetBucketDto::fromArray([
            'value' => 123,
            'count' => '47',
        ]);

        $this->assertSame('123', $dto->value);
        $this->assertSame(47, $dto->count);
    }
}
