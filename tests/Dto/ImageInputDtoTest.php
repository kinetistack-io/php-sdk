<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\ContextHintsDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use PHPUnit\Framework\TestCase;

class ImageInputDtoTest extends TestCase
{
    public function testValidWithImageUrl(): void
    {
        $dto = new ImageInputDto(
            externalId: 'media:1',
            imageUrl: 'https://example.com/test.jpg',
            contextHints: ['page_title' => 'Sample Title']
        );

        $this->assertSame('media:1', $dto->externalId);
        $this->assertSame('https://example.com/test.jpg', $dto->imageUrl);
        $this->assertInstanceOf(ContextHintsDto::class, $dto->contextHints);
        $this->assertSame(['page_title' => 'Sample Title'], $dto->contextHints->toArray());
        $this->assertNull($dto->imageBase64);

        $array = $dto->toArray();
        $this->assertSame('media:1', $array['external_id']);
        $this->assertSame('https://example.com/test.jpg', $array['image_url']);
        $this->assertSame(['page_title' => 'Sample Title'], $array['context_hints']);
        $this->assertArrayNotHasKey('image_base64', $array);
    }

    public function testValidWithContextHintsDto(): void
    {
        $hints = ContextHintsDto::create('Sample Title')->withTaxonomy(['Tech']);
        $dto = new ImageInputDto(
            externalId: 'media:10',
            imageUrl: 'https://example.com/test10.jpg',
            contextHints: $hints
        );

        $this->assertSame('media:10', $dto->externalId);
        $this->assertSame('https://example.com/test10.jpg', $dto->imageUrl);
        $this->assertSame($hints, $dto->contextHints);

        $array = $dto->toArray();
        $this->assertSame([
            'page_title' => 'Sample Title',
            'taxonomy' => ['Tech'],
        ], $array['context_hints']);
    }

    public function testValidWithImageBase64(): void
    {
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $dto = new ImageInputDto(
            externalId: 'media:2',
            imageUrl: null,
            contextHints: ['category' => 'nature'],
            imageBase64: $base64
        );

        $this->assertSame('media:2', $dto->externalId);
        $this->assertNull($dto->imageUrl);
        $this->assertSame($base64, $dto->imageBase64);
        $this->assertInstanceOf(ContextHintsDto::class, $dto->contextHints);
        $this->assertSame(['category' => 'nature'], $dto->contextHints->toArray());

        $array = $dto->toArray();
        $this->assertSame('media:2', $array['external_id']);
        $this->assertSame($base64, $array['image_base64']);
        $this->assertSame(['category' => 'nature'], $array['context_hints']);
        $this->assertArrayNotHasKey('image_url', $array);
    }

    public function testValidWithDataUri(): void
    {
        $dataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $dto = new ImageInputDto(
            externalId: 'media:3',
            imageUrl: null,
            contextHints: [],
            imageBase64: $dataUri
        );

        $this->assertSame('media:3', $dto->externalId);
        $this->assertNull($dto->imageUrl);
        $this->assertSame($dataUri, $dto->imageBase64);
        $this->assertNull($dto->contextHints);

        $array = $dto->toArray();
        $this->assertSame('media:3', $array['external_id']);
        $this->assertSame($dataUri, $array['image_base64']);
        $this->assertSame([], $array['context_hints']);
        $this->assertArrayNotHasKey('image_url', $array);
    }

    public function testValidationFailsWhenBothImageUrlAndImageBase64Provided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Exactly one of imageUrl or imageBase64 must be provided.');

        new ImageInputDto(
            externalId: 'media:1',
            imageUrl: 'https://example.com/test.jpg',
            contextHints: [],
            imageBase64: 'some-base64-string'
        );
    }

    public function testValidationFailsWhenNeitherImageUrlNorImageBase64Provided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Exactly one of imageUrl or imageBase64 must be provided.');

        new ImageInputDto(
            externalId: 'media:1',
            imageUrl: null,
            contextHints: [],
            imageBase64: null
        );
    }

    public function testValidationFailsWhenEmptyExternalId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('externalId cannot be empty.');

        new ImageInputDto(
            externalId: '   ',
            imageUrl: 'https://example.com/test.jpg'
        );
    }

    public function testValidationFailsWhenEmptyImageUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('imageUrl cannot be empty when provided.');

        new ImageInputDto(
            externalId: 'media:1',
            imageUrl: '   '
        );
    }

    public function testValidationFailsWhenEmptyImageBase64(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('imageBase64 cannot be empty when provided.');

        new ImageInputDto(
            externalId: 'media:1',
            imageUrl: null,
            contextHints: [],
            imageBase64: '   '
        );
    }

    public function testFromArrayWithImageUrl(): void
    {
        $dto = ImageInputDto::fromArray([
            'external_id' => 'media:100',
            'image_url' => 'https://example.com/100.png',
            'context_hints' => ['author' => 'Alice'],
        ]);

        $this->assertSame('media:100', $dto->externalId);
        $this->assertSame('https://example.com/100.png', $dto->imageUrl);
        $this->assertInstanceOf(ContextHintsDto::class, $dto->contextHints);
        $this->assertSame(['author' => 'Alice'], $dto->contextHints->toArray());
        $this->assertNull($dto->imageBase64);

        $array = $dto->toArray();
        $this->assertSame('media:100', $array['external_id']);
        $this->assertSame('https://example.com/100.png', $array['image_url']);
        $this->assertSame(['author' => 'Alice'], $array['context_hints']);
        $this->assertArrayNotHasKey('image_base64', $array);
    }

    public function testFromArrayWithImageBase64(): void
    {
        $dto = ImageInputDto::fromArray([
            'external_id' => 'media:200',
            'image_base64' => 'base64payload',
            'context_hints' => ['author' => 'Bob'],
        ]);

        $this->assertSame('media:200', $dto->externalId);
        $this->assertNull($dto->imageUrl);
        $this->assertSame('base64payload', $dto->imageBase64);
        $this->assertInstanceOf(ContextHintsDto::class, $dto->contextHints);
        $this->assertSame(['author' => 'Bob'], $dto->contextHints->toArray());

        $array = $dto->toArray();
        $this->assertSame('media:200', $array['external_id']);
        $this->assertSame('base64payload', $array['image_base64']);
        $this->assertSame(['author' => 'Bob'], $array['context_hints']);
        $this->assertArrayNotHasKey('image_url', $array);
    }

    public function testFromArrayWithCamelCaseKeys(): void
    {
        $dto = ImageInputDto::fromArray([
            'externalId' => 'media:300',
            'imageBase64' => 'base64payload300',
            'contextHints' => ['tag' => 'v1'],
        ]);

        $this->assertSame('media:300', $dto->externalId);
        $this->assertNull($dto->imageUrl);
        $this->assertSame('base64payload300', $dto->imageBase64);
        $this->assertInstanceOf(ContextHintsDto::class, $dto->contextHints);
        $this->assertSame(['tag' => 'v1'], $dto->contextHints->toArray());
    }
}
