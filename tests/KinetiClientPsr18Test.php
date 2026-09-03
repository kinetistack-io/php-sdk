<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentListOptionsDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\VisionOptionsDto;
use KinetiStack\Sdk\KinetiClient;
use KinetiStack\Sdk\Transport\HttpTransport;
use KinetiStack\Sdk\Transport\Psr18Transport;
use KinetiStack\Sdk\Transport\SymfonyTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class KinetiClientPsr18Test extends TestCase
{
    /**
     * Scenario 1: Instantiate with Guzzle (PSR-18).
     * Given a project using guzzlehttp/guzzle
     * When I instantiate new KinetiClient($host, $key, $guzzleClient)
     * Then the SDK successfully maps the request to PSR-7, sends it via Guzzle, and decodes the response cleanly.
     */
    public function testScenario1InstantiateWithGuzzlePsr18(): void
    {
        $mock = new MockHandler([
            // 1. healthz
            new Response(200, ['Content-Type' => 'application/json'], '{"status": "ok"}'),
            // 2. readyz
            new Response(200, ['Content-Type' => 'application/json'], '{"status": "ready", "checks": {"db": "up"}}'),
            // 3. analyzeImage
            new Response(200, ['Content-Type' => 'application/json'], '{"data": {"alt_text": "An image"}}'),
            // 4. analyzeImageStream
            new Response(200, ['Content-Type' => 'application/json'], '{"data": {"alt_text": "Streamed image"}}'),
            // 5. submitBatchJob
            new Response(202, ['Content-Type' => 'application/json'], '{"job_id": "job-123", "status": "pending"}'),
            // 6. getBatchJobStatus
            new Response(200, ['Content-Type' => 'application/json'], '{"job_id": "job-123", "status": "completed", "total_images": 1, "processed_images": 1}'),
            // 7. upsertDocument
            new Response(201, ['Content-Type' => 'application/json'], '{"id": "doc-uuid-1", "externalId": "doc-1", "title": "Doc Title"}'),
            // 8. deleteDocument
            new Response(204, [], ''),
            // 9. listDocuments
            new Response(200, ['Content-Type' => 'application/json'], '{"items": [{"id": "doc-uuid-1", "externalId": "doc-1", "title": "Doc Title"}], "total": 1}'),
            // 10. search
            new Response(200, ['Content-Type' => 'application/json'], '{"results": [{"external_id": "doc-1", "title": "Doc 1", "score": 0.95}], "count": 1}'),
        ]);

        $guzzleClient = new Client(['handler' => HandlerStack::create($mock)]);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $guzzleClient);

        // Verify underlying transport is Psr18Transport
        $this->assertInstanceOf(HttpTransport::class, $kineti->getTransport());
        /** @var HttpTransport $httpTransport */
        $httpTransport = $kineti->getTransport();
        $this->assertInstanceOf(Psr18Transport::class, $httpTransport->getDelegate());

        // 1. healthz
        $health = $kineti->healthz();
        $this->assertSame('ok', $health->status);

        // 2. readyz
        $ready = $kineti->readyz();
        $this->assertSame('ready', $ready->status);

        // 3. analyzeImage
        $vision = $kineti->analyzeImage('https://example.com/img.jpg');
        $this->assertSame('An image', $vision->altText);

        // 4. analyzeImageStream
        $stream = fopen('php://temp', 'w+b');
        $this->assertIsResource($stream);
        fwrite($stream, 'dummy-image-bytes');
        rewind($stream);
        $streamVision = $kineti->analyzeImageStream($stream, 'photo.jpg');
        $this->assertSame('Streamed image', $streamVision->altText);
        fclose($stream);

        // 5. submitBatchJob
        $batchJob = $kineti->submitBatchJob([
            new ImageInputDto('item-1', 'https://example.com/item.jpg'),
        ]);
        $this->assertSame('job-123', $batchJob->jobId);

        // 6. getBatchJobStatus
        $jobStatus = $kineti->getBatchJobStatus('job-123');
        $this->assertTrue($jobStatus->isCompleted());

        // 7. upsertDocument
        $docResponse = $kineti->upsertDocument(new DocumentDto('doc-1', 'Doc Title', 'Doc content'));
        $this->assertSame('doc-1', $docResponse->externalId);

        // 8. deleteDocument
        $deleted = $kineti->deleteDocument('doc-1');
        $this->assertTrue($deleted);

        // 9. listDocuments
        $collection = $kineti->listDocuments(DocumentListOptionsDto::create()->withLimit(10));
        $this->assertSame(1, $collection->total);

        // 10. search
        $searchResults = $kineti->search('test query');
        $this->assertSame(1, $searchResults->total);
        $this->assertCount(1, $searchResults);
        $this->assertSame(0.95, $searchResults->results[0]->score);
    }

    /**
     * Scenario 2: Automatic Discovery.
     * Given a project with any PSR-18 client installed and php-http/discovery enabled
     * When I instantiate new KinetiClient($host, $key) without passing a client instance
     * Then the SDK automatically discovers and uses the available PSR-18 client.
     */
    public function testScenario2AutomaticDiscovery(): void
    {
        $kineti = new KinetiClient('https://api.test', 'test-key');

        $transport = $kineti->getTransport();
        $this->assertInstanceOf(HttpTransport::class, $transport);

        /** @var HttpTransport $httpTransport */
        $httpTransport = $transport;
        // In our dev environment, Discovery finds either Guzzle (PSR-18) or Symfony
        $delegate = $httpTransport->getDelegate();
        $this->assertTrue(
            $delegate instanceof Psr18Transport || $delegate instanceof SymfonyTransport,
            'Discovered transport must be either Psr18Transport or SymfonyTransport'
        );
    }

    /**
     * Scenario 3: Backward Compatibility with Symfony.
     * Given an existing project injecting a Symfony HttpClientInterface
     * When new KinetiClient($host, $key, $symfonyClient) is called
     * Then the SDK continues to work seamlessly without throwing type errors, wrapping it internally.
     */
    public function testScenario3BackwardCompatibilityWithSymfony(): void
    {
        $mockResponse = new MockResponse('{"status": "ok"}', [
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $symfonyClient = new MockHttpClient([$mockResponse]);

        $kineti = new KinetiClient('https://api.test', 'test-key', $symfonyClient);

        $transport = $kineti->getTransport();
        $this->assertInstanceOf(HttpTransport::class, $transport);

        /** @var HttpTransport $httpTransport */
        $httpTransport = $transport;
        $this->assertInstanceOf(SymfonyTransport::class, $httpTransport->getDelegate());

        $health = $kineti->healthz();
        $this->assertSame('ok', $health->status);
    }
}
