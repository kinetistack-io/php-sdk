<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\VisionOptionsDto;
use PHPUnit\Framework\TestCase;

class VisionOptionsDtoTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $dto = new VisionOptionsDto();

        $this->assertSame('en', $dto->language);
        $this->assertTrue($dto->includeTags);
        $this->assertSame(150, $dto->maxLength);
        $this->assertNull($dto->custom);
        $this->assertSame([
            'language' => 'en',
            'include_tags' => true,
            'max_length' => 150,
        ], $dto->toArray());
    }

    public function testFluentBuilder(): void
    {
        $dto = VisionOptionsDto::create()
            ->withLanguage('nl')
            ->withMaxLength(120)
            ->withIncludeTags(false)
            ->withCustom('model', 'qwen2-vl');

        $this->assertSame('nl', $dto->language);
        $this->assertSame(120, $dto->maxLength);
        $this->assertFalse($dto->includeTags);
        $this->assertSame(['model' => 'qwen2-vl'], $dto->custom);

        $array = $dto->toArray();
        $this->assertSame('nl', $array['language']);
        $this->assertSame(120, $array['max_length']);
        $this->assertFalse($array['include_tags']);
        $this->assertSame('qwen2-vl', $array['model']);
    }

    public function testFromArrayWithSnakeCase(): void
    {
        $dto = VisionOptionsDto::fromArray([
            'language' => 'de',
            'include_tags' => false,
            'max_length' => 200,
            'extra_param' => 'val',
        ]);

        $this->assertSame('de', $dto->language);
        $this->assertFalse($dto->includeTags);
        $this->assertSame(200, $dto->maxLength);
        $this->assertSame(['extra_param' => 'val'], $dto->custom);
    }

    public function testFromArrayWithCamelCase(): void
    {
        $dto = VisionOptionsDto::fromArray([
            'language' => 'fr',
            'includeTags' => true,
            'maxLength' => 100,
        ]);

        $this->assertSame('fr', $dto->language);
        $this->assertTrue($dto->includeTags);
        $this->assertSame(100, $dto->maxLength);
        $this->assertNull($dto->custom);
    }

    public function testWithCustomOptions(): void
    {
        $dto = VisionOptionsDto::create()
            ->withCustomOptions(['opt1' => 'v1', 'opt2' => 'v2']);

        $this->assertSame(['opt1' => 'v1', 'opt2' => 'v2'], $dto->custom);
        $array = $dto->toArray();
        $this->assertSame('v1', $array['opt1']);
        $this->assertSame('v2', $array['opt2']);
    }

    public function testEmptyLanguageThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Language code cannot be empty.');

        new VisionOptionsDto(language: '   ');
    }

    public function testInvalidMaxLengthThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxLength must be at least 1.');

        new VisionOptionsDto(maxLength: 0);
    }
}
