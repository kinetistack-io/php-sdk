<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\Dto\ModuleDto;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\KinetiClient;
use KinetiStack\Sdk\ModuleClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ModuleClientTest extends TestCase
{
    private function createSampleModulesJson(): string
    {
        return json_encode([
            'modules' => [
                [
                    'identifier' => 'vision',
                    'label' => 'Image Analysis & Alt-Text',
                    'status' => 'enabled',
                    'accessible' => true,
                ],
                [
                    'identifier' => 'rag',
                    'label' => 'Retrieval-Augmented Generation',
                    'status' => 'disabled',
                    'accessible' => false,
                ],
                [
                    'identifier' => 'experimental-chat',
                    'label' => 'Experimental Chat',
                    'status' => 'experimental',
                    'accessible' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function testList(): void
    {
        $mockResponse = new MockResponse($this->createSampleModulesJson());
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $moduleClient = $kineti->modules();
        $this->assertInstanceOf(ModuleClient::class, $moduleClient);

        $modules = $moduleClient->list();

        $this->assertCount(3, $modules);
        $this->assertContainsOnlyInstancesOf(ModuleDto::class, $modules);

        $this->assertSame('vision', $modules[0]->identifier);
        $this->assertSame('Image Analysis & Alt-Text', $modules[0]->label);
        $this->assertSame('enabled', $modules[0]->status);
        $this->assertTrue($modules[0]->accessible);

        $this->assertSame('rag', $modules[1]->identifier);
        $this->assertSame('disabled', $modules[1]->status);
        $this->assertFalse($modules[1]->accessible);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/modules', $mockResponse->getRequestUrl());
    }

    public function testGetAll(): void
    {
        $mockResponse = new MockResponse($this->createSampleModulesJson());
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $modules = $kineti->modules()->getAll();

        $this->assertCount(3, $modules);
        $this->assertSame('vision', $modules[0]->identifier);
        $this->assertSame('rag', $modules[1]->identifier);
    }

    public function testGet(): void
    {
        $client = new MockHttpClient(fn () => new MockResponse($this->createSampleModulesJson()));
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $vision = $kineti->modules()->get('vision');
        $this->assertNotNull($vision);
        $this->assertSame('vision', $vision->identifier);
        $this->assertTrue($vision->isAccessible());

        $nonExistent = $kineti->modules()->get('unknown-module');
        $this->assertNull($nonExistent);
    }

    public function testIsEnabled(): void
    {
        $client = new MockHttpClient(fn () => new MockResponse($this->createSampleModulesJson()));
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $moduleClient = $kineti->modules();

        $this->assertTrue($moduleClient->isEnabled('vision'));
        $this->assertFalse($moduleClient->isEnabled('rag'));
        $this->assertTrue($moduleClient->isEnabled('experimental-chat'));
        $this->assertFalse($moduleClient->isEnabled('non-existent'));
    }

    public function testIsAccessible(): void
    {
        $client = new MockHttpClient(fn () => new MockResponse($this->createSampleModulesJson()));
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $moduleClient = $kineti->modules();

        $this->assertTrue($moduleClient->isAccessible('vision'));
        $this->assertFalse($moduleClient->isAccessible('rag'));
        $this->assertFalse($moduleClient->isAccessible('unknown-module'));
    }

    public function testMemoizationAcrossCalls(): void
    {
        $requestCount = 0;
        $client = new MockHttpClient(function () use (&$requestCount) {
            $requestCount++;

            return new MockResponse($this->createSampleModulesJson());
        });
        $kineti = new KinetiClient('https://api.test', 'key', $client);
        $moduleClient = $kineti->modules();

        $this->assertTrue($moduleClient->isEnabled('vision'));
        $this->assertFalse($moduleClient->isEnabled('rag'));
        $this->assertNotNull($moduleClient->get('vision'));
        $this->assertCount(3, $moduleClient->list());
        $this->assertSame(1, $requestCount, 'Expected only 1 HTTP request across repeated queries due to memoization');

        // Force refresh should trigger a new request
        $this->assertCount(3, $moduleClient->list(true));
        $this->assertSame(2, $requestCount, 'Expected forceRefresh to trigger a second request');

        // clearCache should also trigger a new request on subsequent call
        $moduleClient->clearCache();
        $this->assertTrue($moduleClient->isEnabled('vision'));
        $this->assertSame(3, $requestCount, 'Expected clearCache to trigger a third request');
    }

    public function testAlternativePayloadKey(): void
    {
        $responseBody = json_encode([
            'data' => [
                [
                    'identifier' => 'vision',
                    'label' => 'Vision',
                    'status' => 'enabled',
                    'accessible' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $modules = $kineti->modules()->list();
        $this->assertCount(1, $modules);
        $this->assertSame('vision', $modules[0]->identifier);
    }

    public function testEmptyResponse(): void
    {
        $responseBody = json_encode(['modules' => []], JSON_THROW_ON_ERROR);
        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $modules = $kineti->modules()->list();
        $this->assertSame([], $modules);
    }

    public function testKinetiClientCachesModuleClient(): void
    {
        $client = new MockHttpClient();
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $moduleClient1 = $kineti->modules();
        $moduleClient2 = $kineti->modules();

        $this->assertSame($moduleClient1, $moduleClient2);
        $this->assertSame($kineti->getTransport(), $moduleClient1->getTransport());
    }

    public function testAuthenticationExceptionPropagation(): void
    {
        $mockResponse = new MockResponse(
            json_encode(['detail' => 'Invalid API key'], JSON_THROW_ON_ERROR),
            ['http_code' => 401]
        );
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'invalid-key', $client);

        $this->expectException(AuthenticationException::class);
        $kineti->modules()->list();
    }

    public function testServerExceptionPropagation(): void
    {
        $mockResponse = new MockResponse(
            json_encode(['detail' => 'Internal server error'], JSON_THROW_ON_ERROR),
            ['http_code' => 500]
        );
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $this->expectException(ServerException::class);
        $kineti->modules()->list();
    }
}
