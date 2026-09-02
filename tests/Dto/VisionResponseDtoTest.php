<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\VisionResponseDto;
use PHPUnit\Framework\TestCase;

class VisionResponseDtoTest extends TestCase
{
    public function testConstructor(): void
    {
        $dto = new VisionResponseDto(
            altText: 'A golden retriever running on grass',
            caption: 'Golden retriever enjoying an outdoor park',
            tags: ['dog', 'retriever', 'grass', 'outdoor'],
            confidenceScore: 0.96,
            modelUsed: 'qwen2-vl:7b'
        );

        $this->assertSame('A golden retriever running on grass', $dto->altText);
        $this->assertSame('Golden retriever enjoying an outdoor park', $dto->caption);
        $this->assertSame(['dog', 'retriever', 'grass', 'outdoor'], $dto->tags);
        $this->assertSame(0.96, $dto->confidenceScore);
        $this->assertSame('qwen2-vl:7b', $dto->modelUsed);
    }

    public function testFromArrayWithFullData(): void
    {
        $data = [
            'alt_text' => 'A cup of coffee on a wooden table',
            'caption' => 'Morning espresso',
            'tags' => ['coffee', 'cup', 'table'],
            'confidence_score' => 0.92,
            'model_used' => 'moondream2',
        ];

        $dto = VisionResponseDto::fromArray($data);

        $this->assertSame('A cup of coffee on a wooden table', $dto->altText);
        $this->assertSame('Morning espresso', $dto->caption);
        $this->assertSame(['coffee', 'cup', 'table'], $dto->tags);
        $this->assertSame(0.92, $dto->confidenceScore);
        $this->assertSame('moondream2', $dto->modelUsed);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $dto = VisionResponseDto::fromArray([]);

        $this->assertSame('', $dto->altText);
        $this->assertNull($dto->caption);
        $this->assertSame([], $dto->tags);
        $this->assertNull($dto->confidenceScore);
        $this->assertNull($dto->modelUsed);
    }

    public function testHasTags(): void
    {
        $dtoWithTags = new VisionResponseDto(
            altText: 'Sample image',
            caption: null,
            tags: ['cat', 'dog'],
            confidenceScore: null,
            modelUsed: null
        );
        $this->assertTrue($dtoWithTags->hasTags());

        $dtoEmptyTags = new VisionResponseDto(
            altText: 'Sample image',
            caption: null,
            tags: [],
            confidenceScore: null,
            modelUsed: null
        );
        $this->assertFalse($dtoEmptyTags->hasTags());
    }

    public function testGetTagsAsStringDefaultSeparator(): void
    {
        $dto = new VisionResponseDto(
            altText: 'Sample image',
            caption: null,
            tags: ['cat', 'dog'],
            confidenceScore: null,
            modelUsed: null
        );

        $this->assertSame('cat, dog', $dto->getTagsAsString());
    }

    public function testGetTagsAsStringCustomSeparator(): void
    {
        $dto = new VisionResponseDto(
            altText: 'Sample image',
            caption: null,
            tags: ['cat', 'dog', 'pet'],
            confidenceScore: null,
            modelUsed: null
        );

        $this->assertSame('cat | dog | pet', $dto->getTagsAsString(' | '));
        $this->assertSame('cat;dog;pet', $dto->getTagsAsString(';'));
    }

    public function testGetTagsAsStringEmpty(): void
    {
        $dto = new VisionResponseDto(
            altText: 'Sample image',
            caption: null,
            tags: [],
            confidenceScore: null,
            modelUsed: null
        );

        $this->assertSame('', $dto->getTagsAsString());
        $this->assertSame('', $dto->getTagsAsString(' - '));
    }

    public function testToArrayWithAllFields(): void
    {
        $dto = new VisionResponseDto(
            altText: 'A mountain peak',
            caption: 'Snowy peak at dawn',
            tags: ['mountain', 'snow'],
            confidenceScore: 0.98,
            modelUsed: 'qwen2-vl:7b'
        );

        $this->assertSame([
            'alt_text' => 'A mountain peak',
            'tags' => ['mountain', 'snow'],
            'caption' => 'Snowy peak at dawn',
            'confidence_score' => 0.98,
            'model_used' => 'qwen2-vl:7b',
        ], $dto->toArray());
    }

    public function testToArrayWithMinimalFields(): void
    {
        $dto = new VisionResponseDto(
            altText: 'A simple image',
            caption: null,
            tags: [],
            confidenceScore: null,
            modelUsed: null
        );

        $this->assertSame([
            'alt_text' => 'A simple image',
            'tags' => [],
        ], $dto->toArray());
    }

    public function testFromArrayWithNonStringOrCoercibleValues(): void
    {
        $data = [
            'alt_text' => 12345,
            'caption' => 67890,
            'tags' => ['outdoor', 42, true],
            'confidence_score' => '0.85',
            'model_used' => 999,
        ];

        $dto = VisionResponseDto::fromArray($data);

        $this->assertSame('12345', $dto->altText);
        $this->assertSame('67890', $dto->caption);
        $this->assertSame(['outdoor', '42', '1'], $dto->tags);
        $this->assertSame(0.85, $dto->confidenceScore);
        $this->assertSame('999', $dto->modelUsed);
    }

    public function testFromArrayWithCamelCaseKeys(): void
    {
        $data = [
            'altText' => 'A sunset on the beach',
            'caption' => 'Golden hour over waves',
            'tags' => ['beach', 'sunset'],
            'confidenceScore' => 0.94,
            'modelUsed' => 'qwen2-vl:7b',
        ];

        $dto = VisionResponseDto::fromArray($data);

        $this->assertSame('A sunset on the beach', $dto->altText);
        $this->assertSame('Golden hour over waves', $dto->caption);
        $this->assertSame(['beach', 'sunset'], $dto->tags);
        $this->assertSame(0.94, $dto->confidenceScore);
        $this->assertSame('qwen2-vl:7b', $dto->modelUsed);
    }

    public function testFromArrayWithNonArrayTagsFallsBackToEmptyArray(): void
    {
        $data = [
            'alt_text' => 'Testing non-array tags',
            'tags' => 'not-an-array',
        ];

        $dto = VisionResponseDto::fromArray($data);

        $this->assertSame([], $dto->tags);
        $this->assertFalse($dto->hasTags());
    }
}
