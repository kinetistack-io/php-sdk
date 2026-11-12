<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\RateLimitExceededException;
use KinetiStack\Sdk\Exception\ServiceModuleDisabledException;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\KinetiClient;
use KinetiStack\Sdk\Transport\Psr18Transport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamInterface;

class Psr18TransportTest extends TestCase
{
    public function testSuccessfulGetRequest(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);
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

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);
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

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);
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
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        try {
            $transport->request('POST', '/v1/test');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Validation Failed', $e->getMessage());
            $this->assertStringContainsString('image_url: Invalid URL', $e->getMessage());
            $this->assertCount(1, $e->violations);
            $this->assertSame('image_url', $e->violations[0]['propertyPath']);
        }
    }

    public function testRateLimitExceededExceptionWithRetryAfter(): void
    {
        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '60',
            ], '{"title": "Too Many Requests"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('Too Many Requests', $e->getMessage());
            $this->assertSame(60, $e->retryAfter);
            $this->assertSame(60, $e->getRetryAfter());
        }
    }

    public function testRateLimitExceededExceptionWithAllRateLimitHeaders(): void
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
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
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
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $transport->request('GET', '/v1/data');
        $this->assertNull($transport->getLastRateLimitInfo());
    }

    /**
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    #[DataProvider('errorStatusCodeProvider')]
    public function testErrorStatusCodes(int $statusCode, string $expectedExceptionClass): void
    {
        $mock = new MockHandler([
            new Response($statusCode, ['Content-Type' => 'application/problem+json'], '{"title": "Error"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
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

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 2,
            'pause_handler' => $pauseHandler,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'recovered'], $response->toArray());
        $this->assertCount(1, $pausedDurations);
        $this->assertGreaterThanOrEqual(1.0, $pausedDurations[0]);
        $this->assertLessThanOrEqual(1.25, $pausedDurations[0]);
    }

    public function testExhaustedRetriesThrowsException(): void
    {
        $mock = new MockHandler([
            new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}'),
            new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function (): void {
            },
        ]);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Service Unavailable');

        $transport->request('GET', '/v1/test');
    }

    public function testNonSeekableBodyStreamIsBufferedAndRetriedSuccessfully(): void
    {
        $capturedBodies = [];
        $mock = new MockHandler([
            function ($request) use (&$capturedBodies) {
                $capturedBodies[] = (string) $request->getBody();

                return new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}');
            },
            function ($request) use (&$capturedBodies) {
                $capturedBodies[] = (string) $request->getBody();

                return new Response(200, ['Content-Type' => 'application/json'], '{"data": "success"}');
            },
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function (): void {
            },
        ]);

        $rawStream = Utils::streamFor('non-seekable-payload-data');
        $nonSeekableStream = new NoSeekStream($rawStream);
        $this->assertFalse($nonSeekableStream->isSeekable());

        $response = $transport->request('POST', '/v1/test', [
            'body' => $nonSeekableStream,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'success'], $response->toArray());
        $this->assertCount(2, $capturedBodies);
        $this->assertSame('non-seekable-payload-data', $capturedBodies[0]);
        $this->assertSame('non-seekable-payload-data', $capturedBodies[1]);
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

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => $pauseHandler,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $pausedDurations);
        $this->assertEqualsWithDelta(30.0, $pausedDurations[0], 2.0);
    }

    public function testCallableGeneratorBodyWithRetries(): void
    {
        $mock = new MockHandler([
            new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function (): void {
            },
        ]);

        $bodyFactory = function () {
            return (function () {
                yield 'streamed-';
                yield 'payload';
            })();
        };

        $response = $transport->request('POST', '/v1/upload', [
            'body' => $bodyFactory,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'success'], $response->toArray());
    }

    public function testAnalyzeImageStreamRetryWithPsr18(): void
    {
        $jobPayload = json_encode([
            'job_id' => 'psr18-retry-job-456',
            'status' => 'pending',
            'poll_url' => '/api/v1/jobs/psr18-retry-job-456',
        ], JSON_THROW_ON_ERROR);

        $mock = new MockHandler([
            new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}'),
            new Response(202, ['Content-Type' => 'application/json'], $jobPayload),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function (): void {
            },
        ]);

        $kineti = new KinetiClient('https://api.test', 'test-key', $transport);

        $stream = fopen('php://temp', 'w+b');
        $this->assertIsResource($stream);
        fwrite($stream, 'binary-image-data-psr18');
        rewind($stream);

        $job = $kineti->analyzeImageStream($stream, 'photo.jpg');

        $this->assertInstanceOf(JobDto::class, $job);
        $this->assertSame('psr18-retry-job-456', $job->jobId);
        $this->assertSame('/api/v1/jobs/psr18-retry-job-456', $job->pollUrl);

        fclose($stream);
    }

    public function testStringBodyMatchingFunctionNameIsNotExecutedAsCallable(): void
    {
        $capturedBody = null;
        $mock = new MockHandler([
            function ($request) use (&$capturedBody) {
                $capturedBody = (string) $request->getBody();

                return new Response(200, ['Content-Type' => 'application/json'], '{"ok": true}');
            },
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $response = $transport->request('POST', '/v1/test', [
            'body' => 'date',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('date', $capturedBody);
    }

    public function testRetryDelayCappedAtDefaultMaxRetryDelay(): void
    {
        $pausedDurations = [];
        $pauseHandler = function (float $duration) use (&$pausedDurations): void {
            $pausedDurations[] = $duration;
        };

        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '3600',
            ], '{"title": "Too Many Requests"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "ok"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => $pauseHandler,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $pausedDurations);
        $this->assertSame(60.0, $pausedDurations[0]);
    }

    public function testRetryDelayRespectsCustomMaxRetryDelay(): void
    {
        $pausedDurations = [];
        $pauseHandler = function (float $duration) use (&$pausedDurations): void {
            $pausedDurations[] = $duration;
        };

        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '3600',
            ], '{"title": "Too Many Requests"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "ok"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'max_retry_delay' => 15.0,
            'pause_handler' => $pauseHandler,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $pausedDurations);
        $this->assertSame(15.0, $pausedDurations[0]);
    }

    public function testExponentialBackoffIncludesJitterAndCapsAtMaxRetryDelay(): void
    {
        $pausedDurations = [];
        $pauseHandler = function (float $duration) use (&$pausedDurations): void {
            $pausedDurations[] = $duration;
        };

        $mock = new MockHandler([
            new Response(503, ['Content-Type' => 'application/problem+json']),
            new Response(503, ['Content-Type' => 'application/problem+json']),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 2,
            'pause_handler' => $pauseHandler,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $pausedDurations);
        // Attempt 1: 1.0 + [0.0, 0.25]
        $this->assertGreaterThanOrEqual(1.0, $pausedDurations[0]);
        $this->assertLessThanOrEqual(1.25, $pausedDurations[0]);
        // Attempt 2: 2.0 + [0.0, 0.25]
        $this->assertGreaterThanOrEqual(2.0, $pausedDurations[1]);
        $this->assertLessThanOrEqual(2.25, $pausedDurations[1]);
    }

    public function testThrowingTraversableCleansUpTemporaryResourceWithoutLeaking(): void
    {
        $mock = new MockHandler([]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $throwingTraversable = (function () {
            yield 'initial-data';
            throw new \RuntimeException('Stream generation failure');
        })();

        $openResourcesBefore = count(get_resources('stream'));

        try {
            $transport->request('POST', '/v1/upload', [
                'body' => $throwingTraversable,
            ]);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Stream generation failure', $e->getMessage());
        }

        $openResourcesAfter = count(get_resources('stream'));
        $this->assertSame($openResourcesBefore, $openResourcesAfter);
    }

    public function testThrowingTraversableOnRetryCleansUpTemporaryResourceWithoutLeaking(): void
    {
        $mock = new MockHandler([
            new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function (): void {
            },
        ]);

        $callCount = 0;
        $bodyFactory = function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                $nonSeekable = $this->createMock(StreamInterface::class);
                $nonSeekable->method('isSeekable')->willReturn(false);
                $nonSeekable->method('eof')->willReturn(true);
                $nonSeekable->method('read')->willReturn('');

                return $nonSeekable;
            }

            return (function () {
                yield 'second-attempt-data';
                throw new \RuntimeException('Retry stream creation failure');
            })();
        };

        $openResourcesBefore = count(get_resources('stream'));

        try {
            $transport->request('POST', '/v1/upload', [
                'body' => $bodyFactory,
            ]);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Retry stream creation failure', $e->getMessage());
        }

        $openResourcesAfter = count(get_resources('stream'));
        $this->assertSame($openResourcesBefore, $openResourcesAfter);
    }

    public function testUnsupportedChunkInTraversableThrowsTransportExceptionAndCleansUp(): void
    {
        $mock = new MockHandler([]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $invalidTraversable = (function () {
            yield 'valid-header';
            yield ['invalid-array-chunk'];
        })();

        $openResourcesBefore = count(get_resources('stream'));

        try {
            $transport->request('POST', '/v1/upload', [
                'body' => $invalidTraversable,
            ]);
            $this->fail('Expected TransportException was not thrown.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('Expected stream chunk to be scalar or \Stringable, got "array"', $e->getMessage());
        }

        $openResourcesAfter = count(get_resources('stream'));
        $this->assertSame($openResourcesBefore, $openResourcesAfter);
    }

    public function testNegativeMaxRetryDelayClampedToZero(): void
    {
        $pausedDurations = [];
        $pauseHandler = function (float $duration) use (&$pausedDurations): void {
            $pausedDurations[] = $duration;
        };

        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '10',
            ], '{"title": "Too Many Requests"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "ok"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'max_retry_delay' => -5.0,
            'pause_handler' => $pauseHandler,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $pausedDurations);
        $this->assertSame(0.0, $pausedDurations[0]);
    }

    public function testToArrayReturnsEmptyArrayOnEmptyResponseBody(): void
    {
        $mock = new MockHandler([
            new Response(204, ['Content-Type' => 'application/json'], ''),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $response = $transport->request('DELETE', '/v1/resource/123');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame([], $response->toArray());
    }

    public function testToArrayReturnsEmptyArrayOnWhitespaceOnlyResponseBody(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], "   \r\n   "),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $response = $transport->request('GET', '/v1/empty');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->toArray());
    }

    public function testTransientNetworkExceptionRetriesAndSucceeds(): void
    {
        $networkException = new class ('Connection timed out') extends \Exception implements ClientExceptionInterface {
        };

        $mock = new MockHandler([
            $networkException,
            new Response(200, ['Content-Type' => 'application/json'], '{"data": "recovered_after_network_error"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $paused = false;
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function (float $duration) use (&$paused): void {
                $paused = true;
                $this->assertGreaterThan(0.0, $duration);
            },
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'recovered_after_network_error'], $response->toArray());
        $this->assertTrue($paused);
    }

    public function testExhaustedNetworkRetriesThrowsTransportException(): void
    {
        $networkException1 = new class ('Connection timed out') extends \Exception implements ClientExceptionInterface {
        };
        $networkException2 = new class ('Connection reset by peer') extends \Exception implements ClientExceptionInterface {
        };

        $mock = new MockHandler([
            $networkException1,
            $networkException2,
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $pauseCount = 0;
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function () use (&$pauseCount): void {
                $pauseCount++;
            },
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Connection reset by peer');

        try {
            $transport->request('GET', '/v1/test');
        } finally {
            $this->assertSame(1, $pauseCount);
        }
    }

    public function testCallableBodyFactoryReEvaluatedOnEachAttempt(): void
    {
        $capturedBodies = [];
        $mock = new MockHandler([
            function ($request) use (&$capturedBodies) {
                $capturedBodies[] = (string) $request->getBody();

                return new Response(503, ['Content-Type' => 'application/problem+json'], '{"title": "Service Unavailable"}');
            },
            function ($request) use (&$capturedBodies) {
                $capturedBodies[] = (string) $request->getBody();

                return new Response(200, ['Content-Type' => 'application/json'], '{"data": "success"}');
            },
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
            'pause_handler' => function (): void {
            },
        ]);

        $factoryCallCount = 0;
        $bodyFactory = function () use (&$factoryCallCount) {
            $factoryCallCount++;

            return 'attempt-' . $factoryCallCount;
        };

        $response = $transport->request('POST', '/v1/test', [
            'body' => $bodyFactory,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'success'], $response->toArray());
        $this->assertSame(2, $factoryCallCount);
        $this->assertCount(2, $capturedBodies);
        $this->assertSame('attempt-1', $capturedBodies[0]);
        $this->assertSame('attempt-2', $capturedBodies[1]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidApiHostProvider(): array
    {
        return [
            'empty host' => [''],
            'ftp scheme' => ['ftp://api.test'],
            'no host' => ['http://'],
            'not a url' => ['not-a-url'],
        ];
    }

    #[DataProvider('invalidApiHostProvider')]
    public function testInvalidApiHostThrowsException(string $invalidHost): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid API host URL. Must be an absolute HTTP or HTTPS URL.');

        new Psr18Transport($invalidHost, 'X-Kineti-Key', 'test-key');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidApiHostComponentsProvider(): array
    {
        return [
            'query' => ['https://api.test?foo=bar'],
            'fragment' => ['https://api.test#fragment'],
            'user and pass' => ['https://user:pass@api.test'],
            'user only' => ['https://user@api.test'],
            'subpath' => ['https://api.test/subpath'],
        ];
    }

    #[DataProvider('invalidApiHostComponentsProvider')]
    public function testApiHostWithDisallowedComponentsThrowsException(string $invalidHost): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid API host URL: ');

        new Psr18Transport($invalidHost, 'X-Kineti-Key', 'test-key');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPathProvider(): array
    {
        return [
            'no leading slash' => ['api/v1/resource'],
            'double leading slash' => ['//api/v1/resource'],
            'traversal middle' => ['/api/v1/../secret'],
            'traversal end' => ['/api/v1/..'],
            'traversal only' => ['/../'],
            'encoded traversal middle lowercase' => ['/api/v1/%2e%2e/secret'],
            'encoded traversal middle uppercase' => ['/api/v1/%2E%2E/secret'],
            'encoded traversal end lowercase' => ['/api/v1/%2e%2e'],
            'encoded traversal end uppercase' => ['/api/v1/%2E%2E'],
            'encoded slash leading' => ['/%2fsecret'],
            'relative dot-dot' => ['..'],
            'relative dot-dot slash' => ['../'],
            'relative dot-dot backslash' => ['..\\'],
            'double encoded traversal' => ['/%252e%252e/'],
            'triple encoded traversal' => ['/%25252e%25252e/'],
            'quadruple encoded traversal' => ['/%2525252e%2525252e/'],
            'quadruple encoded backslash' => ['/api/%2525255c/resource'],
            'null byte' => ["\0"],
            'carriage return' => ["\r"],
            'newline' => ["\n"],
        ];
    }

    #[DataProvider('invalidPathProvider')]
    public function testInvalidPathThrowsException(string $invalidPath): void
    {
        $mock = new MockHandler([]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request path. Must be a relative path starting with a single forward slash and cannot contain traversal segments.');

        $transport->request('GET', $invalidPath);
    }

    public function testValidPathWithQueryStringContainingDotsDoesNotThrow(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $response = $transport->request('GET', '/api/v1/search?query=/../test');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDebugInfoRedactsSensitiveHeadersInDefaultOptions(): void
    {
        $mock = new MockHandler([]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport(
            'https://api.test',
            'Authorization',
            'Bearer token123',
            $client,
            [
                'auth' => 'secret-auth',
                'auth_bearer' => 'secret-bearer',
                'auth_basic' => ['admin', 'pass'],
                'proxy' => 'http://user:pass@proxy.test:8080',
                'headers' => [
                    'Authorization' => 'Bearer secret-bearer',
                    'x-kineti-key' => 'secret-kineti-key',
                    'Proxy-Authorization' => 'Basic proxy-secret',
                    'Cookie' => 'session=123',
                    'Set-Cookie' => 'remember=abc',
                    'X-Api-Key' => 'key1',
                    'api-key' => 'key2',
                    'Token' => 'tok',
                    'X-Custom-Header' => 'public-value',
                ],
            ]
        );

        $debugInfo = $transport->__debugInfo();

        $this->assertSame(Client::class, $debugInfo['client']);
        $this->assertNotEmpty($debugInfo['requestFactory']);
        $this->assertNotEmpty($debugInfo['streamFactory']);
        $this->assertIsArray($debugInfo['defaultOptions']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['auth']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['auth_bearer']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['auth_basic']);
        $this->assertSame('http://[REDACTED]:[REDACTED]@proxy.test:8080', $debugInfo['defaultOptions']['proxy']);
        $this->assertIsArray($debugInfo['defaultOptions']['headers']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['Authorization']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['x-kineti-key']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['Proxy-Authorization']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['Cookie']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['Set-Cookie']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['X-Api-Key']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['api-key']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['Token']);
        $this->assertSame('public-value', $debugInfo['defaultOptions']['headers']['X-Custom-Header']);
        $this->assertSame('[REDACTED]', $debugInfo['authHeaderValue']);
    }

    public function testDebugInfoRedactsColonDelimitedStringHeaders(): void
    {
        $mock = new MockHandler([]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $transport = new Psr18Transport(
            'https://api.test',
            'Authorization',
            'Bearer token123',
            $client,
            [
                'headers' => [
                    'Authorization: Bearer secret-bearer',
                    'x-kineti-key: secret-kineti-key',
                    'Proxy-Authorization: Basic proxy-secret',
                    'Cookie: session=123',
                    'Set-Cookie: remember=abc',
                    'X-Api-Key: key1',
                    'api-key: key2',
                    'Token: tok',
                    'X-Custom-Header: public-value',
                ],
            ]
        );

        $debugInfo = $transport->__debugInfo();

        $this->assertIsArray($debugInfo['defaultOptions']);
        $this->assertIsArray($debugInfo['defaultOptions']['headers']);
        $this->assertSame('Authorization: [REDACTED]', $debugInfo['defaultOptions']['headers'][0]);
        $this->assertSame('x-kineti-key: [REDACTED]', $debugInfo['defaultOptions']['headers'][1]);
        $this->assertSame('Proxy-Authorization: [REDACTED]', $debugInfo['defaultOptions']['headers'][2]);
        $this->assertSame('Cookie: [REDACTED]', $debugInfo['defaultOptions']['headers'][3]);
        $this->assertSame('Set-Cookie: [REDACTED]', $debugInfo['defaultOptions']['headers'][4]);
        $this->assertSame('X-Api-Key: [REDACTED]', $debugInfo['defaultOptions']['headers'][5]);
        $this->assertSame('api-key: [REDACTED]', $debugInfo['defaultOptions']['headers'][6]);
        $this->assertSame('Token: [REDACTED]', $debugInfo['defaultOptions']['headers'][7]);
        $this->assertSame('X-Custom-Header: public-value', $debugInfo['defaultOptions']['headers'][8]);
    }

    public function testForwardingTlsVerificationAndTimeoutOptionsToGuzzleClientInterface(): void
    {
        /** @var array<string, mixed>|null $receivedOptions */
        $receivedOptions = null;

        $mock = new MockHandler([
            function ($request, $options) use (&$receivedOptions) {
                $receivedOptions = $options;
                return new Response(200, ['Content-Type' => 'application/json'], '{"ok": true}');
            },
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport(
            'https://api.test',
            'X-Kineti-Key',
            'test-key',
            $client,
            [
                'verify_peer' => false,
                'verify_host' => false,
                'timeout' => 15.0,
            ]
        );

        $response = $transport->request('GET', '/v1/test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($receivedOptions);
        $this->assertFalse($receivedOptions['verify']);
        $this->assertSame(15.0, $receivedOptions['timeout']);
        $this->assertFalse($receivedOptions['http_errors']);
    }

    public function testGuzzleOptionForwardingOverridesFromPerRequestOptions(): void
    {
        /** @var array<string, mixed>|null $receivedOptions */
        $receivedOptions = null;

        $mock = new MockHandler([
            function ($request, $options) use (&$receivedOptions) {
                $receivedOptions = $options;
                return new Response(200, ['Content-Type' => 'application/json'], '{"ok": true}');
            },
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport(
            'https://api.test',
            'X-Kineti-Key',
            'test-key',
            $client,
            ['timeout' => 10.0]
        );

        $transport->request('POST', '/v1/test', [
            'timeout' => 45.0,
            'verify' => '/path/to/cacert.pem',
        ]);

        $this->assertNotNull($receivedOptions);
        $this->assertSame(45.0, $receivedOptions['timeout']);
        $this->assertSame('/path/to/cacert.pem', $receivedOptions['verify']);
        $this->assertFalse($receivedOptions['http_errors']);
    }

    public function testPreservingPhpunitMockObjectCompatibilityInRequestStream(): void
    {
        $mockClient = $this->createMock(\GuzzleHttp\ClientInterface::class);
        $responseStream = Utils::streamFor("data: {\"chunk\": \"hello\"}\n\n");
        $mockResponse = new Response(200, ['Content-Type' => 'text/event-stream'], $responseStream);

        $mockClient->expects($this->once())
            ->method('send')
            ->willReturn($mockResponse);

        $transport = new Psr18Transport('https://api.kinetistack.com', 'X-Kineti-Key', 'test-key', $mockClient);
        $response = $transport->requestStream('GET', '/v1/stream');

        $this->assertSame(200, $response->getStatusCode());
        $chunks = iterator_to_array($response->getStreamIterator());
        $this->assertCount(1, $chunks);
        $this->assertSame("data: {\"chunk\": \"hello\"}\n\n", $chunks[0]);
    }

    public function testRequestStreamWithCurlExplicitlyDisabled(): void
    {
        /** @var array<string, mixed>|null $receivedOptions */
        $receivedOptions = null;

        $mock = new MockHandler([
            function ($request, $options) use (&$receivedOptions) {
                $receivedOptions = $options;
                return new Response(200, ['Content-Type' => 'text/event-stream'], "data: test\n\n");
            },
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport(
            'https://api.kinetistack.com',
            'X-Kineti-Key',
            'test-key',
            $client,
            ['curl_stream' => false]
        );

        $response = $transport->requestStream('GET', '/v1/stream');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($receivedOptions);
        $this->assertTrue($receivedOptions['stream'] ?? false);
    }

    public function testHttp403ForbiddenInRequestStreamThrowsAuthorizationException(): void
    {
        $errorBody = json_encode(['detail' => 'Forbidden stream access'], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([
            new Response(403, ['Content-Type' => 'application/problem+json'], $errorBody),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $this->expectException(\KinetiStack\Sdk\Exception\AuthorizationException::class);
        $this->expectExceptionMessage('Forbidden stream access');

        $transport->requestStream('GET', '/v1/stream');
    }

    public function testHttp429TooManyRequestsInRequestStreamThrowsRateLimitException(): void
    {
        $errorBody = json_encode(['detail' => 'Rate limit exceeded'], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '10',
                'X-RateLimit-Remaining' => '0',
            ], $errorBody),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $transport = new Psr18Transport('https://api.test', 'X-Kineti-Key', 'test-key', $client, ['max_retries' => 0]);

        $this->expectException(\KinetiStack\Sdk\Exception\RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $transport->requestStream('GET', '/v1/stream');
    }

    public function testRequestStreamWithCurlRetryLoopOn429(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                $errorBody = json_encode(['detail' => 'Rate limited'], JSON_THROW_ON_ERROR);
                fwrite($conn, "HTTP/1.1 429 Too Many Requests\r\nContent-Type: application/problem+json\r\nRetry-After: 0\r\nContent-Length: " . strlen($errorBody) . "\r\nConnection: close\r\n\r\n" . $errorBody);
                fclose($conn);
            },
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                $body = "data: stream-chunk\n\n";
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
                fclose($conn);
            },
        ]);

        try {
            $pausedDurations = [];
            $transport = new Psr18Transport(
                'http://' . $address,
                'X-Kineti-Key',
                'test-key',
                null,
                [
                    'curl_stream' => true,
                    'max_retries' => 2,
                    'pause_handler' => function (float $duration) use (&$pausedDurations): void {
                        $pausedDurations[] = $duration;
                    },
                ]
            );

            $response = $transport->requestStream('GET', '/v1/stream');
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame("data: stream-chunk\n\n", $response->getContent());
            $this->assertCount(1, $pausedDurations);
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlNumericIndexedHeaders(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: " . strlen($req) . "\r\n\r\n" . $req);
                fclose($conn);
            },
        ]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);
            $response = $transport->requestStream('GET', '/v1/stream', [
                'headers' => [
                    'X-Standard-Header' => 'standard-value',
                    0 => 'X-Numeric-Header: numeric-value',
                ],
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $echoedHeaders = $response->getContent();
            $this->assertStringContainsString('X-Standard-Header: standard-value', $echoedHeaders);
            $this->assertStringContainsString('X-Numeric-Header: numeric-value', $echoedHeaders);
            $this->assertStringNotContainsString('0: X-Numeric-Header', $echoedHeaders);
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlAcceptHeaderPrecedence(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        $echoAcceptHeader = static function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }
            $accept = '';
            if (preg_match('/^Accept:\s*(.*)$/mi', $req, $matches)) {
                $accept = trim($matches[1]);
            }
            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: " . strlen($accept) . "\r\n\r\n" . $accept);
            fclose($conn);
        };

        [$address, $pid] = $this->startSocketServer([$echoAcceptHeader, $echoAcceptHeader]);

        try {
            // Default options has Accept: application/json
            $transport = new Psr18Transport(
                'http://' . $address,
                'X-Kineti-Key',
                'test-key',
                null,
                [
                    'curl_stream' => true,
                    'headers' => ['Accept' => 'application/json'],
                ]
            );

            // 1. Calling requestStream() without explicit Accept should default to text/event-stream
            $res1 = $transport->requestStream('GET', '/v1/stream');
            $this->assertSame('text/event-stream', $res1->getContent());

            // 2. Calling requestStream() with explicit Accept should preserve the caller's override
            $res2 = $transport->requestStream('GET', '/v1/stream', [
                'headers' => ['Accept' => 'text/custom-event-stream'],
            ]);
            $this->assertSame('text/custom-event-stream', $res2->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlBodyTypes(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        $echoBody = static function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }
            $body = '';
            if (preg_match('/Content-Length:\s*(\d+)/i', $req, $matches)) {
                $length = (int) $matches[1];
                $parts = explode("\r\n\r\n", $req, 2);
                $body = $parts[1] ?? '';
                while (strlen($body) < $length) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $body .= $buf;
                }
            } elseif (preg_match('/Transfer-Encoding:\s*chunked/i', $req)) {
                $parts = explode("\r\n\r\n", $req, 2);
                $raw = $parts[1] ?? '';
                while (!str_contains($raw, "0\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $raw .= $buf;
                }
                $offset = 0;
                while ($offset < strlen($raw)) {
                    $pos = strpos($raw, "\r\n", $offset);
                    if ($pos === false) {
                        break;
                    }
                    $chunkLenHex = substr($raw, $offset, $pos - $offset);
                    $chunkLen = (int) hexdec(trim($chunkLenHex));
                    if ($chunkLen <= 0) {
                        break;
                    }
                    $body .= substr($raw, $pos + 2, $chunkLen);
                    $offset = $pos + 2 + $chunkLen + 2;
                }
            }
            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
            fclose($conn);
        };

        [$address, $pid] = $this->startSocketServer([$echoBody, $echoBody, $echoBody]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);

            // 1. StreamInterface
            $stream = Utils::streamFor('stream-interface-content');
            $res1 = $transport->requestStream('POST', '/v1/stream', ['body' => $stream]);
            $this->assertSame('stream-interface-content', $res1->getContent());

            // 2. Resource
            $res = fopen('php://temp', 'w+b');
            $this->assertIsResource($res);
            fwrite($res, 'resource-content');
            rewind($res);
            $res2 = $transport->requestStream('POST', '/v1/stream', ['body' => $res]);
            $this->assertSame('resource-content', $res2->getContent());
            fclose($res);

            // 3. Traversable
            $generator = (function () {
                yield 'traversable-';
                yield 'chunk';
            })();
            $res3 = $transport->requestStream('POST', '/v1/stream', ['body' => $generator]);
            $this->assertSame('traversable-chunk', $res3->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlCustomCaBundlePath(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: 2\r\n\r\nok");
                fclose($conn);
            },
        ]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);
            $response = $transport->requestStream('GET', '/v1/stream', [
                'verify' => '/path/to/custom-ca.pem',
                'timeout' => 2.5,
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('ok', $response->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlCustomCaBundlePathAndDisabledVerifyHost(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: 4\r\n\r\ndone");
                fclose($conn);
            },
        ]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);
            $response = $transport->requestStream('GET', '/v1/stream', [
                'verify' => '/path/to/custom-ca.pem',
                'verify_host' => false,
                'timeout' => 2.5,
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('done', $response->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlUnsupportedBodyTypeThrows(): void
    {
        $transport = new Psr18Transport('http://127.0.0.1:54321', 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported request body type for streaming request: stdClass');

        $transport->requestStream('POST', '/v1/stream', ['body' => new \stdClass()]);
    }

    public function testGuzzleClientUsedByDefaultOnNonTestHostWithoutCurlBypass(): void
    {
        /** @var array<string, mixed>|null $receivedOptions */
        $receivedOptions = null;
        /** @var \Psr\Http\Message\RequestInterface|null $receivedRequest */
        $receivedRequest = null;

        $mock = new MockHandler([
            function ($request, $options) use (&$receivedRequest, &$receivedOptions) {
                $receivedRequest = $request;
                $receivedOptions = $options;
                return new Response(200, ['Content-Type' => 'text/event-stream'], "data: guzzle-stream\n\n");
            },
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        // Host is production / non-test domain, and curl_stream is omitted
        $transport = new Psr18Transport(
            'https://api.kinetistack.com',
            'X-Kineti-Key',
            'test-key',
            $client
        );

        $response = $transport->requestStream('GET', '/v1/stream');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($receivedOptions);
        $this->assertTrue($receivedOptions['stream'] ?? false);
        $this->assertNotNull($receivedRequest);
        $this->assertSame('text/event-stream', $receivedRequest->getHeaderLine('Accept'));
        $chunks = iterator_to_array($response->getStreamIterator());
        $this->assertSame(["data: guzzle-stream\n\n"], $chunks);
    }

    public function testGenericPsr18ClientWithoutCurlStreamDisabledUsesCurl(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: 4\r\n\r\ncurl");
                fclose($conn);
            },
        ]);

        try {
            // A generic PSR-18 client (not GuzzleHttp\ClientInterface)
            $genericClient = new class () implements ClientInterface {
                public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
                    throw new \RuntimeException('Generic PSR-18 sendRequest should not be called when curl streaming is active.');
                }
            };

            $transport = new Psr18Transport(
                'http://' . $address,
                'X-Kineti-Key',
                'test-key',
                $genericClient
            );

            $response = $transport->requestStream('GET', '/v1/stream');
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('curl', $response->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testGenericPsr18ClientWithCurlStreamFalseUsesPsr18Client(): void
    {
        $mockResponse = new Response(200, ['Content-Type' => 'text/event-stream'], Utils::streamFor("data: psr18\n\n"));

        $genericClient = new class ($mockResponse) implements ClientInterface {
            public bool $called = false;

            public function __construct(
                private readonly \Psr\Http\Message\ResponseInterface $response
            ) {
            }

            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->called = true;
                return $this->response;
            }
        };

        $transport = new Psr18Transport(
            'https://api.kinetistack.com',
            'X-Kineti-Key',
            'test-key',
            $genericClient,
            ['curl_stream' => false]
        );

        $response = $transport->requestStream('GET', '/v1/stream');
        $this->assertTrue($genericClient->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequestStreamWithCurlDoesNotFollowRedirects(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                fwrite($conn, "HTTP/1.1 302 Found\r\nLocation: http://127.0.0.1:59999/redirected\r\nContent-Length: 0\r\n\r\n");
                fclose($conn);
            },
        ]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);
            $response = $transport->requestStream('GET', '/v1/stream');

            $this->assertSame(302, $response->getStatusCode());
            $headers = $response->getHeaders();
            $this->assertArrayHasKey('location', $headers);
            $this->assertSame(['http://127.0.0.1:59999/redirected'], $headers['location']);
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlRejectsInvalidHeaderName(): void
    {
        $transport = new Psr18Transport('http://127.0.0.1:54321', 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header name "Bad Header"');

        $transport->requestStream('GET', '/v1/stream', [
            'headers' => ['Bad Header' => 'value'],
        ]);
    }

    public function testRequestStreamWithCurlRejectsHeaderValueWithCarriageReturn(): void
    {
        $transport = new Psr18Transport('http://127.0.0.1:54321', 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value for "X-Custom"');

        $transport->requestStream('GET', '/v1/stream', [
            'headers' => ['X-Custom' => "bad\rvalue"],
        ]);
    }

    public function testRequestStreamWithCurlRejectsHeaderValueWithNewline(): void
    {
        $transport = new Psr18Transport('http://127.0.0.1:54321', 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value for "X-Custom"');

        $transport->requestStream('GET', '/v1/stream', [
            'headers' => ['X-Custom' => "bad\nvalue"],
        ]);
    }

    public function testRequestStreamWithCurlRejectsHeaderValueWithNullByte(): void
    {
        $transport = new Psr18Transport('http://127.0.0.1:54321', 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value for "X-Custom"');

        $transport->requestStream('GET', '/v1/stream', [
            'headers' => ['X-Custom' => "bad\0value"],
        ]);
    }

    public function testRequestStreamWithCurlRejectsNumericIndexedHeaderWithControlCharacters(): void
    {
        $transport = new Psr18Transport('http://127.0.0.1:54321', 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('contains control characters');

        $transport->requestStream('GET', '/v1/stream', [
            'headers' => [0 => "X-Numeric: value\r\nInjected: bad"],
        ]);
    }

    public function testRequestStreamWithCurlRejectsNumericIndexedHeaderWithLeadingOrTrailingControlCharacters(): void
    {
        $transport = new Psr18Transport('http://127.0.0.1:54321', 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('contains control characters');

        $transport->requestStream('GET', '/v1/stream', [
            'headers' => [0 => "\r\nX-Injected: value"],
        ]);
    }

    public function testRequestStreamWithCurlFormatsValidNumericIndexedHeaderProperly(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: " . strlen($req) . "\r\n\r\n" . $req);
                fclose($conn);
            },
        ]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);
            $response = $transport->requestStream('GET', '/v1/stream', [
                'headers' => [0 => "  X-Custom-Test  :   custom-value   "],
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString("X-Custom-Test: custom-value\r\n", $response->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlSucceedsOnRetryWithArrayIteratorBody(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        $readBody = static function ($conn, string $req): string {
            $body = '';
            if (preg_match('/Transfer-Encoding:\s*chunked/i', $req)) {
                $parts = explode("\r\n\r\n", $req, 2);
                $raw = $parts[1] ?? '';
                while (!str_contains($raw, "0\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $raw .= $buf;
                }
                $offset = 0;
                while ($offset < strlen($raw)) {
                    $pos = strpos($raw, "\r\n", $offset);
                    if ($pos === false) {
                        break;
                    }
                    $chunkLenHex = substr($raw, $offset, $pos - $offset);
                    $chunkLen = (int) hexdec(trim($chunkLenHex));
                    if ($chunkLen <= 0) {
                        break;
                    }
                    $body .= substr($raw, $pos + 2, $chunkLen);
                    $offset = $pos + 2 + $chunkLen + 2;
                }
            } elseif (preg_match('/Content-Length:\s*(\d+)/i', $req, $matches)) {
                $len = (int) $matches[1];
                $parts = explode("\r\n\r\n", $req, 2);
                $body = $parts[1] ?? '';
                while (strlen($body) < $len) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $body .= $buf;
                }
            }

            return $body;
        };

        $attempt1File = tempnam(sys_get_temp_dir(), 'test_body_att1_');
        $this->assertIsString($attempt1File);

        [$address, $pid] = $this->startSocketServer([
            function ($conn) use ($attempt1File, $readBody): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                $body = $readBody($conn, $req);
                file_put_contents($attempt1File, $body);
                fwrite($conn, "HTTP/1.1 503 Service Unavailable\r\nRetry-After: 0\r\nContent-Length: 0\r\n\r\n");
                fclose($conn);
            },
            function ($conn) use ($readBody): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                $body = $readBody($conn, $req);
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
                fclose($conn);
            },
        ]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);
            $body = new \ArrayIterator(['chunk1', 'chunk2']);

            $response = $transport->requestStream('POST', '/v1/stream', [
                'body' => $body,
                'max_retries' => 1,
                'pause_handler' => function (): void {
                },
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('chunk1chunk2', $response->getContent());
            $this->assertSame('chunk1chunk2', file_get_contents($attempt1File));
        } finally {
            $this->waitForChildProcess($pid);
            if (file_exists($attempt1File)) {
                unlink($attempt1File);
            }
        }
    }

    public function testRequestStreamWithCurlFailsOnRetryWithGeneratorBody(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                fwrite($conn, "HTTP/1.1 503 Service Unavailable\r\nRetry-After: 0\r\nContent-Length: 0\r\n\r\n");
                fclose($conn);
            },
        ]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);
            $generator = (function () {
                yield 'chunk1';
                yield 'chunk2';
            })();

            $this->expectException(TransportException::class);
            $this->expectExceptionMessage('Cannot retry request with non-rewindable Traversable body.');

            $transport->requestStream('POST', '/v1/stream', [
                'body' => $generator,
                'max_retries' => 1,
                'pause_handler' => function (): void {
                },
            ]);
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlFailsOnRetryWithThrowingRewindIterator(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                fwrite($conn, "HTTP/1.1 503 Service Unavailable\r\nRetry-After: 0\r\nContent-Length: 0\r\n\r\n");
                fclose($conn);
            },
        ]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);
            $throwingIterator = new class () implements \Iterator {
                private int $pos = 0;
                public function current(): mixed
                {
                    return 'data';
                }
                public function key(): mixed
                {
                    return $this->pos;
                }
                public function next(): void
                {
                    $this->pos++;
                }
                public function valid(): bool
                {
                    return $this->pos === 0;
                }
                public function rewind(): void
                {
                    if ($this->pos > 0) {
                        throw new \RuntimeException('Cannot rewind');
                    }
                }
            };

            $this->expectException(TransportException::class);
            $this->expectExceptionMessage('Cannot retry request with non-rewindable Traversable body.');

            $transport->requestStream('POST', '/v1/stream', [
                'body' => $throwingIterator,
                'max_retries' => 1,
                'pause_handler' => function (): void {
                },
            ]);
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRequestStreamWithCurlSetsCaPathWhenVerifyIsDirectory(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer([
            function ($conn): void {
                $req = '';
                while (!str_contains($req, "\r\n\r\n")) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $req .= $buf;
                }
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nContent-Length: 2\r\n\r\nok");
                fclose($conn);
            },
        ]);

        try {
            $transport = new Psr18Transport('http://' . $address, 'X-Kineti-Key', 'test-key', null, ['curl_stream' => true]);
            $response = $transport->requestStream('GET', '/v1/stream', [
                'verify' => sys_get_temp_dir(),
                'timeout' => 2.5,
            ]);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('ok', $response->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    /**
     * Start a temporary local TCP socket server in a child process handling sequential requests.
     *
     * @param list<callable(resource): void> $handlers
     * @return array{0: string, 1: int}
     */
    private function startSocketServer(array $handlers): array
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl_fork is required for local socket server tests.');
        }

        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            self::fail('Failed to start TCP socket server: ' . $errstr);
        }

        $address = stream_socket_get_name($server, false);
        if ($address === false) {
            fclose($server);
            self::fail('Failed to retrieve socket address.');
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($server);
            self::fail('Failed to fork child process.');
        }

        if ($pid === 0) {
            try {
                foreach ($handlers as $handler) {
                    $conn = stream_socket_accept($server, 5);
                    if ($conn !== false) {
                        $handler($conn);
                    }
                }
            } finally {
                fclose($server);
                if (function_exists('posix_kill')) {
                    $childPid = getmypid();
                    if ($childPid !== false) {
                        posix_kill($childPid, SIGKILL);
                    }
                }
                exit(0);
            }
        }

        fclose($server);

        return [$address, $pid];
    }

    private function waitForChildProcess(int $pid): void
    {
        if (function_exists('pcntl_waitpid')) {
            $status = 0;
            pcntl_waitpid($pid, $status);
        }
    }
}
