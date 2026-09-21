<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use KinetiStack\Sdk\AdminClient;
use KinetiStack\Sdk\AnalyticsClient;
use KinetiStack\Sdk\Client\AnalyticsClientInterface;
use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Enum\AnalyticsGrouping;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AnalyticsClientTest extends TestCase
{
    /**
     * Scenario 1: Fetching Analytics Grouped by Week on AnalyticsClient
     *
     * Given an authenticated AnalyticsClient
     * When calling getAnalytics with grouping set to AnalyticsGrouping::WEEK
     * Then the underlying HTTP request includes ?group_by=week
     * And returns a populated AnalyticsDto.
     */
    public function testFetchAnalyticsGroupedByWeekOnAnalyticsClient(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['date' => '2026-W36', 'vision' => 250, 'search' => 120, 'ingest' => 15, 'rag' => 45],
                ['date' => '2026-W37', 'vision' => 400, 'search' => 310, 'ingest' => 20, 'rag' => 80],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AnalyticsClient('https://api.test', 'valid-jwt-token', $httpClient);

        $analytics = $client->getAnalytics(AnalyticsGrouping::WEEK);

        $this->assertInstanceOf(AnalyticsDto::class, $analytics);
        $this->assertCount(2, $analytics->data);
        $this->assertSame('2026-W36', $analytics->data[0]['date']);
        $this->assertSame(250, $analytics->data[0]['vision']);
        $this->assertSame(120, $analytics->data[0]['search']);
        $this->assertSame(15, $analytics->data[0]['ingest']);
        $this->assertSame(45, $analytics->data[0]['rag']);
        $this->assertSame('2026-W37', $analytics->data[1]['date']);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('/api/v1/admin/analytics', $mockResponse->getRequestUrl());
        $this->assertStringContainsString('group_by=week', $mockResponse->getRequestUrl());
        $this->assertContains('Authorization: Bearer valid-jwt-token', $mockResponse->getRequestOptions()['headers']);
    }

    /**
     * Scenario 1 (Alternative): Fetching Analytics Grouped by Week on AdminClient
     *
     * Given an authenticated AdminClient
     * When calling getAnalytics with grouping set to AnalyticsGrouping::WEEK
     * Then the underlying HTTP request includes ?group_by=week
     * And returns a populated AnalyticsDto.
     */
    public function testFetchAnalyticsGroupedByWeekOnAdminClient(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['date' => '2026-W36', 'vision' => 100, 'search' => 50],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'admin-jwt-token', $httpClient);

        $analytics = $admin->getAnalytics(AnalyticsGrouping::WEEK);

        $this->assertInstanceOf(AnalyticsDto::class, $analytics);
        $this->assertCount(1, $analytics->data);
        $this->assertSame('2026-W36', $analytics->data[0]['date']);
        $this->assertSame(100, $analytics->data[0]['vision']);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('/api/v1/admin/analytics', $mockResponse->getRequestUrl());
        $this->assertStringContainsString('group_by=week', $mockResponse->getRequestUrl());
        $this->assertContains('Authorization: Bearer admin-jwt-token', $mockResponse->getRequestOptions()['headers']);
    }

    /**
     * Scenario 2: Fetching Analytics Grouped by Month on AnalyticsClient
     *
     * Given an authenticated AnalyticsClient
     * When calling getAnalytics with grouping set to AnalyticsGrouping::MONTH
     * Then the underlying HTTP request includes ?group_by=month
     * And returns a populated AnalyticsDto.
     */
    public function testFetchAnalyticsGroupedByMonthOnAnalyticsClient(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['date' => '2026-08', 'vision' => 1200, 'search' => 850, 'ingest' => 100, 'rag' => 300],
                ['date' => '2026-09', 'vision' => 1800, 'search' => 1100, 'ingest' => 150, 'rag' => 450],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AnalyticsClient('https://api.test', 'valid-jwt-token', $httpClient);

        $analytics = $client->getAnalytics(AnalyticsGrouping::MONTH);

        $this->assertInstanceOf(AnalyticsDto::class, $analytics);
        $this->assertCount(2, $analytics->data);
        $this->assertSame('2026-08', $analytics->data[0]['date']);
        $this->assertSame(1200, $analytics->data[0]['vision']);
        $this->assertSame('2026-09', $analytics->data[1]['date']);
        $this->assertSame(1800, $analytics->data[1]['vision']);

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('/api/v1/admin/analytics', $mockResponse->getRequestUrl());
        $this->assertStringContainsString('group_by=month', $mockResponse->getRequestUrl());
    }

    public function testFetchAnalyticsGroupedByDayDefault(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['date' => '2026-09-20', 'vision' => 50],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AnalyticsClient('https://api.test', 'jwt', $httpClient);

        $analytics = $client->getAnalytics();

        $this->assertInstanceOf(AnalyticsDto::class, $analytics);
        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('group_by=day', $mockResponse->getRequestUrl());
    }

    public function testFetchAnalyticsGroupedByDayExplicit(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['date' => '2026-09-20', 'vision' => 50],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AnalyticsClient('https://api.test', 'jwt', $httpClient);

        $analytics = $client->getAnalytics(AnalyticsGrouping::DAY);

        $this->assertInstanceOf(AnalyticsDto::class, $analytics);
        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertStringContainsString('group_by=day', $mockResponse->getRequestUrl());
    }

    public function testFetchAnalyticsWithDateRangeAndProjectId(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['date' => '2026-W36', 'vision' => 100],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AnalyticsClient('https://api.test', 'jwt', $httpClient);

        $analytics = $client->getAnalytics(
            grouping: AnalyticsGrouping::WEEK,
            from: '2026-09-01',
            to: '2026-09-30',
            projectId: 'proj-uuid-1'
        );

        $this->assertInstanceOf(AnalyticsDto::class, $analytics);
        $requestUrl = $mockResponse->getRequestUrl();
        $this->assertStringContainsString('group_by=week', $requestUrl);
        $this->assertStringContainsString('from=2026-09-01', $requestUrl);
        $this->assertStringContainsString('to=2026-09-30', $requestUrl);
        $this->assertStringContainsString('project_id=proj-uuid-1', $requestUrl);
    }

    public function testAdminClientAnalyticsAccessor(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['date' => '2026-09', 'search' => 500],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'admin-token', $httpClient);

        $analyticsClient = $admin->analytics();
        $this->assertInstanceOf(AnalyticsClientInterface::class, $analyticsClient);
        $this->assertInstanceOf(AnalyticsClient::class, $analyticsClient);

        // Subsequent call returns cached client instance
        $this->assertSame($analyticsClient, $admin->analytics());

        $result = $analyticsClient->getAnalytics(AnalyticsGrouping::MONTH);
        $this->assertInstanceOf(AnalyticsDto::class, $result);
        $this->assertStringContainsString('group_by=month', $mockResponse->getRequestUrl());
        $this->assertContains('Authorization: Bearer admin-token', $mockResponse->getRequestOptions()['headers']);
    }

    public function testWithTokenReturnsNewInstanceWithUpdatedAuth(): void
    {
        $responseBody = json_encode(['data' => []], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new AnalyticsClient('https://api.test', 'old-token', $httpClient);

        $newClient = $client->withToken('new-refreshed-token');
        $this->assertNotSame($client, $newClient);

        $newClient->getAnalytics();
        $this->assertContains('Authorization: Bearer new-refreshed-token', $mockResponse->getRequestOptions()['headers']);
    }

    public function testAnalyticsClientConstructedWithTransportInterface(): void
    {
        $responseBody = json_encode([
            'data' => [
                ['date' => '2026-W37', 'rag' => 30],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($mockResponse);
        $admin = new AdminClient('https://api.test', 'transport-token', $httpClient);

        // Construct AnalyticsClient directly from existing transport
        $client = new AnalyticsClient($admin->getTransport());
        $this->assertSame($admin->getTransport(), $client->getTransport());

        $analytics = $client->getAnalytics(AnalyticsGrouping::WEEK);
        $this->assertInstanceOf(AnalyticsDto::class, $analytics);
        $this->assertStringContainsString('group_by=week', $mockResponse->getRequestUrl());
    }

    public function testAnalyticsClientWithGuzzlePsr18(): void
    {
        /** @var list<array{request: RequestInterface, response: GuzzleResponse}> $container */
        $container = [];
        $history = Middleware::history($container);

        $mock = new MockHandler([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], '{"data": [{"date": "2026-09", "vision": 75}]}'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $guzzleClient = new GuzzleClient(['handler' => $stack]);
        $client = new AnalyticsClient('https://api.test', 'psr18-token', $guzzleClient);

        $result = $client->getAnalytics(AnalyticsGrouping::MONTH);

        $this->assertInstanceOf(AnalyticsDto::class, $result);
        $this->assertCount(1, $result->data);
        $this->assertSame(75, $result->data[0]['vision']);

        assert(is_array($container));
        $this->assertCount(1, $container);
        $sentRequest = $container[0]['request'];
        $this->assertSame('GET', $sentRequest->getMethod());
        $this->assertSame('/api/v1/admin/analytics?group_by=month', $sentRequest->getRequestTarget());
        $this->assertSame(['Bearer psr18-token'], $sentRequest->getHeader('Authorization'));
    }
}
