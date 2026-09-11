<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Exception;

use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\ServiceModuleDisabledException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceModuleDisabledException::class)]
final class ServiceModuleDisabledExceptionTest extends TestCase
{
    public function testInstantiationAndGetters(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new ServiceModuleDisabledException(
            "Service module 'rag' is not enabled for your organization.",
            'rag',
            $previous
        );

        $this->assertInstanceOf(KinetiException::class, $exception);
        $this->assertSame("Service module 'rag' is not enabled for your organization.", $exception->getMessage());
        $this->assertSame(403, $exception->getCode());
        $this->assertSame('rag', $exception->moduleIdentifier);
        $this->assertSame('rag', $exception->getModuleIdentifier());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
