<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use KinetiStack\Sdk\Transport\HttpTransport;
use KinetiStack\Sdk\Transport\Psr18Transport;
use KinetiStack\Sdk\Transport\SymfonyTransport;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TransportGeneralizationTest extends TestCase
{
    public function testSymfonyTransportWithCustomAuthHeader(): void
    {
        $mockResponse = new MockResponse('{"ok": true}');
        $client = new MockHttpClient($mockResponse);

        $transport = new SymfonyTransport(
            'https://api.test',
            'Authorization',
            'Bearer jwt-abc-123',
            $client
        );

        $this->assertSame('Authorization', $transport->getAuthHeaderName());
        $this->assertSame('Bearer jwt-abc-123', $transport->getAuthHeaderValue());
        $this->assertSame('Bearer jwt-abc-123', $transport->getApiKey());

        $transport->request('GET', '/test');
        $headers = $mockResponse->getRequestOptions()['headers'];
        $this->assertContains('Authorization: Bearer jwt-abc-123', $headers);
    }

    public function testSymfonyTransportWithClean5ArgConstructor(): void
    {
        $mockResponse = new MockResponse('{"ok": true}');
        $client = new MockHttpClient($mockResponse);

        $transport = new SymfonyTransport(
            'https://api.test',
            'X-Kineti-Key',
            'legacy-api-key',
            $client,
            ['timeout' => 10]
        );

        $this->assertSame('X-Kineti-Key', $transport->getAuthHeaderName());
        $this->assertSame('legacy-api-key', $transport->getAuthHeaderValue());
        $this->assertSame('legacy-api-key', $transport->getApiKey());

        $transport->request('GET', '/test');
        $headers = $mockResponse->getRequestOptions()['headers'];
        $this->assertContains('X-Kineti-Key: legacy-api-key', $headers);
    }

    public function testSymfonyTransportWithEmptyAuthSendsNoAuthHeader(): void
    {
        $mockResponse = new MockResponse('{"ok": true}');
        $client = new MockHttpClient($mockResponse);

        $transport = new SymfonyTransport(
            'https://api.test',
            'Authorization',
            '',
            $client
        );

        $transport->request('GET', '/public');
        $headers = $mockResponse->getRequestOptions()['headers'];
        foreach ($headers as $header) {
            $this->assertStringStartsNotWith('authorization:', strtolower((string) $header));
            $this->assertStringStartsNotWith('x-kineti-key:', strtolower((string) $header));
        }
    }

    public function testPsr18TransportWithCustomAuthHeader(): void
    {
        /** @var list<array{request: RequestInterface}> $container */
        $container = [];
        $history = Middleware::history($container);

        $mock = new MockHandler([new GuzzleResponse(200, [], '{"ok": true}')]);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $guzzleClient = new Client(['handler' => $stack]);

        $transport = new Psr18Transport(
            'https://api.test',
            'Authorization',
            'Bearer jwt-xyz',
            $guzzleClient
        );

        $this->assertSame('Authorization', $transport->getAuthHeaderName());
        $this->assertSame('Bearer jwt-xyz', $transport->getAuthHeaderValue());

        $transport->request('GET', '/test');
        assert(is_array($container));
        $this->assertCount(1, $container);
        $this->assertSame('Bearer jwt-xyz', $container[0]['request']->getHeaderLine('Authorization'));
    }

    public function testPsr18TransportWithClean5ArgConstructor(): void
    {
        /** @var list<array{request: RequestInterface}> $container */
        $container = [];
        $history = Middleware::history($container);

        $mock = new MockHandler([new GuzzleResponse(200, [], '{"ok": true}')]);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $guzzleClient = new Client(['handler' => $stack]);

        $transport = new Psr18Transport(
            'https://api.test',
            'X-Kineti-Key',
            'legacy-key-psr18',
            $guzzleClient,
            ['timeout' => 10]
        );

        $this->assertSame('X-Kineti-Key', $transport->getAuthHeaderName());
        $this->assertSame('legacy-key-psr18', $transport->getAuthHeaderValue());

        $transport->request('GET', '/test');
        assert(is_array($container));
        $this->assertCount(1, $container);
        $this->assertSame('legacy-key-psr18', $container[0]['request']->getHeaderLine('X-Kineti-Key'));
    }

    public function testHttpTransportDelegation(): void
    {
        $mockResponse = new MockResponse('{"ok": true}');
        $client = new MockHttpClient($mockResponse);

        $transport = new HttpTransport(
            'https://api.test',
            'Authorization',
            'Bearer jwt-via-http-transport',
            $client
        );

        $this->assertSame('Authorization', $transport->getAuthHeaderName());
        $this->assertSame('Bearer jwt-via-http-transport', $transport->getAuthHeaderValue());

        $delegate = $transport->getDelegate();
        $this->assertInstanceOf(SymfonyTransport::class, $delegate);
        $this->assertSame('Authorization', $delegate->getAuthHeaderName());
        $this->assertSame('Bearer jwt-via-http-transport', $delegate->getAuthHeaderValue());

        $transport->request('GET', '/test');
        $headers = $mockResponse->getRequestOptions()['headers'];
        $this->assertContains('Authorization: Bearer jwt-via-http-transport', $headers);
    }

    public function testSymfonyTransportWithAuthHeaderValue(): void
    {
        $mockResponse1 = new MockResponse('{"ok": true}');
        $mockResponse2 = new MockResponse('{"ok": true}');
        $client = new MockHttpClient([$mockResponse1, $mockResponse2]);

        $transport = new SymfonyTransport('https://api.test', 'Authorization', 'Bearer initial', $client);
        $updated = $transport->withAuthHeaderValue('Bearer new-token');

        $this->assertNotSame($transport, $updated);
        $this->assertSame('https://api.test', $updated->getApiHost());
        $this->assertSame('Authorization', $updated->getAuthHeaderName());
        $this->assertSame('Bearer initial', $transport->getAuthHeaderValue());
        $this->assertSame('Bearer new-token', $updated->getAuthHeaderValue());

        $transport->request('GET', '/test1');
        $this->assertContains('Authorization: Bearer initial', $mockResponse1->getRequestOptions()['headers']);

        $updated->request('GET', '/test2');
        $this->assertContains('Authorization: Bearer new-token', $mockResponse2->getRequestOptions()['headers']);
    }

    public function testPsr18TransportWithAuthHeaderValue(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new GuzzleResponse(200, [], '{"ok": true}'),
            new GuzzleResponse(200, [], '{"ok": true}'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $guzzleClient = new Client(['handler' => $stack]);

        $transport = new Psr18Transport('https://api.test', 'Authorization', 'Bearer initial', $guzzleClient);
        $updated = $transport->withAuthHeaderValue('Bearer new-token');

        $this->assertNotSame($transport, $updated);
        $this->assertSame('https://api.test', $updated->getApiHost());
        $this->assertSame('Authorization', $updated->getAuthHeaderName());
        $this->assertSame('Bearer initial', $transport->getAuthHeaderValue());
        $this->assertSame('Bearer new-token', $updated->getAuthHeaderValue());

        $transport->request('GET', '/test1');
        $updated->request('GET', '/test2');

        assert(is_array($container));
        $this->assertCount(2, $container);
        $this->assertSame('Bearer initial', $container[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer new-token', $container[1]['request']->getHeaderLine('Authorization'));
    }

    public function testHttpTransportWithAuthHeaderValue(): void
    {
        $mockResponse1 = new MockResponse('{"ok": true}');
        $mockResponse2 = new MockResponse('{"ok": true}');
        $client = new MockHttpClient([$mockResponse1, $mockResponse2]);

        $transport = new HttpTransport('https://api.test', 'Authorization', 'Bearer initial', $client);
        $updated = $transport->withAuthHeaderValue('Bearer new-token');

        $this->assertNotSame($transport, $updated);
        $this->assertSame('https://api.test', $updated->getApiHost());
        $this->assertSame('Authorization', $updated->getAuthHeaderName());
        $this->assertSame('Bearer initial', $transport->getAuthHeaderValue());
        $this->assertSame('Bearer new-token', $updated->getAuthHeaderValue());

        $transport->request('GET', '/test1');
        $this->assertContains('Authorization: Bearer initial', $mockResponse1->getRequestOptions()['headers']);

        $updated->request('GET', '/test2');
        $this->assertContains('Authorization: Bearer new-token', $mockResponse2->getRequestOptions()['headers']);
    }

    public function testTransportsDefaultToEmptyAuthHeaderValue(): void
    {
        $mockResponse = new MockResponse('{"ok": true}');
        $client = new MockHttpClient($mockResponse);

        $http = new HttpTransport('https://api.test', 'Authorization', '', $client);
        $this->assertSame('Authorization', $http->getAuthHeaderName());
        $this->assertSame('', $http->getAuthHeaderValue());

        $httpKey = new HttpTransport('https://api.test', 'X-Kineti-Key', '', $client);
        $this->assertSame('X-Kineti-Key', $httpKey->getAuthHeaderName());
        $this->assertSame('', $httpKey->getAuthHeaderValue());

        $symfony = new SymfonyTransport('https://api.test', 'Authorization', '', $client);
        $this->assertSame('Authorization', $symfony->getAuthHeaderName());
        $this->assertSame('', $symfony->getAuthHeaderValue());

        $psr = new Psr18Transport('https://api.test', 'Authorization');
        $this->assertSame('Authorization', $psr->getAuthHeaderName());
        $this->assertSame('', $psr->getAuthHeaderValue());
    }

    public function testStaticFactoryMethods(): void
    {
        $mockResponse = new MockResponse('{"ok": true}');
        $client = new MockHttpClient($mockResponse);

        $httpApiKey = HttpTransport::withApiKey('https://api.test', 'test-key', $client);
        $this->assertSame('X-Kineti-Key', $httpApiKey->getAuthHeaderName());
        $this->assertSame('test-key', $httpApiKey->getAuthHeaderValue());

        $httpHeader = HttpTransport::withHeader('https://api.test', 'Authorization', 'Bearer jwt', $client);
        $this->assertSame('Authorization', $httpHeader->getAuthHeaderName());
        $this->assertSame('Bearer jwt', $httpHeader->getAuthHeaderValue());

        $httpBearer = HttpTransport::withBearerToken('https://api.test', 'jwt-token', $client);
        $this->assertSame('Authorization', $httpBearer->getAuthHeaderName());
        $this->assertSame('Bearer jwt-token', $httpBearer->getAuthHeaderValue());

        $symfonyApiKey = SymfonyTransport::withApiKey('https://api.test', 'test-key', $client);
        $this->assertSame('X-Kineti-Key', $symfonyApiKey->getAuthHeaderName());
        $this->assertSame('test-key', $symfonyApiKey->getAuthHeaderValue());

        $symfonyHeader = SymfonyTransport::withHeader('https://api.test', 'Authorization', 'Bearer jwt', $client);
        $this->assertSame('Authorization', $symfonyHeader->getAuthHeaderName());
        $this->assertSame('Bearer jwt', $symfonyHeader->getAuthHeaderValue());

        $symfonyBearer = SymfonyTransport::withBearerToken('https://api.test', 'jwt-token', $client);
        $this->assertSame('Authorization', $symfonyBearer->getAuthHeaderName());
        $this->assertSame('Bearer jwt-token', $symfonyBearer->getAuthHeaderValue());

        $psrApiKey = Psr18Transport::withApiKey('https://api.test', 'test-key');
        $this->assertSame('X-Kineti-Key', $psrApiKey->getAuthHeaderName());
        $this->assertSame('test-key', $psrApiKey->getAuthHeaderValue());

        $psrHeader = Psr18Transport::withHeader('https://api.test', 'Authorization', 'Bearer jwt');
        $this->assertSame('Authorization', $psrHeader->getAuthHeaderName());
        $this->assertSame('Bearer jwt', $psrHeader->getAuthHeaderValue());

        $psrBearer = Psr18Transport::withBearerToken('https://api.test', 'jwt-token');
        $this->assertSame('Authorization', $psrBearer->getAuthHeaderName());
        $this->assertSame('Bearer jwt-token', $psrBearer->getAuthHeaderValue());

        $psrBearerPrefixed = Psr18Transport::withBearerToken('https://api.test', 'Bearer already-prefixed');
        $this->assertSame('Bearer already-prefixed', $psrBearerPrefixed->getAuthHeaderValue());
    }

    public function testStaticFactoryMethodsNamedArgumentsWithDefaultOptions(): void
    {
        $mockResponse = new MockResponse('{"ok": true}');
        $client = new MockHttpClient($mockResponse);

        $httpApiKey = HttpTransport::withApiKey(
            apiHost: 'https://api.test',
            apiKey: 'test-key',
            client: $client,
            defaultOptions: ['timeout' => 15]
        );
        $this->assertSame('X-Kineti-Key', $httpApiKey->getAuthHeaderName());
        $this->assertSame('test-key', $httpApiKey->getAuthHeaderValue());

        $httpHeader = HttpTransport::withHeader(
            apiHost: 'https://api.test',
            headerName: 'Authorization',
            headerValue: 'Bearer jwt',
            client: $client,
            defaultOptions: ['timeout' => 15]
        );
        $this->assertSame('Authorization', $httpHeader->getAuthHeaderName());
        $this->assertSame('Bearer jwt', $httpHeader->getAuthHeaderValue());

        $httpBearer = HttpTransport::withBearerToken(
            apiHost: 'https://api.test',
            token: 'jwt-token',
            client: $client,
            defaultOptions: ['timeout' => 15]
        );
        $this->assertSame('Authorization', $httpBearer->getAuthHeaderName());
        $this->assertSame('Bearer jwt-token', $httpBearer->getAuthHeaderValue());
    }
}
