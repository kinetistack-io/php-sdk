<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\Dto\ImageInputDto;
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
        $this->assertSame(['status' => 'ok'], $result);
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
        $this->assertSame(['status' => 'ok', 'checks' => ['database' => 'ok']], $result);
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

        $result = $kineti->analyzeImage('http://example.com/img.jpg', ['page_title' => 'Test']);

        $this->assertSame('A test image', $result->altText);
        $this->assertSame(['test', 'image'], $result->tags);
        $this->assertSame(0.95, $result->confidenceScore);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame('http://example.com/img.jpg', $requestBody['image_url']);
        $this->assertSame('Test', $requestBody['context_hints']['page_title']);
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
        $result = $kineti->submitBatchJob([
            new ImageInputDto('media:2', null, ['tag' => 'v1'], $base64Data),
        ], ['webhook_url' => 'https://example.com/webhook']);

        $this->assertSame('base64-job-456', $result->jobId);
        $this->assertSame(JobStatus::Pending, $result->status);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertCount(1, $requestBody['images']);
        $this->assertSame('media:2', $requestBody['images'][0]['external_id']);
        $this->assertSame($base64Data, $requestBody['images'][0]['image_base64']);
        $this->assertArrayNotHasKey('image_url', $requestBody['images'][0]);
        $this->assertSame(['tag' => 'v1'], $requestBody['images'][0]['context_hints']);
        $this->assertSame(['webhook_url' => 'https://example.com/webhook'], $requestBody['options']);
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

        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $result = $kineti->analyzeImageContent('binary-data', 'test.png', ['hint' => 'val']);

        $this->assertSame('Binary test image', $result->altText);
        $options = $mockResponse->getRequestOptions();
        $headersStr = is_array($options['headers']) ? implode("\n", $options['headers']) : '';
        $this->assertStringContainsString('multipart/form-data', $headersStr);
        $this->assertStringContainsString('filename="test.png"', $options['body']);
        $this->assertStringContainsString('binary-data', $options['body']);
        $this->assertStringContainsString('"hint":"val"', $options['body']);
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
}
