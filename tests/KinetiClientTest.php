<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\Dto\DocumentCollectionDto;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentResponseDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Exception\BatchJobTimeoutException;
use KinetiStack\Sdk\Exception\NotFoundException;
use KinetiStack\Sdk\Exception\ValidationException;
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
            'checks' => ['database' => 'ok']
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
                'confidence_score' => 0.95
            ]
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
            'status' => 'pending'
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $client = new MockHttpClient($mockResponse);

        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $result = $kineti->submitBatchJob([
            new ImageInputDto('media:1', 'http://example.com/1.jpg')
        ]);

        $this->assertSame('123-abc', $result->jobId);
        $this->assertSame('pending', $result->status);
        $this->assertFalse($result->isCompleted());
    }

    public function testWaitForBatchJobTimeout(): void
    {
        $responseBody = json_encode([
            'job_id' => '123',
            'status' => 'processing'
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
            ]
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
            'status' => 'completed'
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

    public function testUpsertDocumentCreated(): void
    {
        $responseBody = json_encode([
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'external_id' => 'node:42:en',
            'chunks_generated' => 4,
            'status' => 'indexed',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 201]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $doc = new DocumentDto('node:42:en', 'Title', 'Content', 'en', ['admin'], ['bundle' => 'article']);
        $result = $kineti->upsertDocument($doc);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $result->documentId);
        $this->assertSame('node:42:en', $result->externalId);
        $this->assertSame(4, $result->chunksGenerated);
        $this->assertSame('indexed', $result->status);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/documents', $mockResponse->getRequestUrl());

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame('node:42:en', $requestBody['external_id']);
        $this->assertSame('Title', $requestBody['title']);
        $this->assertSame('Content', $requestBody['content']);
        $this->assertSame('en', $requestBody['locale']);
        $this->assertSame(['admin'], $requestBody['permissions']);
        $this->assertSame(['bundle' => 'article'], $requestBody['metadata']);
    }

    public function testUpsertDocumentReplaced(): void
    {
        $responseBody = json_encode([
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'external_id' => 'node:42:en',
            'chunks_generated' => 3,
            'status' => 'indexed',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $doc = new DocumentDto('node:42:en', 'Updated Title', 'Updated Content');
        $result = $kineti->upsertDocument($doc);

        $this->assertSame(3, $result->chunksGenerated);
        $this->assertSame('indexed', $result->status);
    }

    public function testUpsertDocumentValidationError(): void
    {
        $responseBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'An error occurred',
            'detail' => 'Validation failed',
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'title must not be blank.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 422,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        $doc = new DocumentDto('node:42:en', 'Valid title here', 'Content');
        try {
            $kineti->upsertDocument($doc);
        } catch (ValidationException $e) {
            $this->assertCount(1, $e->getViolations());
            $this->assertSame('title', $e->getViolations()[0]['propertyPath']);
            throw $e;
        }
    }

    public function testDeleteDocumentSuccess(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $result = $kineti->deleteDocument('node:42:en');

        $this->assertTrue($result);
        $this->assertSame('DELETE', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/documents/node%3A42%3Aen', $mockResponse->getRequestUrl());
    }

    public function testDeleteDocumentNotFound(): void
    {
        $responseBody = json_encode([
            'title' => 'Not Found',
            'detail' => 'Document not found.',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 404,
            'response_headers' => ['content-type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Document not found.');

        $kineti->deleteDocument('node:99:en');
    }

    public function testDeleteDocumentEmptyIdThrows(): void
    {
        $kineti = new KinetiClient('https://api.test', 'key');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('externalId cannot be empty.');

        $kineti->deleteDocument('   ');
    }

    public function testListDocumentsNoFilters(): void
    {
        $responseBody = json_encode([
            [
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'external_id' => 'node:1:en',
                'title' => 'Article 1',
                'locale' => 'en',
                'permissions' => ['public'],
                'metadata' => ['tags' => ['news']],
                'chunk_count' => 2,
                'chunks_generated' => 2,
                'status' => 'indexed',
                'created_at' => '2026-01-01T00:00:00+00:00',
                'updated_at' => '2026-01-02T00:00:00+00:00',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440001',
                'external_id' => 'node:2:en',
                'title' => 'Article 2',
                'locale' => 'en',
                'chunk_count' => 5,
                'chunks_generated' => 5,
                'status' => 'indexed',
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $collection = $kineti->listDocuments();

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/documents', $mockResponse->getRequestUrl());

        $this->assertSame(2, $collection->total);
        $this->assertCount(2, $collection);
        $this->assertSame(2, count($collection->items));

        $item1 = $collection->items[0];
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $item1->id);
        $this->assertSame('node:1:en', $item1->externalId);
        $this->assertSame('Article 1', $item1->title);
        $this->assertSame('en', $item1->locale);
        $this->assertSame(['public'], $item1->permissions);
        $this->assertSame(['tags' => ['news']], $item1->metadata);
        $this->assertSame(2, $item1->chunkCount);
        $this->assertSame(2, $item1->chunksGenerated);
        $this->assertSame('indexed', $item1->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $item1->createdAt);
        $this->assertSame('2026-01-01T00:00:00+00:00', $item1->createdAt->format(\DateTimeInterface::ATOM));
        $this->assertInstanceOf(\DateTimeImmutable::class, $item1->updatedAt);
        $this->assertSame('2026-01-02T00:00:00+00:00', $item1->updatedAt->format(\DateTimeInterface::ATOM));

        // Test iteration
        $titles = [];
        foreach ($collection as $item) {
            $titles[] = $item->title;
        }
        $this->assertSame(['Article 1', 'Article 2'], $titles);
    }

    public function testListDocumentsWithFilters(): void
    {
        $responseBody = json_encode([
            'hydra:member' => [
                [
                    'external_id' => 'node:42:da',
                    'title' => 'Dansk Artikel',
                    'locale' => 'da',
                ],
            ],
            'hydra:totalItems' => 1,
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $filters = ['locale' => 'da', 'external_id' => 'node:'];
        $collection = $kineti->listDocuments($filters);

        $this->assertSame(1, $collection->total);
        $this->assertCount(1, $collection);
        $this->assertSame('node:42:da', $collection->items[0]->externalId);
        $this->assertStringContainsString('locale=da', $mockResponse->getRequestUrl());
        $this->assertStringContainsString('external_id=node:', $mockResponse->getRequestUrl());
    }
}
