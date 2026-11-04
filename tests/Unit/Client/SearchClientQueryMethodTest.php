<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Unit\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use KinetiStack\Sdk\Client\RagClient;
use KinetiStack\Sdk\Client\RagClientInterface;
use KinetiStack\Sdk\Client\SearchClient;
use KinetiStack\Sdk\Client\SearchClientInterface;
use KinetiStack\Sdk\Configuration;
use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Dto\RagSynthesisDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\KinetiClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SearchClientQueryMethodTest extends TestCase
{
    private function createSampleSearchResponseBody(): string
    {
        return (string) json_encode([
            'results' => [
                [
                    'external_id' => 'doc:1',
                    'title' => 'Test Document 1',
                    'content' => 'Content 1',
                    'score' => 0.95,
                    'chunk_index' => 0,
                    'metadata' => ['key' => 'val'],
                ],
            ],
            'total' => 1,
            'page' => 1,
            'limit' => 10,
            'has_more' => false,
        ], JSON_THROW_ON_ERROR);
    }

    private function createSampleRagResponseBody(): string
    {
        return (string) json_encode([
            'answer' => 'Synthesized RAG answer.',
            'citations' => ['doc:1'],
            'successful' => true,
            'tokens_consumed' => 42,
        ], JSON_THROW_ON_ERROR);
    }

    private function createSampleSseStreamBody(): string
    {
        return "data: {\"chunk\": \"First chunk \"}\n\ndata: {\"chunk\": \"second chunk\"}\n\nevent: done\ndata: [DONE]\n\n";
    }

    // =========================================================================
    // Configuration Tests
    // =========================================================================

    public function testConfigurationDefaultsAndImmutability(): void
    {
        $defaultConfig = new Configuration();
        $this->assertTrue($defaultConfig->useHttpQueryMethod());
        $this->assertSame([], $defaultConfig->getHttpOptions());

        $disabledConfig = $defaultConfig->withHttpQueryMethod(false);
        $this->assertTrue($defaultConfig->useHttpQueryMethod(), 'Original must be immutable');
        $this->assertFalse($disabledConfig->useHttpQueryMethod());

        $reEnabledConfig = $disabledConfig->withHttpQueryMethod(true);
        $this->assertTrue($reEnabledConfig->useHttpQueryMethod());
    }

    public function testConfigurationFromOptionsVariations(): void
    {
        $configSnake = Configuration::fromOptions(['use_http_query_method' => false]);
        $this->assertFalse($configSnake->useHttpQueryMethod());

        $configCamel = Configuration::fromOptions(['useHttpQueryMethod' => false]);
        $this->assertFalse($configCamel->useHttpQueryMethod());

        $configExisting = Configuration::fromOptions($configSnake);
        $this->assertSame($configSnake, $configExisting);

        $configEmpty = Configuration::fromOptions([]);
        $this->assertTrue($configEmpty->useHttpQueryMethod());

        $configWithOptions = Configuration::fromOptions([
            'use_http_query_method' => true,
            'timeout' => 30,
            'headers' => ['Custom' => 'value'],
        ]);
        $this->assertTrue($configWithOptions->useHttpQueryMethod());
        $this->assertSame(['timeout' => 30, 'headers' => ['Custom' => 'value']], $configWithOptions->getHttpOptions());

        $array = $configWithOptions->toArray();
        $this->assertTrue($array['use_http_query_method']);
        $this->assertSame(30, $array['timeout']);

        $updatedHttp = $configWithOptions->withHttpOptions(['timeout' => 60]);
        $this->assertSame(['timeout' => 60], $updatedHttp->getHttpOptions());
        $this->assertSame(['timeout' => 30, 'headers' => ['Custom' => 'value']], $configWithOptions->getHttpOptions());
    }

    // =========================================================================
    // Scenario 1: Search request dispatches HTTP QUERY by default
    // =========================================================================

    public function testScenario1SearchDispatchesHttpQueryByDefaultWithSymfony(): void
    {
        $mockResponse = new MockResponse($this->createSampleSearchResponseBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $searchClient = $client->search();
        $this->assertInstanceOf(SearchClient::class, $searchClient);
        $this->assertInstanceOf(SearchClientInterface::class, $searchClient);

        $dto = SearchQueryDto::create('RFC 10008 HTTP QUERY test')->withLimit(5);
        $response = $searchClient->query($dto);

        $this->assertInstanceOf(SearchResponseDto::class, $response);
        $this->assertSame('QUERY', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/search/query', $mockResponse->getRequestUrl());

        $requestBody = json_decode((string) $mockResponse->getRequestOptions()['body'], true);
        $this->assertIsArray($requestBody);
        $this->assertSame('RFC 10008 HTTP QUERY test', $requestBody['query']);
        $this->assertSame(5, $requestBody['limit']);
    }

    public function testScenario1SearchDispatchesHttpQueryByDefaultWithGuzzlePsr18(): void
    {
        /** @var array<int, array{request: RequestInterface, response: \Psr\Http\Message\ResponseInterface|null, error: mixed, options: array<string, mixed>}> $history */
        $history = [];
        $mock = new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], $this->createSampleSearchResponseBody()),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        $client = new KinetiClient('https://api.test', 'test-api-key', $guzzle);

        // Test backwards-compatible $client->search($query) delegation
        $response = $client->search('guzzle query test');

        $this->assertInstanceOf(SearchResponseDto::class, $response);
        $this->assertIsArray($history);
        $this->assertCount(1, $history);
        $sentRequest = $history[0]['request'];
        $this->assertSame('QUERY', $sentRequest->getMethod());
        $this->assertSame('https://api.test/api/v1/search/query', (string) $sentRequest->getUri());

        $requestBody = json_decode((string) $sentRequest->getBody(), true);
        $this->assertIsArray($requestBody);
        $this->assertSame('guzzle query test', $requestBody['query']);
    }

    public function testScenario1SearchAliasAndAllMethodsDispatchHttpQuery(): void
    {
        $mockResponse1 = new MockResponse($this->createSampleSearchResponseBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $mockResponse2 = new MockResponse($this->createSampleSearchResponseBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $symfonyClient = new MockHttpClient([$mockResponse1, $mockResponse2]);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        // Test search() alias
        $searchResponse = $client->search()->search('alias query');
        $this->assertInstanceOf(SearchResponseDto::class, $searchResponse);
        $this->assertSame('QUERY', $mockResponse1->getRequestMethod());

        // Test all() pagination generator
        $items = iterator_to_array($client->search()->all('all query'));
        $this->assertCount(1, $items);
        $this->assertInstanceOf(SearchResultItemDto::class, $items[0]);
        $this->assertSame('QUERY', $mockResponse2->getRequestMethod());
    }

    public function testScenario1SearchStreamDispatchesHttpQuery(): void
    {
        $mockResponse = new MockResponse($this->createSampleSseStreamBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $generator = $client->search()->stream('streaming query');
        $this->assertInstanceOf(\Generator::class, $generator);

        /** @var RagStreamChunkDto[] $chunks */
        $chunks = iterator_to_array($generator);
        $this->assertCount(2, $chunks);
        $this->assertSame('First chunk ', $chunks[0]->text);
        $this->assertSame('second chunk', $chunks[1]->text);

        $this->assertSame('QUERY', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/rag/query', $mockResponse->getRequestUrl());
    }

    public function testScenario1StreamWithExplicitLegacyEndpointDispatchesHttpQueryWithSymfony(): void
    {
        $mockResponse = new MockResponse($this->createSampleSseStreamBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $generator = $client->search()->stream('streaming query', '/api/v1/search/rag');
        $this->assertInstanceOf(\Generator::class, $generator);

        /** @var RagStreamChunkDto[] $chunks */
        $chunks = iterator_to_array($generator);
        $this->assertCount(2, $chunks);

        $this->assertSame('QUERY', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/search/rag', $mockResponse->getRequestUrl());
    }

    // =========================================================================
    // Scenario 2: RAG request dispatches HTTP QUERY by default
    // =========================================================================

    public function testScenario2RagDispatchesHttpQueryByDefaultWithSymfony(): void
    {
        $mockResponse = new MockResponse($this->createSampleRagResponseBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $ragClient = $client->rag();
        $this->assertInstanceOf(RagClient::class, $ragClient);
        $this->assertInstanceOf(RagClientInterface::class, $ragClient);

        $result = $ragClient->query('What is RFC 10008?');

        $this->assertInstanceOf(RagSynthesisDto::class, $result);
        $this->assertSame('Synthesized RAG answer.', $result->answer);
        $this->assertSame(['doc:1'], $result->citations);
        $this->assertTrue($result->successful);
        $this->assertSame(42, $result->tokensConsumed);

        $this->assertSame('QUERY', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/rag/query', $mockResponse->getRequestUrl());

        $requestBody = json_decode((string) $mockResponse->getRequestOptions()['body'], true);
        $this->assertIsArray($requestBody);
        $this->assertSame('What is RFC 10008?', $requestBody['query']);
    }

    public function testScenario2RagDispatchesHttpQueryByDefaultWithGuzzlePsr18(): void
    {
        /** @var array<int, array{request: RequestInterface, response: \Psr\Http\Message\ResponseInterface|null, error: mixed, options: array<string, mixed>}> $history */
        $history = [];
        $mock = new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], $this->createSampleRagResponseBody()),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        $client = new KinetiClient('https://api.test', 'test-api-key', $guzzle);

        $result = $client->rag()->query('What is KinetiStack?');

        $this->assertInstanceOf(RagSynthesisDto::class, $result);
        $this->assertIsArray($history);
        $this->assertCount(1, $history);
        $sentRequest = $history[0]['request'];
        $this->assertSame('QUERY', $sentRequest->getMethod());
        $this->assertSame('https://api.test/api/v1/rag/query', (string) $sentRequest->getUri());
    }

    public function testScenario2RagStreamingQueryDispatchesHttpQuery(): void
    {
        $mockResponse = new MockResponse($this->createSampleSseStreamBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        // When streaming query DTO is passed to rag()->query()
        $queryDto = SearchQueryDto::create('streaming RAG question')->withStream(true);
        $generator = $client->rag()->query($queryDto);

        $this->assertInstanceOf(\Generator::class, $generator);
        /** @var RagStreamChunkDto[] $chunks */
        $chunks = iterator_to_array($generator);
        $this->assertCount(2, $chunks);
        $this->assertSame('QUERY', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/rag/query', $mockResponse->getRequestUrl());
    }

    public function testScenario2RagStreamMethodDispatchesHttpQuery(): void
    {
        /** @var array<int, array{request: RequestInterface, response: \Psr\Http\Message\ResponseInterface|null, error: mixed, options: array<string, mixed>}> $history */
        $history = [];
        $mock = new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'text/event-stream'], $this->createSampleSseStreamBody()),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        $client = new KinetiClient('https://api.test', 'test-api-key', $guzzle);

        $generator = $client->rag()->stream('stream question');
        $this->assertInstanceOf(\Generator::class, $generator);

        /** @var RagStreamChunkDto[] $chunks */
        $chunks = iterator_to_array($generator);
        $this->assertCount(2, $chunks);

        $this->assertIsArray($history);
        $this->assertCount(1, $history);
        $this->assertSame('QUERY', $history[0]['request']->getMethod());
        $this->assertSame('https://api.test/api/v1/rag/query', (string) $history[0]['request']->getUri());
    }

    public function testRagQueryWithResultsEnvelopeReturnsSearchResponseDto(): void
    {
        $payload = (string) json_encode([
            'results' => [
                [
                    'external_id' => 'doc:100',
                    'title' => 'Doc Title',
                    'content' => 'Doc content',
                    'score' => 0.88,
                    'chunk_index' => 0,
                ],
            ],
            'total' => 1,
            'page' => 1,
            'limit' => 10,
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($payload, ['http_code' => 200]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $result = $client->rag()->query('Find relevant docs');

        $this->assertInstanceOf(SearchResponseDto::class, $result);
        $this->assertSame(1, $result->total);
        $this->assertCount(1, $result->results);
        $this->assertSame('doc:100', $result->results[0]->externalId);
    }

    public function testRagQueryWithMemberEnvelopeReturnsSearchResponseDto(): void
    {
        $payload = (string) json_encode([
            'member' => [
                [
                    'external_id' => 'doc:200',
                    'title' => 'Hydra Doc Title',
                    'content' => 'Hydra Doc content',
                    'score' => 0.77,
                    'chunk_index' => 0,
                ],
            ],
            'total' => 1,
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($payload, ['http_code' => 200]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $result = $client->rag()->query('Find hydra docs');

        $this->assertInstanceOf(SearchResponseDto::class, $result);
        $this->assertCount(1, $result->results);
        $this->assertSame('doc:200', $result->results[0]->externalId);
    }

    public function testRagQueryWithNestedSynthesisEnvelopeReturnsRagSynthesisDto(): void
    {
        $payload = (string) json_encode([
            'synthesis' => [
                'answer' => 'Synthesized nested answer.',
                'citations' => ['doc:300', 'doc:301'],
                'successful' => true,
                'tokens_consumed' => 88,
            ],
            'status' => 'completed',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($payload, ['http_code' => 200]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $result = $client->rag()->query('Ask a question with nested synthesis response');

        $this->assertInstanceOf(RagSynthesisDto::class, $result);
        $this->assertSame('Synthesized nested answer.', $result->answer);
        $this->assertSame(['doc:300', 'doc:301'], $result->citations);
        $this->assertTrue($result->successful);
        $this->assertSame(88, $result->tokensConsumed);
    }

    public function testRagQueryWithNullAnswerFailedSynthesisReturnsRagSynthesisDto(): void
    {
        $payload = (string) json_encode([
            'answer' => null,
            'citations' => [],
            'successful' => false,
            'fallback_reason' => 'LLM rate limit reached',
            'tokens_consumed' => 0,
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($payload, ['http_code' => 200]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $result = $client->rag()->query('Failed synthesis question');

        $this->assertInstanceOf(RagSynthesisDto::class, $result);
        $this->assertNull($result->answer);
        $this->assertSame([], $result->citations);
        $this->assertFalse($result->successful);
        $this->assertSame('LLM rate limit reached', $result->fallbackReason);
    }

    public function testRagQueryWithNestedSynthesisAndRootTokensConsumedPropagatesUsage(): void
    {
        $payload = (string) json_encode([
            'synthesis' => [
                'answer' => 'Answer without embedded tokens.',
                'citations' => ['doc:400'],
                'successful' => true,
            ],
            'tokens_consumed' => 150,
            'status' => 'completed',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($payload, ['http_code' => 200]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $result = $client->rag()->query('Question with root tokens');

        $this->assertInstanceOf(RagSynthesisDto::class, $result);
        $this->assertSame('Answer without embedded tokens.', $result->answer);
        $this->assertSame(['doc:400'], $result->citations);
        $this->assertSame(150, $result->tokensConsumed);
    }

    public function testRagQueryAggregatesSseEventStreamResponseIntoRagSynthesisDto(): void
    {
        $sseBody = "data: {\"chunk\":\"Perovskite \"}\n\n"
            . "data: {\"chunk\":\"solar cells.\"}\n\n"
            . "data: {\"is_done\":true,\"citations\":[\"doc:1\",\"doc:2\",\"123\"],\"tokens_consumed\":42}\n\n";

        $mockResponse = new MockResponse($sseBody, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $result = $client->rag()->query('What are the key advantages of perovskite solar cells?');

        $this->assertInstanceOf(RagSynthesisDto::class, $result);
        $this->assertSame('Perovskite solar cells.', $result->answer);
        $this->assertSame(['doc:1', 'doc:2', '123'], $result->citations);
        $this->assertTrue($result->successful);
        $this->assertSame(42, $result->tokensConsumed);
        $this->assertSame('QUERY', $mockResponse->getRequestMethod());
    }

    public function testRagQueryAggregatesSseBodyWithoutExplicitContentTypeHeader(): void
    {
        $sseBody = "data: {\"chunk\":\"Aggregated \"}\n\n"
            . "data: {\"chunk\":\"answer.\",\"citations\":[\"doc:10\"]}\n\n"
            . "data: {\"is_done\":true,\"citations\":[\"doc:10\",\"doc:20\"]}\n\n";

        $mock = new MockHandler([
            new GuzzleResponse(200, [], $sseBody),
        ]);
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $client = new KinetiClient('https://api.test', 'test-api-key', $guzzle);

        $result = $client->rag()->query('Unheadered SSE stream');

        $this->assertInstanceOf(RagSynthesisDto::class, $result);
        $this->assertSame('Aggregated answer.', $result->answer);
        $this->assertSame(['doc:10', 'doc:20'], $result->citations);
        $this->assertTrue($result->successful);
    }

    public function testRagQueryDoesNotSniffBodyAsSseWhenNonEventStreamContentTypeIsPresent(): void
    {
        $mockResponse = new MockResponse(':malformed json body', [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient);

        $this->expectException(TransportException::class);
        $client->rag()->query('Should fail JSON decoding instead of treating as SSE comment');
    }

    // =========================================================================
    // Scenario 3: Fallback configuration forces POST method for Search and RAG
    // =========================================================================

    public function testScenario3FallbackForcesPostForSearchWithSymfony(): void
    {
        $mockResponse = new MockResponse($this->createSampleSearchResponseBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient, [
            'use_http_query_method' => false,
        ]);

        $this->assertFalse($client->getConfiguration()->useHttpQueryMethod());

        $response = $client->search()->query('fallback search');

        $this->assertInstanceOf(SearchResponseDto::class, $response);
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/search/query', $mockResponse->getRequestUrl());
    }

    public function testScenario3FallbackForcesPostForSearchWithGuzzlePsr18(): void
    {
        /** @var array<int, array{request: RequestInterface, response: \Psr\Http\Message\ResponseInterface|null, error: mixed, options: array<string, mixed>}> $history */
        $history = [];
        $mock = new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], $this->createSampleSearchResponseBody()),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        $config = new Configuration(['useHttpQueryMethod' => false]);
        $client = new KinetiClient('https://api.test', 'test-api-key', $guzzle, $config);

        $response = $client->search('fallback search with guzzle');

        $this->assertInstanceOf(SearchResponseDto::class, $response);
        $this->assertIsArray($history);
        $this->assertCount(1, $history);
        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertSame('https://api.test/api/v1/search/query', (string) $history[0]['request']->getUri());
    }

    public function testScenario3FallbackForcesPostForRagWithSymfony(): void
    {
        $mockResponse = new MockResponse($this->createSampleRagResponseBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $symfonyClient = new MockHttpClient($mockResponse);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient, [
            'use_http_query_method' => false,
        ]);

        $result = $client->rag()->query('fallback rag question');

        $this->assertInstanceOf(RagSynthesisDto::class, $result);
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/rag/query', $mockResponse->getRequestUrl());
    }

    public function testScenario3FallbackForcesPostForRagWithGuzzlePsr18(): void
    {
        /** @var array<int, array{request: RequestInterface, response: \Psr\Http\Message\ResponseInterface|null, error: mixed, options: array<string, mixed>}> $history */
        $history = [];
        $mock = new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], $this->createSampleRagResponseBody()),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        $client = new KinetiClient('https://api.test', 'test-api-key', $guzzle, [
            'use_http_query_method' => false,
        ]);

        $result = $client->rag()->query('fallback rag question with guzzle');

        $this->assertInstanceOf(RagSynthesisDto::class, $result);
        $this->assertIsArray($history);
        $this->assertCount(1, $history);
        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertSame('https://api.test/api/v1/rag/query', (string) $history[0]['request']->getUri());
    }

    public function testScenario3FallbackForcesPostForStreamingSearchAndRag(): void
    {
        $searchMock = new MockResponse($this->createSampleSseStreamBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $ragMock = new MockResponse($this->createSampleSseStreamBody(), [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $symfonyClient = new MockHttpClient([$searchMock, $ragMock]);
        $client = new KinetiClient('https://api.test', 'test-api-key', $symfonyClient, [
            'use_http_query_method' => false,
        ]);

        // Search stream fallback
        $searchChunks = iterator_to_array($client->searchStream('streaming query'));
        $this->assertCount(2, $searchChunks);
        $this->assertSame('POST', $searchMock->getRequestMethod());

        // RAG stream fallback
        $ragChunks = iterator_to_array($client->rag()->stream('streaming rag'));
        $this->assertCount(2, $ragChunks);
        $this->assertSame('POST', $ragMock->getRequestMethod());
    }

    // =========================================================================
    // Client Methods & Immutability Tests
    // =========================================================================

    public function testKinetiClientAccessorsAndImmutability(): void
    {
        $symfonyClient = new MockHttpClient();
        $client = new KinetiClient('https://api.test', 'key1', $symfonyClient, ['use_http_query_method' => false]);

        $this->assertFalse($client->getConfiguration()->useHttpQueryMethod());
        $this->assertInstanceOf(SearchClient::class, $client->search());
        $this->assertInstanceOf(SearchClientInterface::class, $client->search());
        $this->assertInstanceOf(RagClient::class, $client->rag());
        $this->assertInstanceOf(RagClientInterface::class, $client->rag());

        $this->assertSame($client->getConfiguration(), $client->search()->getConfiguration());
        $this->assertSame($client->getTransport(), $client->search()->getTransport());
        $this->assertSame($client->getConfiguration(), $client->rag()->getConfiguration());
        $this->assertSame($client->getTransport(), $client->rag()->getTransport());

        // withApiKey preserves configuration
        $keyedClient = $client->withApiKey('key2');
        $this->assertFalse($keyedClient->getConfiguration()->useHttpQueryMethod());
        $this->assertNotSame($client, $keyedClient);

        // withConfiguration updates configuration
        $reconfigured = $keyedClient->withConfiguration(['use_http_query_method' => true]);
        $this->assertTrue($reconfigured->getConfiguration()->useHttpQueryMethod());
        $this->assertFalse($keyedClient->getConfiguration()->useHttpQueryMethod(), 'Original must be immutable');
    }

    public function testWithConfigurationPreservesExistingHttpOptionsAndUpdatesTransport(): void
    {
        $symfonyClient = new MockHttpClient();
        $client = new KinetiClient('https://api.test', 'key1', $symfonyClient, [
            'timeout' => 30.0,
            'max_retries' => 3,
            'use_http_query_method' => true,
        ]);

        $this->assertSame(30.0, $client->getConfiguration()->getHttpOptions()['timeout'] ?? null);
        $this->assertSame(3, $client->getConfiguration()->getHttpOptions()['max_retries'] ?? null);
        $this->assertTrue($client->getConfiguration()->useHttpQueryMethod());

        // Reconfigure with array option only changing use_http_query_method
        $reconfigured = $client->withConfiguration(['use_http_query_method' => false]);
        $this->assertFalse($reconfigured->getConfiguration()->useHttpQueryMethod());
        $this->assertSame(30.0, $reconfigured->getConfiguration()->getHttpOptions()['timeout'] ?? null);
        $this->assertSame(3, $reconfigured->getConfiguration()->getHttpOptions()['max_retries'] ?? null);

        // Reconfigure with new timeout updates transport and configuration
        $updatedOptions = $client->withConfiguration(['timeout' => 45.0]);
        $this->assertSame(45.0, $updatedOptions->getConfiguration()->getHttpOptions()['timeout'] ?? null);
        $this->assertSame(3, $updatedOptions->getConfiguration()->getHttpOptions()['max_retries'] ?? null);
        $this->assertTrue($updatedOptions->getConfiguration()->useHttpQueryMethod());
    }
}
