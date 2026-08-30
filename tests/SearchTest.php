<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\Dto\RagSynthesisDto;
use KinetiStack\Sdk\Dto\SearchFilterDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\KinetiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SearchTest extends TestCase
{
    public function testSearchWithoutSynthesis(): void
    {
        $responseBody = json_encode([
            'results' => [
                [
                    'external_id' => 'node:101:en',
                    'title' => 'Solar Panels 101',
                    'content' => 'Solar panels convert sunlight into electricity.',
                    'chunk_index' => 0,
                    'score' => 0.89,
                    'metadata' => ['bundle' => 'article', 'category' => 'energy'],
                ],
                [
                    'external_id' => 'node:102:en',
                    'title' => 'Inverters Explained',
                    'content' => 'Inverters convert DC to AC power.',
                    'chunk_index' => 1,
                    'score' => 0.75,
                    'metadata' => null,
                ],
            ],
            'total' => 2,
            'synthesis' => null,
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $query = SearchQueryDto::create('solar panel')
            ->withLimit(5)
            ->withSynthesis(false);

        $response = $kineti->search($query);

        $this->assertInstanceOf(SearchResponseDto::class, $response);
        $this->assertSame(2, $response->total);
        $this->assertCount(2, $response->results);
        $this->assertFalse($response->hasSynthesis());
        $this->assertNull($response->synthesis);

        $item1 = $response->results[0];
        $this->assertSame('node:101:en', $item1->externalId);
        $this->assertSame('Solar Panels 101', $item1->title);
        $this->assertSame('Solar panels convert sunlight into electricity.', $item1->content);
        $this->assertSame('Solar panels convert sunlight into electricity.', $item1->getSnippet());
        $this->assertSame(0, $item1->chunkIndex);
        $this->assertSame(0.89, $item1->score);
        $this->assertSame(['bundle' => 'article', 'category' => 'energy'], $item1->metadata);

        $item2 = $response->results[1];
        $this->assertSame('node:102:en', $item2->externalId);
        $this->assertSame('Inverters Explained', $item2->title);
        $this->assertSame(0.75, $item2->score);
        $this->assertNull($item2->metadata);
    }

    public function testSearchWithRagSynthesis(): void
    {
        $responseBody = json_encode([
            'results' => [
                [
                    'external_id' => 'node:101:en',
                    'title' => 'Solar Panels 101',
                    'content' => 'Solar panels convert sunlight into electricity using photovoltaic cells.',
                    'chunk_index' => 0,
                    'score' => 0.94,
                    'metadata' => ['bundle' => 'article'],
                ],
            ],
            'total' => 1,
            'synthesis' => [
                'answer' => 'Solar panels generate electricity from sunlight via photovoltaic cells.',
                'citations' => ['node:101:en'],
                'successful' => true,
                'fallback_reason' => null,
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $query = SearchQueryDto::create('how do solar panels work')
            ->withLocale('en')
            ->withPermissions(['authenticated'])
            ->withSynthesis(true);

        $response = $kineti->search($query);

        $this->assertTrue($response->hasSynthesis());
        $this->assertInstanceOf(RagSynthesisDto::class, $response->synthesis);
        $this->assertSame('Solar panels generate electricity from sunlight via photovoltaic cells.', $response->synthesis->answer);
        $this->assertSame(['node:101:en'], $response->synthesis->citations);
        $this->assertTrue($response->synthesis->successful);
        $this->assertNull($response->synthesis->fallbackReason);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertTrue($requestBody['synthesize_answer']);
        $this->assertSame(['authenticated'], $requestBody['filters']['permissions']);
    }

    public function testSearchSendsCorrectHttpMethod(): void
    {
        $mockResponse = new MockResponse(json_encode(['results' => [], 'total' => 0], JSON_THROW_ON_ERROR));
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $kineti->search(SearchQueryDto::create('test'));

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/search/query', $mockResponse->getRequestUrl());
    }

    public function testSearchPayloadOmitsNullFields(): void
    {
        $mockResponse = new MockResponse(json_encode(['results' => [], 'total' => 0], JSON_THROW_ON_ERROR));
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $query = SearchQueryDto::create('minimal query');
        $kineti->search($query);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame(['query' => 'minimal query'], $requestBody);
        $this->assertSame(['query' => 'minimal query'], $query->toArray());
    }

    public function testFluentBuilderProducesCorrectPayload(): void
    {
        $query = SearchQueryDto::create('renewable energy')
            ->withLimit(10)
            ->withLocale('da')
            ->withUserRoles(['editor', 'admin'])
            ->withMinScore(0.65)
            ->withDistinctDocuments(true)
            ->withPermissions('authenticated')
            ->withFilter('bundle', 'article')
            ->withSynthesizeAnswer(true);

        $payload = $query->toArray();

        $this->assertSame('renewable energy', $payload['query']);
        $this->assertSame(10, $payload['limit']);
        $this->assertSame('da', $payload['locale']);
        $this->assertSame(['editor', 'admin'], $payload['user_roles']);
        $this->assertSame(0.65, $payload['min_score']);
        $this->assertTrue($payload['distinct_documents']);
        $this->assertTrue($payload['synthesize_answer']);
        $this->assertSame('article', $payload['filters']['bundle']);
        $this->assertSame(['authenticated'], $payload['filters']['permissions']);
    }

    public function testSearchFiltersAreSerializedCorrectly(): void
    {
        $filter = (new SearchFilterDto())
            ->withLocale('fr')
            ->withPermissions(['member', 'subscriber'])
            ->withCustom('tag', 'solar')
            ->withCustom('archived', false);

        $this->assertFalse($filter->isEmpty());

        $data = $filter->toArray();
        $this->assertSame('fr', $data['locale']);
        $this->assertSame(['member', 'subscriber'], $data['permissions']);
        $this->assertSame('solar', $data['tag']);
        $this->assertFalse($data['archived']);

        $restored = SearchFilterDto::fromArray($data);
        $this->assertSame('fr', $restored->locale);
        $this->assertSame(['member', 'subscriber'], $restored->permissions);
        $this->assertSame(['tag' => 'solar', 'archived' => false], $restored->custom);

        $emptyFilter = new SearchFilterDto();
        $this->assertTrue($emptyFilter->isEmpty());
        $this->assertSame([], $emptyFilter->toArray());
    }

    public function testSearchResultItemDtoFromArray(): void
    {
        $data = [
            'externalId' => 'doc:123',
            'title' => 'Test Title',
            'snippet' => 'Test snippet content',
            'chunkIndex' => 2,
            'score' => 0.85,
            'metadata' => ['author' => 'Alice'],
        ];

        $dto = SearchResultItemDto::fromArray($data);
        $this->assertSame('doc:123', $dto->externalId);
        $this->assertSame('Test Title', $dto->title);
        $this->assertSame('Test snippet content', $dto->content);
        $this->assertSame('Test snippet content', $dto->getSnippet());
        $this->assertSame(2, $dto->chunkIndex);
        $this->assertSame(0.85, $dto->score);
        $this->assertSame(['author' => 'Alice'], $dto->metadata);

        $serialized = $dto->toArray();
        $this->assertSame('doc:123', $serialized['external_id']);
        $this->assertSame('Test snippet content', $serialized['content']);
        $this->assertSame(2, $serialized['chunk_index']);
        $this->assertSame(0.85, $serialized['score']);
    }

    public function testRagSynthesisDtoFromArray(): void
    {
        $data = [
            'answer' => 'Synthesized response text.',
            'citations' => ['doc:1', 'doc:2'],
            'successful' => true,
            'fallback_reason' => null,
        ];

        $dto = RagSynthesisDto::fromArray($data);
        $this->assertSame('Synthesized response text.', $dto->answer);
        $this->assertSame(['doc:1', 'doc:2'], $dto->citations);
        $this->assertTrue($dto->successful);
        $this->assertNull($dto->fallbackReason);

        $serialized = $dto->toArray();
        $this->assertSame('Synthesized response text.', $serialized['answer']);
        $this->assertSame(['doc:1', 'doc:2'], $serialized['citations']);
        $this->assertTrue($serialized['successful']);
        $this->assertArrayNotHasKey('fallback_reason', $serialized);

        // Test fallback reason when present
        $fallbackDto = RagSynthesisDto::fromArray([
            'answer' => null,
            'citations' => [],
            'successful' => false,
            'fallback_reason' => 'LLM rate limit reached',
        ]);
        $this->assertFalse($fallbackDto->successful);
        $this->assertSame('LLM rate limit reached', $fallbackDto->fallbackReason);
        $this->assertSame('LLM rate limit reached', $fallbackDto->toArray()['fallback_reason']);
    }

    public function testSearchWithStringQueryShortcut(): void
    {
        $mockResponse = new MockResponse(json_encode(['results' => [], 'total' => 0], JSON_THROW_ON_ERROR));
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'key', $client);

        $response = $kineti->search('simple search text');

        $this->assertInstanceOf(SearchResponseDto::class, $response);
        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame('simple search text', $requestBody['query']);
    }

    public function testSearchEmptyQueryThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search query cannot be empty.');

        SearchQueryDto::create('   ');
    }

    public function testSearchValidationError(): void
    {
        $responseBody = json_encode([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'An error occurred',
            'detail' => 'Validation failed',
            'violations' => [
                ['propertyPath' => 'limit', 'message' => 'Limit must be between 1 and 50.'],
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

        $kineti->search(SearchQueryDto::create('valid query')->withLimit(100));
    }

    public function testSearchResponseCountAndIteration(): void
    {
        $results = [
            new SearchResultItemDto('doc:1', 'Doc 1', 'Snippet 1', 0, 0.9),
            new SearchResultItemDto('doc:2', 'Doc 2', 'Snippet 2', 0, 0.8),
        ];

        $response = new SearchResponseDto($results, 2);

        $this->assertSame(2, $response->count());
        $this->assertCount(2, $response);

        $titles = [];
        foreach ($response as $item) {
            $titles[] = $item->title;
        }

        $this->assertSame(['Doc 1', 'Doc 2'], $titles);

        $serialized = $response->toArray();
        $this->assertCount(2, $serialized['results']);
        $this->assertSame(2, $serialized['total']);
        $this->assertArrayNotHasKey('synthesis', $serialized);
    }

    public function testSearchResponseFromArrayHandlesMalformedResults(): void
    {
        // Malformed payload where results/member is a scalar or missing
        $response1 = SearchResponseDto::fromArray(['results' => 'invalid_string']);
        $this->assertCount(0, $response1->results);
        $this->assertSame(0, $response1->total);

        $response2 = SearchResponseDto::fromArray(['hydra:member' => 12345]);
        $this->assertCount(0, $response2->results);
        $this->assertSame(0, $response2->total);
    }
}
