<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\Dto\ContextHintsDto;
use KinetiStack\Sdk\Dto\HealthStatusDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Dto\VisionOptionsDto;
use KinetiStack\Sdk\Enum\JobStatus;
use KinetiStack\Sdk\Exception\BatchJobTimeoutException;
use KinetiStack\Sdk\Exception\PayloadTooLargeException;
use KinetiStack\Sdk\KinetiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class KinetiClientTest extends TestCase
{
    public function testHealthz(): void
    {
        $responseBody = json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR);
        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $result = $kineti->healthz();
        $this->assertInstanceOf(HealthStatusDto::class, $result);
        $this->assertSame('ok', $result->status);
        $this->assertTrue($result->isHealthy());
        $this->assertTrue($result->isReady());
        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/healthz', $mockResponse->getRequestUrl());
    }

    public function testReadyz(): void
    {
        $responseBody = json_encode([
            'status' => 'ok',
            'checks' => ['database' => 'ok'],
        ], JSON_THROW_ON_ERROR);
        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $result = $kineti->readyz();
        $this->assertInstanceOf(HealthStatusDto::class, $result);
        $this->assertSame('ok', $result->status);
        $this->assertTrue($result->isHealthy());
        $this->assertTrue($result->isReady());
        $this->assertSame(['database' => 'ok'], $result->checks);
        $this->assertSame(['database' => 'ok'], $result->getChecks());
        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/readyz', $mockResponse->getRequestUrl());
    }

    public function testAnalyzeImage(): void
    {
        $responseBody = json_encode([
            'data' => [
                'alt_text' => 'A test image',
                'tags' => ['test', 'image'],
                'confidence_score' => 0.95,
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);

        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $hints = ContextHintsDto::create('Test')->withTaxonomy(['Sample']);
        $options = VisionOptionsDto::create()->withLanguage('nl')->withMaxLength(120);

        $result = $kineti->analyzeImage('http://example.com/img.jpg', $hints, $options);

        $this->assertSame('A test image', $result->altText);
        $this->assertSame(['test', 'image'], $result->tags);
        $this->assertSame(0.95, $result->confidenceScore);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame('http://example.com/img.jpg', $requestBody['image_url']);
        $this->assertSame('Test', $requestBody['context_hints']['page_title']);
        $this->assertSame(['Sample'], $requestBody['context_hints']['taxonomy']);
        $this->assertSame('nl', $requestBody['options']['language']);
        $this->assertSame(120, $requestBody['options']['max_length']);
    }

    public function testSubmitBatchJob(): void
    {
        $responseBody = json_encode([
            'job_id' => '123-abc',
            'status' => 'pending',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);

        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $result = $kineti->submitBatchJob([
            new ImageInputDto('media:1', 'http://example.com/1.jpg'),
        ]);

        $this->assertSame('123-abc', $result->jobId);
        $this->assertSame(JobStatus::Pending, $result->status);
        $this->assertFalse($result->isCompleted());
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/jobs/batch-images', $mockResponse->getRequestUrl());

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertCount(1, $requestBody['images']);
        $this->assertSame('media:1', $requestBody['images'][0]['external_id']);
        $this->assertSame('http://example.com/1.jpg', $requestBody['images'][0]['image_url']);
        $this->assertArrayNotHasKey('image_base64', $requestBody['images'][0]);
    }

    public function testSubmitBatchJobWithImageBase64(): void
    {
        $responseBody = json_encode([
            'job_id' => 'base64-job-456',
            'status' => 'pending',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);

        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $base64Data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $options = VisionOptionsDto::create()->withCustom('webhook_url', 'https://example.com/webhook');

        $result = $kineti->submitBatchJob([
            new ImageInputDto('media:2', null, ['tag' => 'v1'], $base64Data),
        ], $options);

        $this->assertSame('base64-job-456', $result->jobId);
        $this->assertSame(JobStatus::Pending, $result->status);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertCount(1, $requestBody['images']);
        $this->assertSame('media:2', $requestBody['images'][0]['external_id']);
        $this->assertSame($base64Data, $requestBody['images'][0]['image_base64']);
        $this->assertArrayNotHasKey('image_url', $requestBody['images'][0]);
        $this->assertSame(['tag' => 'v1'], $requestBody['images'][0]['context_hints']);
        $this->assertSame('https://example.com/webhook', $requestBody['options']['webhook_url']);
    }

    public function testSubmitBatchJobPayloadExceedsLimitThrowsPayloadTooLargeException(): void
    {
        $mockResponse = new MockResponse('{}');
        $client = new MockHttpClient($mockResponse);

        $kineti = new KinetiClient('https://api.test', 'key', $client);

        // 11MB string exceeds 10MB (10485760 bytes) limit
        $largeBase64 = str_repeat('a', 11 * 1024 * 1024);

        $this->expectException(PayloadTooLargeException::class);
        $this->expectExceptionMessage('Batch payload size exceeds the maximum limit of 10MB (10485760 bytes).');

        $kineti->submitBatchJob([
            new ImageInputDto('media:large', null, [], $largeBase64),
        ]);
    }

    public function testWaitForBatchJobTimeout(): void
    {
        $responseBody = json_encode([
            'job_id' => '123',
            'status' => 'processing',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient([$mockResponse]);

        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $this->expectException(BatchJobTimeoutException::class);
        $kineti->waitForBatchJob('123', 0, 0);
    }

    public function testAnalyzeImageBinaryThrowsIfFileNotFound(): void
    {
        $kineti = new KinetiClient('https://api.test', 'key');

        $this->expectException(\InvalidArgumentException::class);
        $kineti->analyzeImageBinary('/does/not/exist.jpg');
    }

    public function testAnalyzeImageContent(): void
    {
        $responseBody = json_encode([
            'data' => [
                'alt_text' => 'Binary test image',
            ],
        ], JSON_THROW_ON_ERROR);

        $capturedBody = '';
        $capturedHeaders = [];

        $client = new MockHttpClient(function ($method, $url, $options) use (&$capturedBody, &$capturedHeaders, $responseBody) {
            $capturedHeaders = $options['headers'] ?? [];
            $bodyClosure = $options['body'];
            while ('' !== ($chunk = $bodyClosure(8192))) {
                $capturedBody .= $chunk;
            }
            return new MockResponse($responseBody);
        });
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $hints = ContextHintsDto::create()->withCustom('hint', 'val');
        $options = VisionOptionsDto::create()->withLanguage('en');

        $result = $kineti->analyzeImageContent('binary-data', 'test.png', $hints, $options);

        $this->assertSame('Binary test image', $result->altText);
        $headersStr = is_array($capturedHeaders) ? implode("\n", $capturedHeaders) : '';
        $this->assertStringContainsString('multipart/form-data', $headersStr);
        $this->assertStringContainsString('filename="test.png"', $capturedBody);
        $this->assertStringContainsString('binary-data', $capturedBody);
        $this->assertStringContainsString('"hint":"val"', $capturedBody);
        $this->assertStringContainsString('"language":"en"', $capturedBody);
    }

    public function testGetBatchJobStatus(): void
    {
        $responseBody = json_encode([
            'job_id' => 'abc',
            'status' => 'completed',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $result = $kineti->getBatchJobStatus('abc');
        $this->assertTrue($result->isCompleted());
        $this->assertSame('abc', $result->jobId);
    }

    public function testWaitForBatchJobSuccessWithCallback(): void
    {
        $response1 = new MockResponse(json_encode(['job_id' => '123', 'status' => 'processing'], JSON_THROW_ON_ERROR));
        $response2 = new MockResponse(json_encode(['job_id' => '123', 'status' => 'completed'], JSON_THROW_ON_ERROR));

        $client = new MockHttpClient([$response1, $response2]);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $callbackCalls = 0;
        $result = $kineti->waitForBatchJob('123', 60, 0, function ($dto) use (&$callbackCalls) {
            $callbackCalls++;
        });

        $this->assertTrue($result->isCompleted());
        $this->assertSame(2, $callbackCalls);
    }

    public function testAnalyzeImageStream(): void
    {
        $responseBody = json_encode([
            'data' => [
                'alt_text' => 'Stream test image',
            ],
        ], JSON_THROW_ON_ERROR);

        $capturedBody = '';
        $capturedHeaders = [];

        $client = new MockHttpClient(function ($method, $url, $options) use (&$capturedBody, &$capturedHeaders, $responseBody) {
            $capturedHeaders = $options['headers'] ?? [];
            $bodyClosure = $options['body'];
            while ('' !== ($chunk = $bodyClosure(8192))) {
                $capturedBody .= $chunk;
            }
            return new MockResponse($responseBody);
        });

        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $hints = ContextHintsDto::create()->withCustom('hint', 'stream_val');
        $options = VisionOptionsDto::create()->withLanguage('fr');

        $stream = fopen('php://temp', 'w+b');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Failed to open temp stream');
        }
        fwrite($stream, 'stream-data-content');
        rewind($stream);

        $result = $kineti->analyzeImageStream($stream, 'stream.png', $hints, $options);

        $this->assertSame('Stream test image', $result->altText);

        $headersStr = is_array($capturedHeaders) ? implode("\n", $capturedHeaders) : '';
        $this->assertStringContainsString('multipart/form-data', $headersStr);

        $this->assertStringContainsString('filename="stream.png"', $capturedBody);
        $this->assertStringContainsString('stream-data-content', $capturedBody);
        $this->assertStringContainsString('"hint":"stream_val"', $capturedBody);
        $this->assertStringContainsString('"language":"fr"', $capturedBody);

        fclose($stream);
    }

    public function testAnalyzeImageStreamThrowsIfInvalidResource(): void
    {
        $kineti = new KinetiClient('https://api.test', 'key');
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line */
        $kineti->analyzeImageStream('not-a-resource');
    }

    public function testAnalyzeImageStreamThrowsIfClosedResource(): void
    {
        $kineti = new KinetiClient('https://api.test', 'key');
        $stream = fopen('php://temp', 'w+b');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Failed to open temp stream');
        }
        fclose($stream);
        $this->expectException(\InvalidArgumentException::class);
        $kineti->analyzeImageStream($stream);
    }

    public function testAnalyzeImageStreamRewindsSeekableStream(): void
    {
        $responseBody = json_encode([
            'data' => [
                'alt_text' => 'Rewound stream image',
            ],
        ], JSON_THROW_ON_ERROR);

        $capturedBody = '';
        $client = new MockHttpClient(function ($method, $url, $options) use (&$capturedBody, $responseBody) {
            $bodyClosure = $options['body'];
            while ('' !== ($chunk = $bodyClosure(8192))) {
                $capturedBody .= $chunk;
            }
            return new MockResponse($responseBody);
        });

        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $stream = fopen('php://temp', 'w+b');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Failed to open temp stream');
        }
        fwrite($stream, 'seekable-content');
        // Intentionally do not rewind before calling analyzeImageStream; pointer is at end.
        $this->assertSame(strlen('seekable-content'), ftell($stream));

        $result = $kineti->analyzeImageStream($stream, 'rewind.png');

        $this->assertSame('Rewound stream image', $result->altText);
        $this->assertStringContainsString('seekable-content', $capturedBody);

        fclose($stream);
    }

    public function testWaitForBatchJobExponentialBackoffProgressionAndJitter(): void
    {
        $responses = [
            new MockResponse(json_encode(['job_id' => 'exp-1', 'status' => 'processing'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['job_id' => 'exp-1', 'status' => 'processing'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['job_id' => 'exp-1', 'status' => 'processing'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['job_id' => 'exp-1', 'status' => 'processing'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['job_id' => 'exp-1', 'status' => 'processing'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['job_id' => 'exp-1', 'status' => 'completed'], JSON_THROW_ON_ERROR)),
        ];

        $client = new MockHttpClient($responses);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        /** @var list<int> $sleepCalls */
        $sleepCalls = [];
        $result = $kineti->waitForBatchJob(
            jobId: 'exp-1',
            timeoutSeconds: 60,
            pollIntervalSeconds: 1,
            onProgress: null,
            maxPollIntervalSeconds: 5,
            sleeper: function (int $microseconds) use (&$sleepCalls): void {
                $sleepCalls[] = $microseconds;
            }
        );

        $this->assertTrue($result->isCompleted());
        $this->assertCount(5, $sleepCalls);

        // Expected intervals in seconds before jitter: 1s, 2s, 4s, 5s (capped), 5s (capped)
        // Jitter is between 0 and 500,000 microseconds (0 - 500ms)
        $expectedBaseSeconds = [1, 2, 4, 5, 5];

        foreach ($expectedBaseSeconds as $index => $baseSeconds) {
            $minUs = $baseSeconds * 1_000_000;
            $maxUs = $minUs + 500_000;

            $this->assertGreaterThanOrEqual(
                $minUs,
                $sleepCalls[$index],
                sprintf('Sleep call %d was %d us, expected >= %d us', $index, $sleepCalls[$index], $minUs)
            );
            $this->assertLessThanOrEqual(
                $maxUs,
                $sleepCalls[$index],
                sprintf('Sleep call %d was %d us, expected <= %d us', $index, $sleepCalls[$index], $maxUs)
            );
        }

        // Total requests made: 6 polls vs 15+ polls if polling at 1s intervals over 17 seconds
        $this->assertSame(6, $client->getRequestsCount());
    }

    public function testWaitForBatchJobDefaultMaxPollIntervalCapsAtTenSeconds(): void
    {
        $responses = [
            new MockResponse(json_encode(['job_id' => 'exp-2', 'status' => 'processing'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['job_id' => 'exp-2', 'status' => 'processing'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['job_id' => 'exp-2', 'status' => 'processing'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['job_id' => 'exp-2', 'status' => 'completed'], JSON_THROW_ON_ERROR)),
        ];

        $client = new MockHttpClient($responses);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        /** @var list<int> $sleepCalls */
        $sleepCalls = [];
        $kineti->waitForBatchJob(
            jobId: 'exp-2',
            timeoutSeconds: 60,
            pollIntervalSeconds: 4, // 4s -> 8s -> 10s (capped at default 10s)
            sleeper: function (int $microseconds) use (&$sleepCalls): void {
                $sleepCalls[] = $microseconds;
            }
        );

        $this->assertCount(3, $sleepCalls);
        // Call 0: 4s
        $this->assertGreaterThanOrEqual(4_000_000, $sleepCalls[0]);
        $this->assertLessThanOrEqual(4_500_000, $sleepCalls[0]);
        // Call 1: 8s
        $this->assertGreaterThanOrEqual(8_000_000, $sleepCalls[1]);
        $this->assertLessThanOrEqual(8_500_000, $sleepCalls[1]);
        // Call 2: capped at default 10s (not 16s)
        $this->assertGreaterThanOrEqual(10_000_000, $sleepCalls[2]);
        $this->assertLessThanOrEqual(10_500_000, $sleepCalls[2]);
    }

    public function testDefaultPollingConstants(): void
    {
        $this->assertSame(2, KinetiClient::DEFAULT_POLL_INTERVAL_SECONDS);
        $this->assertSame(10, KinetiClient::DEFAULT_MAX_POLL_INTERVAL_SECONDS);
        $this->assertSame(60, KinetiClient::DEFAULT_TIMEOUT_SECONDS);

        $reflection = new \ReflectionMethod(KinetiClient::class, 'waitForBatchJob');
        $params = [];
        foreach ($reflection->getParameters() as $param) {
            if ($param->isDefaultValueAvailable()) {
                $params[$param->getName()] = $param->getDefaultValue();
            }
        }

        $this->assertArrayHasKey('timeoutSeconds', $params);
        $this->assertArrayHasKey('pollIntervalSeconds', $params);
        $this->assertArrayHasKey('maxPollIntervalSeconds', $params);

        $this->assertSame(KinetiClient::DEFAULT_TIMEOUT_SECONDS, $params['timeoutSeconds']);
        $this->assertSame(KinetiClient::DEFAULT_POLL_INTERVAL_SECONDS, $params['pollIntervalSeconds']);
        $this->assertSame(KinetiClient::DEFAULT_MAX_POLL_INTERVAL_SECONDS, $params['maxPollIntervalSeconds']);
    }
}
