<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\KinetiClient;
use KinetiStack\Sdk\Transport\SymfonyTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;

class SymfonyTransportTest extends TestCase
{
    /**
     * Scenario 1: Retrying a 503 Response on analyzeImageStream() with SymfonyTransport
     *
     * Given a SymfonyTransport configured with max_retries: 1 and a seekable fopen('php://temp', 'r+') image stream
     * When the first request to POST /api/v1/images/analyze returns HTTP 503 and the retry returns HTTP 202
     * Then the stream is rewound and re-sent on the second attempt without throwing Cannot traverse an already closed generator
     * And a valid JobDto is returned.
     */
    public function testAnalyzeImageStreamRetryOn503RewindsStreamAndSucceeds(): void
    {
        $jobPayload = json_encode([
            'job_id' => 'retry-stream-job-123',
            'status' => 'pending',
            'poll_url' => '/api/v1/jobs/retry-stream-job-123',
        ], JSON_THROW_ON_ERROR);

        $requestBodies = [];
        $response1 = new MockResponse('{"title": "Service Unavailable", "detail": "Temporarily down"}', [
            'http_code' => 503,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $response2 = new MockResponse($jobPayload, [
            'http_code' => 202,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);

        $mockClient = new MockHttpClient(function ($method, $url, $options) use (&$requestBodies, $response1, $response2) {
            $body = '';
            if (isset($options['body']) && is_callable($options['body'])) {
                $closure = $options['body'];
                while ('' !== ($chunk = $closure(8192))) {
                    $body .= $chunk;
                }
            }
            $requestBodies[] = $body;

            return count($requestBodies) === 1 ? $response1 : $response2;
        });

        $transport = new SymfonyTransport(
            'https://api.test',
            'test-key',
            $mockClient,
            [
                'max_retries' => 1,
                'retry_strategy' => new GenericRetryStrategy([429, 503], 0),
            ]
        );

        $client = new KinetiClient('https://api.test', 'test-key', $transport);

        $stream = fopen('php://temp', 'w+b');
        $this->assertIsResource($stream);
        fwrite($stream, 'test-image-binary-payload-data');
        rewind($stream);

        $job = $client->analyzeImageStream($stream, 'upload.jpg');

        $this->assertInstanceOf(JobDto::class, $job);
        $this->assertSame('retry-stream-job-123', $job->jobId);
        $this->assertSame('/api/v1/jobs/retry-stream-job-123', $job->pollUrl);

        $this->assertCount(2, $requestBodies);
        // Verify both attempts contained the file content
        $this->assertStringContainsString('test-image-binary-payload-data', $requestBodies[0]);
        $this->assertStringContainsString('test-image-binary-payload-data', $requestBodies[1]);
        $this->assertStringContainsString('filename="upload.jpg"', $requestBodies[0]);
        $this->assertStringContainsString('filename="upload.jpg"', $requestBodies[1]);

        fclose($stream);
    }

    /**
     * Scenario 3: Passing pause_handler or Per-Request max_retries to SymfonyTransport
     *
     * Given SymfonyTransport receiving ['pause_handler' => fn() => null, 'max_retries' => 1]
     * When request() is executed
     * Then pause_handler and max_retries are stripped before calling Symfony's HttpClientInterface::request()
     * so no InvalidArgumentException is thrown.
     */
    public function testCustomOptionsStrippedWithoutException(): void
    {
        $mockResponse = new MockResponse('{"data": "success"}', [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $mockClient = new MockHttpClient($mockResponse);

        $transport = new SymfonyTransport('https://api.test', 'test-key', $mockClient);

        $response = $transport->request('GET', '/v1/test', [
            'pause_handler' => function (): void {
            },
            'max_retries' => 1,
            'retry_strategy' => new GenericRetryStrategy([429, 503], 0),
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'success'], $response->toArray());

        $requestOptions = $mockResponse->getRequestOptions();
        $this->assertArrayNotHasKey('pause_handler', $requestOptions);
        $this->assertArrayNotHasKey('retry_strategy', $requestOptions);
        $this->assertArrayNotHasKey('max_retries', $requestOptions);
    }

    public function testPerRequestMaxRetriesOverridesDefaultZero(): void
    {
        $response1 = new MockResponse('{"title": "Service Unavailable"}', [
            'http_code' => 503,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $response2 = new MockResponse('{"recovered": true}', [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);

        $mockClient = new MockHttpClient([$response1, $response2]);

        // Default transport has NO retries
        $transport = new SymfonyTransport('https://api.test', 'test-key', $mockClient);

        $response = $transport->request('GET', '/v1/test', [
            'max_retries' => 1,
            'retry_strategy' => new GenericRetryStrategy([429, 503], 0),
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['recovered' => true], $response->toArray());
        $this->assertSame(2, $mockClient->getRequestsCount());
    }

    public function testPerRequestMaxRetriesCanDisableRetries(): void
    {
        $response1 = new MockResponse('{"title": "Service Unavailable"}', [
            'http_code' => 503,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);

        $requestCount = 0;
        $mockClient = new MockHttpClient(function ($method, $url, $options) use (&$requestCount, $response1) {
            $requestCount++;

            return $response1;
        });

        // Default transport has 2 retries
        $transport = new SymfonyTransport('https://api.test', 'test-key', $mockClient, [
            'max_retries' => 2,
            'retry_strategy' => new GenericRetryStrategy([429, 503], 0),
        ]);

        $this->expectException(ServiceUnavailableException::class);

        try {
            $transport->request('GET', '/v1/test', [
                'max_retries' => 0,
            ]);
        } finally {
            $this->assertSame(1, $requestCount);
        }
    }

    public function testCallableGeneratorFactoryBodyWorksAcrossRetries(): void
    {
        $response1 = new MockResponse('{"title": "Service Unavailable"}', [
            'http_code' => 503,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $response2 = new MockResponse('{"ok": true}', [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);

        $requestBodies = [];
        $mockClient = new MockHttpClient(function ($method, $url, $options) use (&$requestBodies, $response1, $response2) {
            $body = '';
            if (isset($options['body']) && is_callable($options['body'])) {
                $closure = $options['body'];
                while ('' !== ($chunk = $closure(8192))) {
                    $body .= $chunk;
                }
            }
            $requestBodies[] = $body;

            return count($requestBodies) === 1 ? $response1 : $response2;
        });

        $transport = new SymfonyTransport('https://api.test', 'test-key', $mockClient, [
            'max_retries' => 1,
            'retry_strategy' => new GenericRetryStrategy([429, 503], 0),
        ]);

        $bodyFactory = function () {
            return (function () {
                yield 'hello ';
                yield 'world';
            })();
        };

        $response = $transport->request('POST', '/v1/stream', [
            'body' => $bodyFactory,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->toArray());
        $this->assertCount(2, $requestBodies);
        $this->assertSame('hello world', $requestBodies[0]);
        $this->assertSame('hello world', $requestBodies[1]);
    }

    public function testStringBodyMatchingFunctionNameIsNotExecutedAsCallable(): void
    {
        $capturedBody = null;
        $mockClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedBody) {
            $capturedBody = $options['body'] ?? null;

            return new MockResponse('{"ok": true}', [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]);
        });

        $transport = new SymfonyTransport('https://api.test', 'test-key', $mockClient);
        $response = $transport->request('POST', '/v1/test', [
            'body' => 'date',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('date', $capturedBody);
    }

    public function testNativeSymfonyChunkStreamingClosureIsNotWrapped(): void
    {
        $capturedClosure = null;
        $mockClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedClosure) {
            $capturedClosure = $options['body'] ?? null;
            $content = '';
            if (is_callable($capturedClosure)) {
                $content = $capturedClosure(4096);
            }

            return new MockResponse('{"received": "' . $content . '"}', [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'application/json'],
            ]);
        });

        $transport = new SymfonyTransport('https://api.test', 'test-key', $mockClient);

        $called = false;
        $chunkClosure = function (int $size) use (&$called): string {
            if ($called) {
                return '';
            }
            $called = true;

            return 'native-chunk-' . $size;
        };

        $response = $transport->request('POST', '/v1/stream', [
            'body' => $chunkClosure,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['received' => 'native-chunk-4096'], $response->toArray());
    }

    public function testNonSeekableStreamThrowsTransportExceptionOnRetry(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->assertIsArray($pair);
        [$readStream, $writeStream] = $pair;
        fwrite($writeStream, 'test-socket-data');
        fclose($writeStream);

        $requestCount = 0;
        $response1 = new MockResponse('{"title": "Service Unavailable"}', [
            'http_code' => 503,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $response2 = new MockResponse('{"job_id": "test-job"}', [
            'http_code' => 202,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);

        $mockClient = new MockHttpClient(function ($method, $url, $options) use (&$requestCount, $response1, $response2) {
            $requestCount++;
            if (isset($options['body']) && is_callable($options['body'])) {
                $closure = $options['body'];
                while ('' !== ($chunk = $closure(65536))) {
                    // consume chunks
                }
            }

            return $requestCount === 1 ? $response1 : $response2;
        });

        $transport = new SymfonyTransport('https://api.test', 'test-key', $mockClient, [
            'max_retries' => 1,
            'retry_strategy' => new GenericRetryStrategy([503], 0),
        ]);

        $client = new KinetiClient('https://api.test', 'test-key', $transport);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Cannot retry request with non-seekable body stream.');

        try {
            $client->analyzeImageStream($readStream, 'nonseekable.jpg');
        } finally {
            fclose($readStream);
        }
    }

    public function testToArrayReturnsEmptyArrayOnEmptyResponseBody(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 204,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $mockClient = new MockHttpClient($mockResponse);
        $transport = new SymfonyTransport('https://api.test', 'test-key', $mockClient);

        $response = $transport->request('DELETE', '/v1/resource/123');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame([], $response->toArray());
    }

    public function testToArrayReturnsEmptyArrayOnWhitespaceOnlyResponseBody(): void
    {
        $mockResponse = new MockResponse("   \r\n   ", [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $mockClient = new MockHttpClient($mockResponse);
        $transport = new SymfonyTransport('https://api.test', 'test-key', $mockClient);

        $response = $transport->request('GET', '/v1/empty');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->toArray());
    }
}
