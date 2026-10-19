<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Client;

use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\KinetiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SearchPaginationTest extends TestCase
{
    public function testSearchAllAutoPaginatesAcrossThreePages(): void
    {
        /** @var list<array{method: string, url: string, body: array<string, mixed>}> $requests */
        $requests = [];
        $responses = [
            // Page 1: 2 items (full page), offset 0
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:1', 'title' => 'Document 1', 'content' => 'Content 1', 'chunk_index' => 0, 'score' => 0.95],
                    ['external_id' => 'doc:2', 'title' => 'Document 2', 'content' => 'Content 2', 'chunk_index' => 0, 'score' => 0.90],
                ],
                'total' => 2,
                'has_more' => true,
                'next_offset' => 2,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
            // Page 2: 2 items (full page), offset 2
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:3', 'title' => 'Document 3', 'content' => 'Content 3', 'chunk_index' => 0, 'score' => 0.85],
                    ['external_id' => 'doc:4', 'title' => 'Document 4', 'content' => 'Content 4', 'chunk_index' => 0, 'score' => 0.80],
                ],
                'total' => 2,
                'has_more' => true,
                'next_offset' => 4,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
            // Page 3: 1 item (< limit of 2, marks last page), offset 4
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:5', 'title' => 'Document 5', 'content' => 'Content 5', 'chunk_index' => 0, 'score' => 0.75],
                ],
                'total' => 1,
                'has_more' => false,
                'next_offset' => null,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
        ];

        $callback = function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $body = json_decode((string) ($options['body'] ?? '{}'), true);
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'body' => is_array($body) ? $body : [],
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

        // Verify request payloads: offset progression without page
        $this->assertSame(0, $requests[0]['body']['offset']);
        $this->assertSame(2, $requests[0]['body']['limit']);
        $this->assertSame('renewable energy', $requests[0]['body']['query']);
        $this->assertArrayNotHasKey('page', $requests[0]['body']);

        $this->assertSame(2, $requests[1]['body']['offset']);
        $this->assertSame(2, $requests[1]['body']['limit']);
        $this->assertArrayNotHasKey('page', $requests[1]['body']);

        $this->assertSame(4, $requests[2]['body']['offset']);
        $this->assertSame(2, $requests[2]['body']['limit']);
        $this->assertArrayNotHasKey('page', $requests[2]['body']);

        // Assert all 5 items were yielded in order
        $this->assertCount(5, $items);
        $this->assertSame('doc:1', $items[0]->externalId);
        $this->assertSame('doc:2', $items[1]->externalId);
        $this->assertSame('doc:3', $items[2]->externalId);
        $this->assertSame('doc:4', $items[3]->externalId);
        $this->assertSame('doc:5', $items[4]->externalId);
    }

    public function testSearchAllScenario2MultiPageOffsetIteration(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $responses = [
            // Page 1: 10 items, has_more: true, next_offset: 10, total: 10
            new MockResponse((string) json_encode([
                'results' => array_map(static fn (int $i): array => [
                    'external_id' => "doc:{$i}",
                    'title' => "Doc {$i}",
                    'content' => "Content {$i}",
                    'chunk_index' => 0,
                    'score' => 0.9,
                ], range(1, 10)),
                'total' => 10,
                'has_more' => true,
                'next_offset' => 10,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
            // Page 2: 4 items, has_more: false, next_offset: null, total: 4
            new MockResponse((string) json_encode([
                'results' => array_map(static fn (int $i): array => [
                    'external_id' => "doc:{$i}",
                    'title' => "Doc {$i}",
                    'content' => "Content {$i}",
                    'chunk_index' => 0,
                    'score' => 0.8,
                ], range(11, 14)),
                'total' => 4,
                'has_more' => false,
                'next_offset' => null,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
        ];

        $callback = function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $decoded = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $requests[] = $decoded;
            }
            $response = array_shift($responses);
            $this->assertNotNull($response, 'Unexpected extra network call made.');

            return $response;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('scenario 2')->withLimit(10);
        $items = iterator_to_array($kineti->searchAll($query), false);

        $this->assertCount(2, $requests);
        $this->assertSame(0, $requests[0]['offset']);
        $this->assertSame(10, $requests[0]['limit']);
        $this->assertArrayNotHasKey('page', $requests[0]);

        $this->assertSame(10, $requests[1]['offset']);
        $this->assertSame(10, $requests[1]['limit']);
        $this->assertArrayNotHasKey('page', $requests[1]);

        $this->assertCount(14, $items);
        $this->assertSame('doc:1', $items[0]->externalId);
        $this->assertSame('doc:14', $items[13]->externalId);
    }

    public function testSearchAllScenario3RespectsMaxOffsetBoundary1000(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $mockResponse = new MockResponse((string) json_encode([
            'results' => [
                ['external_id' => 'doc:1000', 'title' => 'Doc 1000', 'content' => 'Content', 'chunk_index' => 0, 'score' => 0.9],
            ],
            'total' => 1,
            'has_more' => true,
            'next_offset' => 1050, // Exceeds backend max offset (1000)
        ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);

        $callback = function (string $method, string $url, array $options) use (&$requests, $mockResponse): MockResponse {
            $decoded = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $requests[] = $decoded;
            }
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('boundary test')->withOffset(950)->withLimit(50);
        $items = iterator_to_array($kineti->searchAll($query), false);

        // First request at offset 950 was executed
        $this->assertCount(1, $requests);
        $this->assertSame(950, $requests[0]['offset']);
        $this->assertCount(1, $items);
        // Did not dispatch subsequent request at offset 1050

        // Test starting with offset exceeding 1000 returns immediately
        $excessiveQuery = SearchQueryDto::create('excessive')->withOffset(1001);
        $itemsExcessive = iterator_to_array($kineti->searchAll($excessiveQuery), false);
        $this->assertCount(0, $itemsExcessive);
        // No additional request dispatched
        $this->assertCount(1, $requests);
    }

    public function testSearchAllInfiniteLoopPreventionWhenOffsetDoesNotAdvance(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $mockResponse = new MockResponse((string) json_encode([
            'results' => [
                ['external_id' => 'doc:1', 'title' => 'Doc 1', 'content' => 'Content', 'chunk_index' => 0, 'score' => 0.9],
                ['external_id' => 'doc:2', 'title' => 'Doc 2', 'content' => 'Content', 'chunk_index' => 0, 'score' => 0.8],
            ],
            'total' => 2,
            'has_more' => true,
            'next_offset' => 0, // Malformed: next_offset <= current offset (0)
        ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);

        $callback = function (string $method, string $url, array $options) use (&$requests, $mockResponse): MockResponse {
            $decoded = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $requests[] = $decoded;
            }
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $items = iterator_to_array($kineti->searchAll(SearchQueryDto::create('infinite loop test')->withLimit(2)), false);

        // Loop halts after first page rather than repeating offset 0
        $this->assertCount(1, $requests);
        $this->assertCount(2, $items);
    }

    public function testSearchAllFallbackGracefullyForLegacyPayloadWithoutTopKMetadata(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $responses = [
            // Page 1: 2 items, limit 2, total 3 (omits has_more and next_offset)
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:1', 'title' => 'Doc 1', 'content' => 'C1', 'chunk_index' => 0, 'score' => 0.9],
                    ['external_id' => 'doc:2', 'title' => 'Doc 2', 'content' => 'C2', 'chunk_index' => 0, 'score' => 0.8],
                ],
                'total' => 3,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
            // Page 2: 1 item, limit 2, total 3 (omits has_more and next_offset)
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:3', 'title' => 'Doc 3', 'content' => 'C3', 'chunk_index' => 0, 'score' => 0.7],
                ],
                'total' => 3,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
        ];

        $callback = function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $decoded = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $requests[] = $decoded;
            }
            $response = array_shift($responses);
            $this->assertNotNull($response, 'Unexpected extra network call made.');

            return $response;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('legacy payload')->withLimit(2);
        $items = iterator_to_array($kineti->searchAll($query), false);

        // Fallback advances by offset + limit
        $this->assertCount(2, $requests);
        $this->assertSame(0, $requests[0]['offset']);
        $this->assertSame(2, $requests[1]['offset']);
        $this->assertCount(3, $items);
    }

    public function testSearchAllScenario1Yields1000Items(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $limit = 100;
        $totalPages = 10; // 10 pages of 100 items = 1000 items

        $callback = function (string $method, string $url, array $options) use (&$requests, $limit, $totalPages): MockResponse {
            $body = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($body)) {
                /** @var array<string, mixed> $body */
                $requests[] = $body;
            } else {
                $body = [];
            }
            $rawOffset = $body['offset'] ?? 0;
            $currentOffset = is_numeric($rawOffset) ? (int) $rawOffset : 0;
            $pageIndex = (int) ($currentOffset / $limit) + 1;

            $results = [];
            if ($pageIndex <= $totalPages) {
                for ($i = 0; $i < $limit; $i++) {
                    $index = $currentOffset + $i + 1;
                    $results[] = [
                        'external_id' => "doc:{$index}",
                        'title' => "Document {$index}",
                        'content' => "Content for document {$index}",
                        'chunk_index' => 0,
                        'score' => 0.9,
                    ];
                }
            }

            $hasMore = $pageIndex < $totalPages;
            $nextOffset = $hasMore ? $currentOffset + $limit : null;

            return new MockResponse((string) json_encode([
                'results' => $results,
                'total' => count($results),
                'has_more' => $hasMore,
                'next_offset' => $nextOffset,
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
        $this->assertCount(10, $requests);
        $this->assertSame(0, $requests[0]['offset']);
        $this->assertSame(900, $requests[9]['offset']);
    }

    public function testScenario2SettingExplicitLimitsAndPageInSearch(): void
    {
        /** @var array{method: string, url: string, body: array<string, mixed>}|null $capturedRequest */
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
            $body = json_decode((string) ($options['body'] ?? '{}'), true);
            $capturedRequest = [
                'method' => $method,
                'url' => $url,
                'body' => is_array($body) ? $body : [],
            ];
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        // Given SearchQueryDto::create('query')->setLimit(50)->setOffset(50)
        $query = SearchQueryDto::create('query')->setLimit(50)->setOffset(50);

        // When calling KinetiClient::search($query)
        $response = $kineti->search($query);

        // Then the request payload accurately reflects the requested offset and limit
        $this->assertNotNull($capturedRequest);
        $this->assertSame('POST', $capturedRequest['method']);
        $this->assertSame('query', $capturedRequest['body']['query']);
        $this->assertSame(50, $capturedRequest['body']['limit']);
        $this->assertSame(50, $capturedRequest['body']['offset']);

        $this->assertSame(2, $response->getPage());
        $this->assertSame(50, $response->getLimit());
    }

    public function testSearchAllSinglePageWhenResultsFewerThanLimit(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $mockResponse = new MockResponse((string) json_encode([
            'results' => [
                ['external_id' => 'doc:1', 'title' => 'Doc 1', 'content' => 'Content 1', 'chunk_index' => 0, 'score' => 0.99],
                ['external_id' => 'doc:2', 'title' => 'Doc 2', 'content' => 'Content 2', 'chunk_index' => 0, 'score' => 0.92],
            ],
            'total' => 2,
            'has_more' => false,
            'next_offset' => null,
            'limit' => 10,
        ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);

        $callback = function (string $method, string $url, array $options) use (&$requests, $mockResponse): MockResponse {
            $decoded = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $requests[] = $decoded;
            }
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('quick query')->withLimit(10);
        $items = iterator_to_array($kineti->searchAll($query), false);

        // Terminated immediately after page 1 because 2 < 10
        $this->assertCount(1, $requests);
        $this->assertSame(0, $requests[0]['offset']);
        $this->assertCount(2, $items);
    }

    public function testSearchAllEmptyResults(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $mockResponse = new MockResponse((string) json_encode([
            'results' => [],
            'total' => 0,
            'has_more' => false,
            'next_offset' => null,
            'limit' => 10,
        ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);

        $callback = function (string $method, string $url, array $options) use (&$requests, $mockResponse): MockResponse {
            $decoded = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $requests[] = $decoded;
            }
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $items = iterator_to_array($kineti->searchAll('empty results'), false);

        $this->assertCount(1, $requests);
        $this->assertSame(0, $requests[0]['offset']);
        $this->assertEmpty($items);
    }

    public function testSearchAllAcceptsStringQuery(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $mockResponse = new MockResponse((string) json_encode([
            'results' => [
                ['external_id' => 'doc:1', 'title' => 'Doc 1', 'content' => 'Content', 'chunk_index' => 0, 'score' => 0.9],
            ],
            'total' => 1,
            'has_more' => false,
            'next_offset' => null,
        ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]);

        $callback = function (string $method, string $url, array $options) use (&$requests, $mockResponse): MockResponse {
            $decoded = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $requests[] = $decoded;
            }
            return $mockResponse;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $items = iterator_to_array($kineti->searchAll('plain string query'), false);

        $this->assertCount(1, $requests);
        $this->assertSame('plain string query', $requests[0]['query']);
        $this->assertSame(0, $requests[0]['offset']);
        $this->assertSame(10, $requests[0]['limit']);
        $this->assertArrayNotHasKey('page', $requests[0]);
        $this->assertCount(1, $items);
    }

    public function testSearchAllCustomStartingOffsetAndLimit(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $responses = [
            // Starting at offset 20, returns 2 items
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:21', 'title' => 'Doc 21', 'content' => 'C21', 'chunk_index' => 0, 'score' => 0.8],
                    ['external_id' => 'doc:22', 'title' => 'Doc 22', 'content' => 'C22', 'chunk_index' => 0, 'score' => 0.7],
                ],
                'total' => 2,
                'has_more' => true,
                'next_offset' => 22,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
            // Offset 22: 1 item (< limit 2, terminates)
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:23', 'title' => 'Doc 23', 'content' => 'C23', 'chunk_index' => 0, 'score' => 0.6],
                ],
                'total' => 1,
                'has_more' => false,
                'next_offset' => null,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
        ];

        $callback = function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $decoded = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $requests[] = $decoded;
            }
            $response = array_shift($responses);
            $this->assertNotNull($response, 'Unexpected extra network call made.');

            return $response;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('starting offset')->withOffset(20)->withLimit(2);
        $items = iterator_to_array($kineti->searchAll($query), false);

        $this->assertCount(2, $requests);
        $this->assertSame(20, $requests[0]['offset']);
        $this->assertSame(2, $requests[0]['limit']);
        $this->assertArrayNotHasKey('page', $requests[0]);

        $this->assertSame(22, $requests[1]['offset']);
        $this->assertSame(2, $requests[1]['limit']);
        $this->assertArrayNotHasKey('page', $requests[1]);

        $this->assertCount(3, $items);
        $this->assertSame('doc:21', $items[0]->externalId);
        $this->assertSame('doc:22', $items[1]->externalId);
        $this->assertSame('doc:23', $items[2]->externalId);
    }

    public function testSearchAllStartingOffsetComputesCorrectFirstWindow(): void
    {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        $responses = [
            new MockResponse((string) json_encode([
                'results' => [
                    ['external_id' => 'doc:5', 'title' => 'Doc 5', 'content' => 'C5', 'chunk_index' => 0, 'score' => 0.8],
                ],
                'total' => 1,
                'has_more' => false,
                'next_offset' => null,
                'limit' => 2,
            ], JSON_THROW_ON_ERROR), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']]),
        ];

        $callback = function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $decoded = json_decode((string) ($options['body'] ?? '{}'), true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $requests[] = $decoded;
            }
            $response = array_shift($responses);
            $this->assertNotNull($response, 'Unexpected extra network call made.');

            return $response;
        };

        $client = new MockHttpClient($callback);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('offset query')->withOffset(4)->withLimit(2);
        $items = iterator_to_array($kineti->searchAll($query), false);

        $this->assertCount(1, $requests);
        $this->assertSame(4, $requests[0]['offset']);
        $this->assertSame(2, $requests[0]['limit']);
        $this->assertArrayNotHasKey('page', $requests[0]);

        $this->assertCount(1, $items);
        $this->assertSame('doc:5', $items[0]->externalId);
    }
}
