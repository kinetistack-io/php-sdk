<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\Dto\DocumentCollectionDto;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentListOptionsDto;
use KinetiStack\Sdk\Dto\DocumentResponseDto;
use KinetiStack\Sdk\Dto\DocumentSummaryDto;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Enum\JobStatus;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\NotFoundException;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\KinetiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class KinetiClientDocumentsTest extends TestCase
{
    use FixtureTrait;

    public function testUpsertDocumentAccepted202(): void
    {
        $fixture = $this->loadFixture('Documents/document_upsert_202.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 202,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $doc = new DocumentDto(
            externalId: 'node:42:en',
            title: 'Sample Article',
            content: 'Detailed body content for indexing.',
            locale: 'en',
            permissions: ['public'],
            metadata: ['category' => 'news']
        );

        $response = $kineti->upsertDocument($doc);

        $this->assertInstanceOf(JobDto::class, $response);
        $this->assertSame('00000000-0000-0000-0000-000000000001', $response->jobId);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $response->documentId);
        $this->assertSame('node:42:en', $response->externalId);
        $this->assertSame(0, $response->chunksGenerated);
        $this->assertSame(JobStatus::Pending, $response->status);
        $this->assertTrue($response->isPending());
        $this->assertSame('/api/v1/jobs/00000000-0000-0000-0000-000000000001', $response->pollUrl);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/documents', $mockResponse->getRequestUrl());

        $requestHeaders = $mockResponse->getRequestOptions()['headers'];
        $this->assertContains('X-Kineti-Key: test-api-key', $requestHeaders);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame('node:42:en', $requestBody['external_id']);
        $this->assertSame('Sample Article', $requestBody['title']);
        $this->assertSame('Detailed body content for indexing.', $requestBody['content']);
        $this->assertSame('en', $requestBody['locale']);
        $this->assertSame(['public'], $requestBody['permissions']);
        $this->assertSame(['category' => 'news'], $requestBody['metadata']);
    }

    public function testUpsertDocumentUpdated200(): void
    {
        $fixture = json_encode([
            'job_id' => '00000000-0000-0000-0000-000000000002',
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'external_id' => 'node:42:en',
            'chunks_generated' => 0,
            'status' => 'pending',
            'poll_url' => '/api/v1/jobs/00000000-0000-0000-0000-000000000002',
        ], JSON_THROW_ON_ERROR);
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 202,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $doc = new DocumentDto('node:42:en', 'Updated Article', 'New content body');
        $response = $kineti->upsertDocument($doc);

        $this->assertInstanceOf(JobDto::class, $response);
        $this->assertSame('00000000-0000-0000-0000-000000000002', $response->jobId);
        $this->assertSame(JobStatus::Pending, $response->status);
    }

    public function testUpsertDocumentAndPollWithWaitForJob(): void
    {
        $upsertResponse = new MockResponse(json_encode([
            'job_id' => 'ingest-job-1',
            'document_id' => 'doc-1',
            'external_id' => 'node:100',
            'chunks_generated' => 0,
            'status' => 'pending',
            'poll_url' => '/api/v1/jobs/ingest-job-1',
        ], JSON_THROW_ON_ERROR), ['http_code' => 202]);

        $pollProcessing = new MockResponse(json_encode([
            'job_id' => 'ingest-job-1',
            'status' => 'processing',
        ], JSON_THROW_ON_ERROR));

        $pollCompleted = new MockResponse(json_encode([
            'job_id' => 'ingest-job-1',
            'status' => 'completed',
            'type' => 'document_ingest',
            'results' => [
                'document_id' => 'doc-1',
                'external_id' => 'node:100',
                'chunks_generated' => 5,
                'status' => 'indexed',
            ],
        ], JSON_THROW_ON_ERROR));

        $client = new MockHttpClient([$upsertResponse, $pollProcessing, $pollCompleted]);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $job = $kineti->upsertDocument(new DocumentDto('node:100', 'Doc', 'Content'));
        $this->assertSame('ingest-job-1', $job->jobId);
        $this->assertTrue($job->isPending());

        $completed = $kineti->waitForJob($job->jobId, 10, 0);
        $this->assertTrue($completed->isCompleted());
        $this->assertSame('doc-1', $completed->documentId);
        $this->assertSame(5, $completed->chunksGenerated);
        $this->assertIsArray($completed->results);
        $this->assertArrayHasKey('chunks_generated', $completed->results);
        $this->assertSame(5, $completed->results['chunks_generated']);
    }

    public function testUpsertDocumentValidationError422(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_validation_error_422.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 422,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('title: This value should not be blank.');

        try {
            $kineti->upsertDocument(new DocumentDto('node:1:en', 'Valid Title', 'Content'));
        } catch (ValidationException $e) {
            $this->assertCount(1, $e->getViolations());
            $this->assertSame('title', $e->getViolations()[0]['propertyPath']);
            $this->assertSame('This value should not be blank.', $e->getViolations()[0]['message']);
            throw $e;
        }
    }

    public function testUpsertDocumentUnauthorized401(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_unauthorized_401.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 401,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'invalid-key', $client);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid or missing API key.');

        $kineti->upsertDocument(new DocumentDto('node:1:en', 'Title', 'Content'));
    }

    public function testUpsertDocumentForbidden403(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_forbidden_403.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 403,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Access denied for this resource or project.');

        $kineti->upsertDocument(new DocumentDto('node:1:en', 'Title', 'Content'));
    }

    public function testUpsertDocumentServerError500(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_server_error_500.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 500,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('An unexpected error occurred on the server.');

        $kineti->upsertDocument(new DocumentDto('node:1:en', 'Title', 'Content'));
    }

    public function testDeleteDocumentSuccess204(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $result = $kineti->deleteDocument('node:42:en');

        $this->assertTrue($result);
        $this->assertSame('DELETE', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/documents/node%3A42%3Aen', $mockResponse->getRequestUrl());
    }

    public function testDeleteDocumentUrlEncodesSpecialCharacters(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 204]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $kineti->deleteDocument('item/path?name=foo&lang=da#section');

        $expectedEncoded = rawurlencode('item/path?name=foo&lang=da#section');
        $this->assertStringEndsWith('/api/v1/documents/' . $expectedEncoded, $mockResponse->getRequestUrl());
    }

    public function testDeleteDocumentNotFound404(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_not_found_404.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 404,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Document not found.');

        $kineti->deleteDocument('node:missing:en');
    }

    public function testDeleteDocumentUnauthorized401(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_unauthorized_401.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 401,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'invalid-key', $client);

        $this->expectException(AuthenticationException::class);
        $kineti->deleteDocument('node:1:en');
    }

    public function testDeleteDocumentForbidden403(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_forbidden_403.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 403,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $this->expectException(AuthorizationException::class);
        $kineti->deleteDocument('node:1:en');
    }

    public function testDeleteDocumentEmptyIdThrows(): void
    {
        $kineti = new KinetiClient('https://api.test', 'test-key');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('externalId cannot be empty.');

        $kineti->deleteDocument('   ');
    }

    public function testListDocumentsNoFiltersHydra(): void
    {
        $fixture = $this->loadFixture('Documents/document_list_hydra_200.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $collection = $kineti->listDocuments();

        $this->assertInstanceOf(DocumentCollectionDto::class, $collection);
        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/documents', $mockResponse->getRequestUrl());

        $this->assertSame(2, $collection->total);
        $this->assertCount(2, $collection);
        $this->assertSame(2, count($collection->items));

        $item1 = $collection->items[0];
        $this->assertInstanceOf(DocumentSummaryDto::class, $item1);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $item1->id);
        $this->assertSame('node:1:en', $item1->externalId);
        $this->assertSame('Article 1', $item1->title);
        $this->assertSame('en', $item1->locale);
        $this->assertSame(['public'], $item1->permissions);
        $this->assertSame(['tags' => ['news', 'tech']], $item1->metadata);
        $this->assertSame(2, $item1->chunkCount);
        $this->assertSame(2, $item1->chunksGenerated);
        $this->assertSame('indexed', $item1->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $item1->createdAt);
        $this->assertSame('2026-01-01T00:00:00+00:00', $item1->createdAt->format(\DateTimeInterface::ATOM));
        $this->assertInstanceOf(\DateTimeImmutable::class, $item1->updatedAt);
        $this->assertSame('2026-01-02T00:00:00+00:00', $item1->updatedAt->format(\DateTimeInterface::ATOM));

        $item2 = $collection->items[1];
        $this->assertSame('node:2:en', $item2->externalId);
        $this->assertSame('Article 2', $item2->title);
        $this->assertSame(['admin'], $item2->permissions);
        $this->assertNull($item2->metadata);
        $this->assertSame(5, $item2->chunkCount);

        // Test iterator
        $externalIds = [];
        foreach ($collection as $docSummary) {
            $externalIds[] = $docSummary->externalId;
        }
        $this->assertSame(['node:1:en', 'node:2:en'], $externalIds);
    }

    public function testListDocumentsPlainList(): void
    {
        $fixture = $this->loadFixture('Documents/document_list_plain_200.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $collection = $kineti->listDocuments();

        $this->assertSame(2, $collection->total);
        $this->assertCount(2, $collection);
        $this->assertSame('node:1:en', $collection->items[0]->externalId);
        $this->assertSame('node:2:en', $collection->items[1]->externalId);
    }

    public function testListDocumentsEmptyCollection(): void
    {
        $fixture = $this->loadFixture('Documents/document_list_empty_200.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $collection = $kineti->listDocuments();

        $this->assertSame(0, $collection->total);
        $this->assertCount(0, $collection);
        $this->assertSame([], $collection->items);
    }

    public function testListDocumentsWithFilters(): void
    {
        $fixture = $this->loadFixture('Documents/document_list_hydra_200.json');
        $mockResponse = new MockResponse($fixture, ['http_code' => 200]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $options = DocumentListOptionsDto::create()
            ->withLocale('da')
            ->withExternalId('node:')
            ->withPage(2)
            ->withItemsPerPage(15);
        $collection = $kineti->listDocuments($options);

        $this->assertSame(2, $collection->total);
        $requestUrl = $mockResponse->getRequestUrl();
        $this->assertStringContainsString('locale=da', $requestUrl);
        $this->assertStringContainsString('external_id=node:', $requestUrl);
        $this->assertStringContainsString('page=2', $requestUrl);
        $this->assertStringContainsString('itemsPerPage=15', $requestUrl);
    }

    public function testListDocumentsUnauthorized401(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_unauthorized_401.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 401,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'invalid-key', $client);

        $this->expectException(AuthenticationException::class);
        $kineti->listDocuments();
    }

    public function testListDocumentsForbidden403(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_forbidden_403.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 403,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $this->expectException(AuthorizationException::class);
        $kineti->listDocuments();
    }

    public function testListDocumentsServerError500(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_server_error_500.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 500,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $this->expectException(ServerException::class);
        $kineti->listDocuments();
    }
}
