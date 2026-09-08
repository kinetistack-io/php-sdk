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

    public function testSymfonyTransportWithLegacySignature(): void
    {
        $mockResponse = new MockResponse('{"ok": true}');
        $client = new MockHttpClient($mockResponse);

        // Legacy: ($apiHost, $apiKey, $client, $defaultOptions)
        $transport = new SymfonyTransport(
            'https://api.test',
            'legacy-api-key',
            $client
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

    public function testPsr18TransportWithLegacySignature(): void
    {
        /** @var list<array{request: RequestInterface}> $container */
        $container = [];
        $history = Middleware::history($container);

        $mock = new MockHandler([new GuzzleResponse(200, [], '{"ok": true}')]);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $guzzleClient = new Client(['handler' => $stack]);

        // Legacy: ($apiHost, $apiKey, $client)
        $transport = new Psr18Transport(
            'https://api.test',
            'legacy-key-psr18',
            $guzzleClient
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
}
