<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\HealthStatusDto;
use PHPUnit\Framework\TestCase;

class HealthStatusDtoTest extends TestCase
{
    public function testHealthStatusOk(): void
    {
        $dto = HealthStatusDto::fromArray(['status' => 'ok']);

        $this->assertSame('ok', $dto->status);
        $this->assertTrue($dto->isHealthy());
        $this->assertTrue($dto->isReady());
        $this->assertNull($dto->checks);
        $this->assertSame([], $dto->getChecks());
        $this->assertSame(['status' => 'ok'], $dto->toArray());
    }

    public function testHealthStatusReadyWithChecks(): void
    {
        $checks = ['database' => 'ok', 'ollama' => 'ok'];
        $dto = new HealthStatusDto('ready', $checks);

        $this->assertSame('ready', $dto->status);
        $this->assertTrue($dto->isHealthy());
        $this->assertTrue($dto->isReady());
        $this->assertSame($checks, $dto->checks);
        $this->assertSame($checks, $dto->getChecks());
        $this->assertSame([
            'status' => 'ready',
            'checks' => $checks,
        ], $dto->toArray());
    }

    public function testHealthStatusError(): void
    {
        $dto = HealthStatusDto::fromArray([
            'status' => 'error',
            'checks' => ['database' => 'error'],
        ]);

        $this->assertSame('error', $dto->status);
        $this->assertFalse($dto->isHealthy());
        $this->assertFalse($dto->isReady());
        $this->assertSame(['database' => 'error'], $dto->getChecks());
    }

    public function testHealthStatusWithSubCheckFailure(): void
    {
        $dto = HealthStatusDto::fromArray([
            'status' => 'ok',
            'checks' => [
                'postgres' => 'ok',
                'redis' => 'error',
            ],
        ]);

        $this->assertTrue($dto->isHealthy());
        $this->assertFalse($dto->isReady());
    }

    public function testHealthStatusEmptyData(): void
    {
        $dto = HealthStatusDto::fromArray([]);

        $this->assertSame('', $dto->status);
        $this->assertFalse($dto->isHealthy());
        $this->assertFalse($dto->isReady());
        $this->assertNull($dto->checks);
    }
}
