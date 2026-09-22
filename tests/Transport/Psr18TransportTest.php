<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ServiceModuleDisabledException;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\Transport\Psr18Transport;
use KinetiStack\Sdk\Transport\ResponseErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

class Psr18TransportTest extends TestCase
{
    public function testSuccessfulGetRequest(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'test-key', $client);
        $response = $transport->request('GET', '/v1/test', [
            'query' => ['page' => 2, 'limit' => 10],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'success'], $response->toArray());
        $this->assertSame('{"data": "success"}', $response->getContent());

        $lastRequest = $mock->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertSame('https://api.test/v1/test?page=2&limit=10', (string) $lastRequest->getUri());
        $this->assertSame('test-key', $lastRequest->getHeaderLine('X-Kineti-Key'));
        $this->assertSame('application/json', $lastRequest->getHeaderLine('Accept'));
    }

    public function testSuccessfulPostJsonRequest(): void
    {
        $mock = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], '{"id": "doc-123"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'test-key', $client);
        $response = $transport->request('POST', '/v1/documents', [
            'json' => ['title' => 'Test Document'],
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['id' => 'doc-123'], $response->toArray());

        $lastRequest = $mock->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('POST', $lastRequest->getMethod());
        $this->assertSame('application/json', $lastRequest->getHeaderLine('Content-Type'));
        $this->assertSame('{"title":"Test Document"}', (string) $lastRequest->getBody());
    }

    public function testExplicitAuthorizationHeaderPreserved(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"status": "ok"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'test-key', $client);
        $transport->request('GET', '/v1/me', [
            'headers' => ['Authorization' => 'Bearer custom-token'],
        ]);

        $lastRequest = $mock->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('Bearer custom-token', $lastRequest->getHeaderLine('Authorization'));
        $this->assertFalse($lastRequest->hasHeader('X-Kineti-Key'));
    }

    public function testBodyPayloads(): void
    {
        // Test string body
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"ok": true}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"ok": true}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"ok": true}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        // 1. String body
        $transport->request('POST', '/v1/raw', ['body' => 'raw-data']);
        $this->assertSame('raw-data', (string) $mock->getLastRequest()?->getBody());

        // 2. Resource body
        $res = fopen('php://temp', 'w+b');
        $this->assertIsResource($res);
        fwrite($res, 'resource-data');
        rewind($res);
        $transport->request('POST', '/v1/raw', ['body' => $res]);
        $this->assertSame('resource-data', (string) $mock->getLastRequest()?->getBody());
        fclose($res);

        // 3. Traversable/Generator body
        $generator = function () {
            yield 'chunk1-';
            yield 'chunk2';
        };
        $transport->request('POST', '/v1/raw', ['body' => $generator()]);
        $this->assertSame('chunk1-chunk2', (string) $mock->getLastRequest()?->getBody());
    }

    public function testAuthenticationException(): void
    {
        $mock = new MockHandler([
            new Response(401, ['Content-Type' => 'application/problem+json'], '{"title": "Unauthorized"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthorized');

        $transport->request('GET', '/v1/test');
    }

    public function testServiceModuleDisabledException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://kinetistack.io/errors/module-disabled',
            'title' => 'Service Module Disabled',
            'status' => 403,
            'detail' => "Service module 'vision' is not enabled for your organization.",
            'module' => 'vision',
        ], JSON_THROW_ON_ERROR);

        $mock = new MockHandler([
            new Response(403, ['Content-Type' => 'application/problem+json'], $errorBody),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        try {
            $transport->request('POST', '/v1/images/analyze');
            $this->fail('Expected ServiceModuleDisabledException');
        } catch (ServiceModuleDisabledException $e) {
            $this->assertSame("Service module 'vision' is not enabled for your organization.", $e->getMessage());
            $this->assertSame('vision', $e->moduleIdentifier);
            $this->assertSame('vision', $e->getModuleIdentifier());
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testValidationException(): void
    {
        $errorBody = json_encode([
            'title' => 'Validation Failed',
            'violations' => [
                ['propertyPath' => 'image_url', 'message' => 'Invalid URL'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mock = new MockHandler([
            new Response(422, ['Content-Type' => 'application/problem+json'], $errorBody),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        try {
            $transport->request('POST', '/v1/test');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('Validation Failed', $e->getMessage());
            $this->assertCount(1, $e->violations);
            $this->assertSame('image_url', $e->violations[0]['propertyPath']);
        }
    }

    public function testRateLimitExceptionWithRetryAfter(): void
    {
        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '60',
            ], '{"title": "Too Many Requests"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame('Too Many Requests', $e->getMessage());
            $this->assertSame(60, $e->retryAfter);
            $this->assertSame(60, $e->getRetryAfter());
        }
    }

    public function testRateLimitExceptionWithAllRateLimitHeaders(): void
    {
        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'X-RateLimit-Limit' => '100',
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => '1726950000',
                'Retry-After' => '30',
            ], '{"title": "Too Many Requests"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame('Too Many Requests', $e->getMessage());
            $this->assertSame(100, $e->limit);
            $this->assertSame(100, $e->getLimit());
            $this->assertSame(0, $e->remaining);
            $this->assertSame(0, $e->getRemaining());
            $this->assertSame(1726950000, $e->reset);
            $this->assertSame(1726950000, $e->getReset());
            $this->assertSame(30, $e->retryAfter);
            $this->assertSame(30, $e->getRetryAfter());

            $info = $transport->getLastRateLimitInfo();
            $this->assertNotNull($info);
            $this->assertSame(100, $info->limit);
            $this->assertSame(0, $info->remaining);
            $this->assertSame(1726950000, $info->reset);
            $this->assertSame(30, $info->retryAfter);
        }
    }

    public function testSuccessfulResponseCapturesRateLimitHeaders(): void
    {
        $mock = new MockHandler([
            new Response(200, [
                'Content-Type' => 'application/json',
                'X-RateLimit-Limit' => '250',
                'X-RateLimit-Remaining' => '240',
                'X-RateLimit-Reset' => '1726958888',
            ], '{"data": "ok"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        $this->assertNull($transport->getLastRateLimitInfo());

        $response = $transport->request('GET', '/v1/data');
        $this->assertSame(200, $response->getStatusCode());

        $info = $transport->getLastRateLimitInfo();
        $this->assertInstanceOf(RateLimitInfoDto::class, $info);
        $this->assertSame(250, $info->limit);
        $this->assertSame(240, $info->remaining);
        $this->assertSame(1726958888, $info->reset);
        $this->assertNull($info->retryAfter);
    }

    public function testSuccessfulResponseWithoutRateLimitHeadersReturnsNull(): void
    {
        $mock = new MockHandler([
            new Response(200, [
                'Content-Type' => 'application/json',
            ], '{"data": "ok"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        $transport->request('GET', '/v1/data');
        $this->assertNull($transport->getLastRateLimitInfo());
    }

    /**
     * @dataProvider errorStatusCodeProvider
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    public function testErrorStatusCodes(int $statusCode, string $expectedExceptionClass): void
    {
        $mock = new MockHandler([
            new Response($statusCode, ['Content-Type' => 'application/problem+json'], '{"title": "Error"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage('Error');

        $transport->request('GET', '/v1/test');
    }

    /**
     * @return array<int, array{0: int, 1: class-string<\Throwable>}>
     */
    public static function errorStatusCodeProvider(): array
    {
        return [
            [403, \KinetiStack\Sdk\Exception\AuthorizationException::class],
            [404, \KinetiStack\Sdk\Exception\NotFoundException::class],
            [413, \KinetiStack\Sdk\Exception\PayloadTooLargeException::class],
            [500, \KinetiStack\Sdk\Exception\ServerException::class],
            [503, \KinetiStack\Sdk\Exception\ServiceUnavailableException::class],
            [418, \KinetiStack\Sdk\Exception\KinetiException::class],
        ];
    }

    public function testDecodingFallbackOnNonJsonError(): void
    {
        $mock = new MockHandler([
            new Response(502, ['Content-Type' => 'text/html'], '<html><body>502 Bad Gateway</body></html>'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        $this->expectException(\KinetiStack\Sdk\Exception\KinetiException::class);
        $this->expectExceptionMessage('API Error 502: <html><body>502 Bad Gateway</body></html>');

        $transport->request('GET', '/v1/test');
    }

    public function testClientExceptionMapsToTransportException(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(
            new class ('Connection timed out') extends \RuntimeException implements ClientExceptionInterface {}
        );

        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Connection timed out');

        $transport->request('GET', '/v1/test');
    }

    public function testRetryOnRateLimitRespectsRetryAfter(): void
    {
        $pausedDurations = [];
        $pauseHandler = function (float $duration) use (&$pausedDurations): void {
            $pausedDurations[] = $duration;
        };

        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '3',
            ], '{"title": "Too Many Requests"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "success_after_retry"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
            'max_retries' => 3,
            'pause_handler' => $pauseHandler,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'success_after_retry'], $response->toArray());
        $this->assertSame(0, $mock->count()); // All mock responses consumed
        $this->assertSame([3.0], $pausedDurations);
    }

    public function testRetryOnServiceUnavailable503(): void
    {
        $pausedDurations = [];
        $pauseHandler = function (float $duration) use (&$pausedDurations): void {
            $pausedDurations[] = $duration;
        };

        $mock = new MockHandler([
            new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "recovered"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
            'max_retries' => 2,
            'pause_handler' => $pauseHandler,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'recovered'], $response->toArray());
        $this->assertCount(1, $pausedDurations);
        $this->assertSame(1.0, $pausedDurations[0]);
    }

    public function testExhaustedRetriesThrowsException(): void
    {
        $mock = new MockHandler([
            new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}'),
            new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function (): void {
            },
        ]);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Service Unavailable');

        $transport->request('GET', '/v1/test');
    }

    public function testRetryThrowsWhenStreamIsNotSeekable(): void
    {
        $mock = new MockHandler([
            new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function (): void {
            },
        ]);

        $nonSeekableStream = $this->createMock(StreamInterface::class);
        $nonSeekableStream->method('isSeekable')->willReturn(false);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Cannot retry request with non-seekable body stream.');

        $transport->request('POST', '/v1/test', [
            'body' => $nonSeekableStream,
        ]);
    }

    public function testRetryWithHttpDateRetryAfter(): void
    {
        $pausedDurations = [];
        $pauseHandler = function (float $duration) use (&$pausedDurations): void {
            $pausedDurations[] = $duration;
        };

        $httpDate = gmdate('D, d M Y H:i:s \G\M\T', time() + 30);

        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => $httpDate,
            ], '{"title": "Too Many Requests"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => $pauseHandler,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $pausedDurations);
        $this->assertEqualsWithDelta(30.0, $pausedDurations[0], 2.0);
    }
}
