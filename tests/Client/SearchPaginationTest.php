<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Client;

use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\KinetiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SearchPaginationTest extends TestCase
{
    public function testSearchAllAutoPaginatesAcrossThreePages(): void
    {
        $requests = [];
        $responses = [
            // Page 1: 2 items (full page)
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:1', 'title' => 'Document 1', 'content' => 'Content 1', 'chunk_index' => 0, 'score' => 0.95],
                    ['external_id' => 'doc:2', 'title' => 'Document 2', 'content' => 'Content 2', 'chunk_index' => 0, 'score' => 0.90],
                ],
                'total' => 5,
                'page' => 1,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
            // Page 2: 2 items (full page)
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:3', 'title' => 'Document 3', 'content' => 'Content 3', 'chunk_index' => 0, 'score' => 0.85],
                    ['external_id' => 'doc:4', 'title' => 'Document 4', 'content' => 'Content 4', 'chunk_index' => 0, 'score' => 0.80],
                ],
                'total' => 5,
                'page' => 2,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
            // Page 3: 1 item (< limit of 2, marks last page)
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:5', 'title' => 'Document 5', 'content' => 'Content 5', 'chunk_index' => 0, 'score' => 0.75],
                ],
                'total' => 5,
                'page' => 3,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
        ];

        $callback = function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'body' => json_decode((string) ($options['body'] ?? '{}'), true),
            ];
            $response = array_shift($responses);
            $this->assertNotNull($response, 'Unexpected extra network call made.');

            return $response;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('renewable energy')->withLimit(2);
        $generator = $kineti->searchAll($query);

        $this->assertInstanceOf(\Generator::class, $generator);

        /** @var list<SearchResultItemDto> $items */
        $items = iterator_to_array($generator, false);

        // Assert exactly 3 network calls were executed
        $this->assertCount(3, $requests);

        // Verify request payloads
        $this->assertSame(1, $requests[0]['body']['page']);
        $this->assertSame(2, $requests[0]['body']['limit']);
        $this->assertSame('renewable energy', $requests[0]['body']['query']);

        $this->assertSame(2, $requests[1]['body']['page']);
        $this->assertSame(2, $requests[1]['body']['limit']);

        $this->assertSame(3, $requests[2]['body']['page']);
        $this->assertSame(2, $requests[2]['body']['limit']);

        // Assert all 5 items were yielded in order
        $this->assertCount(5, $items);
        $this->assertSame('doc:1', $items[0]->externalId);
        $this->assertSame('doc:2', $items[1]->externalId);
        $this->assertSame('doc:3', $items[2]->externalId);
        $this->assertSame('doc:4', $items[3]->externalId);
        $this->assertSame('doc:5', $items[4]->externalId);
    }

    public function testSearchAllScenario1Yields1000Items(): void
    {
        $requests = [];
        $totalItems = 1000;
        $limit = 100;
        $totalPages = 10; // 10 pages of 100 items + 1 empty page to terminate

        $callback = function (string $method, string $url, array $options) use (&$requests, $limit, $totalPages): MockResponse {
            $body = json_decode((string) ($options['body'] ?? '{}'), true);
            $requests[] = $body;
            $requestedPage = (int) ($body['page'] ?? 1);

            $results = [];
            if ($requestedPage <= $totalPages) {
                $start = ($requestedPage - 1) * $limit;
                for ($i = 0; $i < $limit; $i++) {
                    $index = $start + $i + 1;
                    $results[] = [
                        'external_id' => "doc:{$index}",
                        'title' => "Document {$index}",
                        'content' => "Content for document {$index}",
                        'chunk_index' => 0,
                        'score' => 0.9,
                    ];
                }
            }

            return new MockResponse((string) json_encode([
                'results' => $results,
                'total' => 1000,
                'page' => $requestedPage,
                'limit' => $limit,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('bulk query')->withLimit($limit);
        $generator = $kineti->searchAll($query);

        $this->assertInstanceOf(\Generator::class, $generator);

        $yieldedCount = 0;
        foreach ($generator as $item) {
            $this->assertInstanceOf(SearchResultItemDto::class, $item);
            $yieldedCount++;
        }

        $this->assertSame(1000, $yieldedCount);
        // 10 full pages + 1 final page returning 0 items (0 < 100)
        $this->assertCount(11, $requests);
        $this->assertSame(1, $requests[0]['page']);
        $this->assertSame(10, $requests[9]['page']);
        $this->assertSame(11, $requests[10]['page']);
    }

    public function testScenario2SettingExplicitLimitsAndPageInSearch(): void
    {
        $capturedRequest = null;
        $mockResponse = new MockResponse((string) json_encode([
            'results' => [
                ['external_id' => 'doc:51', 'title' => 'Page 2 Doc', 'content' => 'Text', 'chunk_index' => 0, 'score' => 0.88],
            ],
            'total' => 100,
            'page' => 2,
            'limit' => 50,
        ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);

        $callback = function (string $method, string $url, array $options) use (&$capturedRequest, $mockResponse): MockResponse {
            $capturedRequest = [
                'method' => $method,
                'url' => $url,
                'body' => json_decode((string) ($options['body'] ?? '{}'), true),
            ];
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        // Given SearchQueryDto::create('query')->setLimit(50)->setPage(2)
        $query = SearchQueryDto::create('query')->setLimit(50)->setPage(2);

        // When calling KinetiClient::search($query)
        $response = $kineti->search($query);

        // Then the request payload accurately reflects the requested page and limit
        $this->assertNotNull($capturedRequest);
        $this->assertSame('POST', $capturedRequest['method']);
        $this->assertSame('query', $capturedRequest['body']['query']);
        $this->assertSame(50, $capturedRequest['body']['limit']);
        $this->assertSame(2, $capturedRequest['body']['page']);

        $this->assertSame(2, $response->getPage());
        $this->assertSame(50, $response->getLimit());
    }

    public function testSearchAllSinglePageWhenResultsFewerThanLimit(): void
    {
        $requests = [];
        $mockResponse = new MockResponse((string) json_encode([
            'results' => [
                ['external_id' => 'doc:1', 'title' => 'Doc 1', 'content' => 'Content 1', 'chunk_index' => 0, 'score' => 0.99],
                ['external_id' => 'doc:2', 'title' => 'Doc 2', 'content' => 'Content 2', 'chunk_index' => 0, 'score' => 0.92],
            ],
            'total' => 2,
            'page' => 1,
            'limit' => 10,
        ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);

        $callback = function (string $method, string $url, array $options) use (&$requests, $mockResponse): MockResponse {
            $requests[] = $options;
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('quick query')->withLimit(10);
        $items = iterator_to_array($kineti->searchAll($query), false);

        // Terminated immediately after page 1 because 2 < 10
        $this->assertCount(1, $requests);
        $this->assertCount(2, $items);
    }

    public function testSearchAllEmptyResults(): void
    {
        $requests = [];
        $mockResponse = new MockResponse((string) json_encode([
            'results' => [],
            'total' => 0,
            'page' => 1,
            'limit' => 10,
        ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);

        $callback = function (string $method, string $url, array $options) use (&$requests, $mockResponse): MockResponse {
            $requests[] = $options;
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $items = iterator_to_array($kineti->searchAll('empty results'), false);

        $this->assertCount(1, $requests);
        $this->assertEmpty($items);
    }

    public function testSearchAllAcceptsStringQuery(): void
    {
        $requests = [];
        $mockResponse = new MockResponse((string) json_encode([
            'results' => [
                ['external_id' => 'doc:1', 'title' => 'Doc 1', 'content' => 'Content', 'chunk_index' => 0, 'score' => 0.9],
            ],
            'total' => 1,
        ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);

        $callback = function (string $method, string $url, array $options) use (&$requests, $mockResponse): MockResponse {
            $requests[] = json_decode((string) ($options['body'] ?? '{}'), true);
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $items = iterator_to_array($kineti->searchAll('plain string query'), false);

        $this->assertCount(1, $requests);
        $this->assertSame('plain string query', $requests[0]['query']);
        $this->assertSame(1, $requests[0]['page']);
        $this->assertSame(10, $requests[0]['limit']);
        $this->assertCount(1, $items);
    }

    public function testSearchAllCustomStartingPageAndLimit(): void
    {
        $requests = [];
        $responses = [
            // Starting at page 3, returns 2 items (limit 2)
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:5', 'title' => 'Doc 5', 'content' => 'C5', 'chunk_index' => 0, 'score' => 0.8],
                    ['external_id' => 'doc:6', 'title' => 'Doc 6', 'content' => 'C6', 'chunk_index' => 0, 'score' => 0.7],
                ],
                'total' => 7,
                'page' => 3,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
            // Page 4: 1 item (< limit 2, terminates)
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:7', 'title' => 'Doc 7', 'content' => 'C7', 'chunk_index' => 0, 'score' => 0.6],
                ],
                'total' => 7,
                'page' => 4,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
        ];

        $callback = function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = json_decode((string) ($options['body'] ?? '{}'), true);
            $response = array_shift($responses);
            $this->assertNotNull($response, 'Unexpected extra network call made.');

            return $response;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('starting offset')->withPage(3)->withLimit(2);
        $items = iterator_to_array($kineti->searchAll($query), false);

        $this->assertCount(2, $requests);
        $this->assertSame(3, $requests[0]['page']);
        $this->assertSame(2, $requests[0]['limit']);
        $this->assertSame(4, $requests[1]['page']);
        $this->assertSame(2, $requests[1]['limit']);

        $this->assertCount(3, $items);
        $this->assertSame('doc:5', $items[0]->externalId);
        $this->assertSame('doc:6', $items[1]->externalId);
        $this->assertSame('doc:7', $items[2]->externalId);
    }

    public function testSearchAllClearsInputOffsetToPreventStaticOffsetAcrossPages(): void
    {
        $requests = [];
        $responses = [
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:1', 'title' => 'Doc 1', 'content' => 'C1', 'chunk_index' => 0, 'score' => 0.9],
                    ['external_id' => 'doc:2', 'title' => 'Doc 2', 'content' => 'C2', 'chunk_index' => 0, 'score' => 0.8],
                ],
                'total' => 3,
                'page' => 1,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:3', 'title' => 'Doc 3', 'content' => 'C3', 'chunk_index' => 0, 'score' => 0.7],
                ],
                'total' => 3,
                'page' => 2,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
        ];

        $callback = function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = json_decode((string) ($options['body'] ?? '{}'), true);
            $response = array_shift($responses);
            $this->assertNotNull($response, 'Unexpected extra network call made.');

            return $response;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        // Given a query with an offset set
        $query = SearchQueryDto::create('solar energy')->withOffset(20)->withLimit(2);
        $items = iterator_to_array($kineti->searchAll($query), false);

        $this->assertCount(2, $requests);
        // Verify page-based iteration occurs without offset being sent
        $this->assertSame(1, $requests[0]['page']);
        $this->assertArrayNotHasKey('offset', $requests[0]);
        $this->assertSame(2, $requests[1]['page']);
        $this->assertArrayNotHasKey('offset', $requests[1]);

        $this->assertCount(3, $items);
    }
}
