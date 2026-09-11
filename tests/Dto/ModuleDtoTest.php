<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\ModuleDto;
use PHPUnit\Framework\TestCase;

class ModuleDtoTest extends TestCase
{
    public function testInstantiationAndProperties(): void
    {
        $dto = new ModuleDto('vision', 'Image Analysis & Alt-Text', 'enabled', true);

        $this->assertSame('vision', $dto->identifier);
        $this->assertSame('Image Analysis & Alt-Text', $dto->label);
        $this->assertSame('enabled', $dto->status);
        $this->assertTrue($dto->accessible);
        $this->assertTrue($dto->isAccessible());
        $this->assertTrue($dto->isEnabled());
        $this->assertTrue($dto->isGloballyEnabled());
    }

    public function testDisabledModule(): void
    {
        $dto = new ModuleDto('rag', 'Retrieval-Augmented Generation', 'disabled', false);

        $this->assertSame('rag', $dto->identifier);
        $this->assertSame('Retrieval-Augmented Generation', $dto->label);
        $this->assertSame('disabled', $dto->status);
        $this->assertFalse($dto->accessible);
        $this->assertFalse($dto->isAccessible());
        $this->assertFalse($dto->isEnabled());
        $this->assertFalse($dto->isGloballyEnabled());
    }

    public function testExperimentalAccessibleModule(): void
    {
        $dto = new ModuleDto('preview-feat', 'Preview Feature', 'experimental', true);

        $this->assertSame('preview-feat', $dto->identifier);
        $this->assertSame('Preview Feature', $dto->label);
        $this->assertSame('experimental', $dto->status);
        $this->assertTrue($dto->accessible);
        $this->assertTrue($dto->isAccessible());
        $this->assertTrue($dto->isEnabled());
        $this->assertFalse($dto->isGloballyEnabled());
    }

    public function testGloballyEnabledButNotAccessibleModule(): void
    {
        $dto = new ModuleDto('search', 'Search & Indexing', 'enabled', false);

        $this->assertSame('search', $dto->identifier);
        $this->assertSame('enabled', $dto->status);
        $this->assertFalse($dto->accessible);
        $this->assertFalse($dto->isAccessible());
        $this->assertFalse($dto->isEnabled());
        $this->assertTrue($dto->isGloballyEnabled());
    }

    public function testToArrayAndFromArray(): void
    {
        $original = new ModuleDto('vision', 'Image Analysis', 'enabled', true);
        $array = $original->toArray();

        $expected = [
            'identifier' => 'vision',
            'label' => 'Image Analysis',
            'status' => 'enabled',
            'accessible' => true,
        ];
        $this->assertSame($expected, $array);

        $recreated = ModuleDto::fromArray($array);
        $this->assertSame($original->identifier, $recreated->identifier);
        $this->assertSame($original->label, $recreated->label);
        $this->assertSame($original->status, $recreated->status);
        $this->assertSame($original->accessible, $recreated->accessible);
    }

    public function testFromArrayWithDefaults(): void
    {
        $dto = ModuleDto::fromArray([
            'identifier' => 'custom',
            'label' => 'Custom Module',
        ]);

        $this->assertSame('custom', $dto->identifier);
        $this->assertSame('Custom Module', $dto->label);
        $this->assertSame('', $dto->status);
        $this->assertFalse($dto->accessible);
    }

    public function testEmptyIdentifierThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('identifier cannot be empty.');
        new ModuleDto('', 'Label', 'enabled', true);
    }

    public function testWhitespaceIdentifierThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('identifier cannot be empty.');
        new ModuleDto('   ', 'Label', 'enabled', true);
    }

    public function testEmptyLabelThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('label cannot be empty.');
        new ModuleDto('vision', '', 'enabled', true);
    }

    public function testWhitespaceLabelThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('label cannot be empty.');
        new ModuleDto('vision', '   ', 'enabled', true);
    }
}
