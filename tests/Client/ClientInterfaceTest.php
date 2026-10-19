<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Client;

use KinetiStack\Sdk\Enum\AnalyticsGrouping;
use KinetiStack\Sdk\KinetiClient;
use KinetiStack\Sdk\KinetiClientInterface;
use KinetiStack\Sdk\ModuleClient;
use KinetiStack\Sdk\ModuleClientInterface;
use KinetiStack\Sdk\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class ClientInterfaceTest extends TestCase
{
    public function testInterfacesCanBeMockedInConsumerSuites(): void
    {
        $mockKinetiClient = $this->createMock(KinetiClientInterface::class);
        $mockModuleClient = $this->createMock(ModuleClientInterface::class);

        $mockKinetiClient->method('modules')->willReturn($mockModuleClient);

        $this->assertInstanceOf(KinetiClientInterface::class, $mockKinetiClient);
        $this->assertInstanceOf(ModuleClientInterface::class, $mockModuleClient);
        $this->assertSame($mockModuleClient, $mockKinetiClient->modules());
    }

    public function testInterfaceDefinitions(): void
    {
        $kinetiRef = new \ReflectionClass(KinetiClientInterface::class);
        $moduleRef = new \ReflectionClass(ModuleClientInterface::class);
        $this->assertTrue($kinetiRef->isInterface());
        $this->assertTrue($moduleRef->isInterface());
    }

    public function testClientClassesImplementInterfaces(): void
    {
        $transport = $this->createMock(TransportInterface::class);

        $kinetiClient = new KinetiClient('https://api.test', 'secret-key', $transport);
        $this->assertInstanceOf(KinetiClientInterface::class, $kinetiClient);

        $moduleClient = $kinetiClient->modules();
        $this->assertInstanceOf(ModuleClient::class, $moduleClient);
        $this->assertInstanceOf(ModuleClientInterface::class, $moduleClient);
    }

    public function testAnalyticsGroupingPascalCaseAliases(): void
    {
        $this->assertSame(AnalyticsGrouping::DAY, AnalyticsGrouping::Day);
        $this->assertSame(AnalyticsGrouping::WEEK, AnalyticsGrouping::Week);
        $this->assertSame(AnalyticsGrouping::MONTH, AnalyticsGrouping::Month);

        $this->assertSame('day', AnalyticsGrouping::Day->value);
        $this->assertSame('week', AnalyticsGrouping::Week->value);
        $this->assertSame('month', AnalyticsGrouping::Month->value);
    }

    public function testFacadeModulesReturnTypeNarrowing(): void
    {
        $refMethod = new \ReflectionMethod(KinetiClientInterface::class, 'modules');
        $returnType = $refMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(ModuleClientInterface::class, $returnType->getName());

        $clientRefMethod = new \ReflectionMethod(KinetiClient::class, 'modules');
        $clientReturnType = $clientRefMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $clientReturnType);
        $this->assertSame(ModuleClientInterface::class, $clientReturnType->getName());
    }
}
