<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
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

    public function testRateLimitExceededExceptionWithRetryAfter(): void
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
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

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
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    #[DataProvider('errorStatusCodeProvider')]
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

    public function testCallableGeneratorBodyWithRetries(): void
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

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
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

        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

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

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
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

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
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

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
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
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

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

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
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
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

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

        $transport = new Psr18Transport('https://api.test', 'test-key', $client, [
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
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

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
        $transport = new Psr18Transport('https://api.test', 'test-key', $client);

        $response = $transport->request('GET', '/v1/empty');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->toArray());
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
                'headers' => [
                    'Authorization' => 'Bearer secret-bearer',
                    'x-kineti-key' => 'secret-kineti-key',
                    'Proxy-Authorization' => 'Basic proxy-secret',
                    'X-Custom-Header' => 'public-value',
                ],
            ]
        );

        $debugInfo = $transport->__debugInfo();

        $this->assertSame(Client::class, $debugInfo['client']);
        $this->assertNotEmpty($debugInfo['requestFactory']);
        $this->assertNotEmpty($debugInfo['streamFactory']);
        $this->assertIsArray($debugInfo['defaultOptions']);
        $this->assertIsArray($debugInfo['defaultOptions']['headers']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['Authorization']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['x-kineti-key']);
        $this->assertSame('[REDACTED]', $debugInfo['defaultOptions']['headers']['Proxy-Authorization']);
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
        $this->assertSame('X-Custom-Header: public-value', $debugInfo['defaultOptions']['headers'][3]);
    }
}
