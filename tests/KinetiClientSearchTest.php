<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\Dto\RagSynthesisDto;
use KinetiStack\Sdk\Dto\SearchFilterDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\KinetiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class KinetiClientSearchTest extends TestCase
{
    use FixtureTrait;

    public function testSearchWithoutSynthesis200(): void
    {
        $fixture = $this->loadFixture('Search/search_response_without_synthesis_200.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

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
        $this->assertInstanceOf(SearchResultItemDto::class, $item1);
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

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/search/query', $mockResponse->getRequestUrl());
    }

    public function testSearchWithRagSynthesis200(): void
    {
        $fixture = $this->loadFixture('Search/search_response_with_synthesis_200.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('how do solar panels and inverters work together')
            ->withLocale('en')
            ->withPermissions(['authenticated'])
            ->withSynthesis(true);

        $response = $kineti->search($query);

        $this->assertTrue($response->hasSynthesis());
        $this->assertInstanceOf(RagSynthesisDto::class, $response->synthesis);
        $this->assertSame(
            'Solar panels produce electricity from sunlight, which inverters then convert for household use.',
            $response->synthesis->answer
        );
        $this->assertSame(['node:101:en', 'node:102:en'], $response->synthesis->citations);
        $this->assertTrue($response->synthesis->successful);
        $this->assertNull($response->synthesis->fallbackReason);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertTrue($requestBody['synthesize_answer']);
        $this->assertSame('en', $requestBody['locale']);
        $this->assertSame(['authenticated'], $requestBody['filters']['permissions']);
    }

    public function testSearchWithFallbackSynthesis200(): void
    {
        $fixture = $this->loadFixture('Search/search_response_fallback_200.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $response = $kineti->search(SearchQueryDto::create('test')->withSynthesis(true));

        $this->assertTrue($response->hasSynthesis());
        $this->assertNotNull($response->synthesis);
        $this->assertFalse($response->synthesis->successful);
        $this->assertSame('LLM rate limit reached', $response->synthesis->fallbackReason);
        $this->assertNull($response->synthesis->answer);
        $this->assertSame([], $response->synthesis->citations);
    }

    public function testSearchPayloadSerializationAndNullOmission(): void
    {
        $fixture = $this->loadFixture('Search/search_response_without_synthesis_200.json');
        $mockResponse = new MockResponse($fixture, ['http_code' => 200]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $query = SearchQueryDto::create('renewable tech')
            ->withLimit(10)
            ->withLocale('da')
            ->withUserRoles(['editor', 'admin'])
            ->withMinScore(0.70)
            ->withDistinctDocuments(true)
            ->withSynthesizeAnswer(true)
            ->withFilter('bundle', 'article');

        $kineti->search($query);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame('renewable tech', $requestBody['query']);
        $this->assertSame(10, $requestBody['limit']);
        $this->assertSame('da', $requestBody['locale']);
        $this->assertSame(['editor', 'admin'], $requestBody['user_roles']);
        $this->assertSame(0.70, $requestBody['min_score']);
        $this->assertTrue($requestBody['distinct_documents']);
        $this->assertTrue($requestBody['synthesize_answer']);
        $this->assertSame('article', $requestBody['filters']['bundle']);

        // Minimal query omits null keys
        $minimalQuery = SearchQueryDto::create('minimal');
        $this->assertSame(['query' => 'minimal'], $minimalQuery->toArray());
    }

    public function testSearchWithStringShortcut(): void
    {
        $fixture = $this->loadFixture('Search/search_response_without_synthesis_200.json');
        $mockResponse = new MockResponse($fixture, ['http_code' => 200]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $response = $kineti->search('simple string query');

        $this->assertInstanceOf(SearchResponseDto::class, $response);
        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame('simple string query', $requestBody['query']);
    }

    public function testSearchValidationError422(): void
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

        $kineti->search(SearchQueryDto::create('test')->withLimit(9999));
    }

    public function testSearchUnauthorized401(): void
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

        $kineti->search('query');
    }

    public function testSearchForbidden403(): void
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

        $kineti->search('query');
    }

    public function testSearchRateLimit429WithRetryAfter(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_rate_limit_429.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 429,
            'response_headers' => [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '45',
            ],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        try {
            $kineti->search('query');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame('Rate limit exceeded. Please retry after the specified duration.', $e->getMessage());
            $this->assertSame(45, $e->retryAfter);
        }
    }

    public function testSearchServerError500(): void
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

        $kineti->search('query');
    }

    public function testSearchServiceUnavailable503(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_service_unavailable_503.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 503,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Vector indexing service is temporarily undergoing maintenance.');

        $kineti->search('query');
    }
}
