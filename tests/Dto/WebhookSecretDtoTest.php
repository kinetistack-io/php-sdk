<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\WebhookSecretDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookSecretDto::class)]
final class WebhookSecretDtoTest extends TestCase
{
    private const SECRET = 'whsec_test_secret_key_1234567890abcdef';
    private const ROTATED_AT = '2026-10-27T12:00:00Z';

    public function testConstructorAndProperties(): void
    {
        $dto = new WebhookSecretDto(self::SECRET, self::ROTATED_AT);

        $this->assertSame(self::SECRET, $dto->webhookSecret);
        $this->assertSame(self::ROTATED_AT, $dto->rotatedAt);
    }

    public function testToArray(): void
    {
        $dto = new WebhookSecretDto(self::SECRET, self::ROTATED_AT);

        $expected = [
            'webhook_secret' => self::SECRET,
            'rotated_at' => self::ROTATED_AT,
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    public function testFromArrayWithSnakeCase(): void
    {
        $data = [
            'webhook_secret' => self::SECRET,
            'rotated_at' => self::ROTATED_AT,
        ];

        $dto = WebhookSecretDto::fromArray($data);

        $this->assertSame(self::SECRET, $dto->webhookSecret);
        $this->assertSame(self::ROTATED_AT, $dto->rotatedAt);
    }

    public function testFromArrayWithCamelCase(): void
    {
        $data = [
            'webhookSecret' => self::SECRET,
            'rotatedAt' => self::ROTATED_AT,
        ];

        $dto = WebhookSecretDto::fromArray($data);

        $this->assertSame(self::SECRET, $dto->webhookSecret);
        $this->assertSame(self::ROTATED_AT, $dto->rotatedAt);
    }

    public function testEmptySecretThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('webhookSecret cannot be empty.');

        new WebhookSecretDto('', self::ROTATED_AT);
    }

    public function testWhitespaceSecretThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('webhookSecret cannot be empty.');

        new WebhookSecretDto("   \n\t  ", self::ROTATED_AT);
    }

    public function testEmptyRotatedAtThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('rotatedAt cannot be empty.');

        new WebhookSecretDto(self::SECRET, '');
    }

    public function testWhitespaceRotatedAtThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('rotatedAt cannot be empty.');

        new WebhookSecretDto(self::SECRET, '   ');
    }

    public function testFromArrayWithMissingDataThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WebhookSecretDto::fromArray([]);
    }

    public function testDebugInfoMasksSecret(): void
    {
        $dto = new WebhookSecretDto(self::SECRET, self::ROTATED_AT);

        $debugInfo = $dto->__debugInfo();

        $this->assertSame('[REDACTED]', $debugInfo['webhookSecret']);
        $this->assertSame(self::ROTATED_AT, $debugInfo['rotatedAt']);

        $export = var_export($debugInfo, true);
        $this->assertStringNotContainsString(self::SECRET, $export);

        $printOutput = print_r($dto, true);
        $this->assertStringNotContainsString(self::SECRET, $printOutput);
        $this->assertStringContainsString('[REDACTED]', $printOutput);
    }

    public function testSensitiveParameterAttributeOnConstructorSecret(): void
    {
        $refMethod = new \ReflectionMethod(WebhookSecretDto::class, '__construct');
        $params = $refMethod->getParameters();

        $secretParam = null;
        foreach ($params as $param) {
            if ($param->getName() === 'webhookSecret') {
                $secretParam = $param;
                break;
            }
        }

        $this->assertNotNull($secretParam, 'Parameter $webhookSecret not found in WebhookSecretDto::__construct');
        $attributes = $secretParam->getAttributes(\SensitiveParameter::class);
        $this->assertNotEmpty($attributes, 'Parameter $webhookSecret must have #[\SensitiveParameter] attribute.');
    }
}
