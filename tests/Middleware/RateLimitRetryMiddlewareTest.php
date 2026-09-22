<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Middleware;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use KinetiStack\Sdk\Exception\RateLimitExceededException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\KinetiClient;
use KinetiStack\Sdk\Middleware\RateLimitRetryMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(RateLimitRetryMiddleware::class)]
final class RateLimitRetryMiddlewareTest extends TestCase
{
    public function testDefaultPropertiesAndGetters(): void
    {
        $middleware = RateLimitRetryMiddleware::create();

        $this->assertSame(RateLimitRetryMiddleware::DEFAULT_MAX_RETRIES, $middleware->getMaxRetries());
        $this->assertSame(RateLimitRetryMiddleware::DEFAULT_BASE_DELAY_MS, $middleware->getBaseDelayMs());
        $this->assertSame(RateLimitRetryMiddleware::DEFAULT_MAX_DELAY_MS, $middleware->getMaxDelayMs());
        $this->assertTrue($middleware->isJitterEnabled());
        $this->assertSame([429], $middleware->getRetryStatusCodes());
    }

    public function testCustomConfiguration(): void
    {
        $middleware = RateLimitRetryMiddleware::create(
            maxRetries: 5,
            baseDelayMs: 500,
            maxDelayMs: 10000,
            enableJitter: false,
            retryStatusCodes: [429, 503]
        );

        $this->assertSame(5, $middleware->getMaxRetries());
        $this->assertSame(500, $middleware->getBaseDelayMs());
        $this->assertSame(10000, $middleware->getMaxDelayMs());
        $this->assertFalse($middleware->isJitterEnabled());
        $this->assertSame([429, 503], $middleware->getRetryStatusCodes());
    }

    public function testFactoryHelperReturnsCallable(): void
    {
        $factory = RateLimitRetryMiddleware::factory(maxRetries: 2);
        $this->assertInstanceOf(RateLimitRetryMiddleware::class, $factory);

        $stack = HandlerStack::create(new MockHandler([new Response(200)]));
        $stack->push($factory);
        $client = new Client(['handler' => $stack]);

        $response = $client->request('GET', 'https://api.test/data');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRetriesOn429UntilSuccess(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '0'], '{"title": "Too Many Requests"}'),
            new Response(429, ['Retry-After' => '0'], '{"title": "Too Many Requests"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"success": true}'),
        ]);

        $delays = [];
        $middleware = RateLimitRetryMiddleware::create(
            maxRetries: 3,
            baseDelayMs: 10,
            delay: function (int $retries, ?ResponseInterface $response = null) use (&$delays): int {
                $delays[] = $retries;

                return 0; // Return 0ms so test runs fast
            }
        );

        $stack = HandlerStack::create($mock);
        $stack->push($middleware);
        $client = new Client(['handler' => $stack]);

        $response = $client->request('GET', 'https://api.test/endpoint');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"success": true}', (string) $response->getBody());
        $this->assertSame([1, 2], $delays);
        $this->assertSame(0, $mock->count());
    }

    public function testRetriesExhaustedReturns429(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '0'], '{"title": "Attempt 1"}'),
            new Response(429, ['Retry-After' => '0'], '{"title": "Attempt 2"}'),
            new Response(429, ['Retry-After' => '0'], '{"title": "Attempt 3"}'),
        ]);

        $retryCount = 0;
        $middleware = RateLimitRetryMiddleware::create(
            maxRetries: 2,
            delay: function (int $retries) use (&$retryCount): int {
                $retryCount = $retries;

                return 0;
            }
        );

        $stack = HandlerStack::create($mock);
        $stack->push($middleware);
        $client = new Client(['handler' => $stack, 'http_errors' => false]);

        $response = $client->request('GET', 'https://api.test/endpoint');

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame(2, $retryCount);
        $this->assertSame('{"title": "Attempt 3"}', (string) $response->getBody());
    }

    public function testHonorsRetryAfterHeaderInSeconds(): void
    {
        $middleware = RateLimitRetryMiddleware::create(maxDelayMs: 60000);
        $delayFn = $middleware->buildDelay();

        $response = new Response(429, ['Retry-After' => '5']);
        $delayMs = $delayFn(1, $response, new Request('GET', 'https://api.test'));

        $this->assertSame(5000, $delayMs);
    }

    public function testHonorsRetryAfterHeaderInHttpDate(): void
    {
        $futureTime = time() + 10;
        $httpDate = gmdate('D, d M Y H:i:s \G\M\T', $futureTime);

        $middleware = RateLimitRetryMiddleware::create(maxDelayMs: 60000);
        $delayFn = $middleware->buildDelay();

        $response = new Response(429, ['Retry-After' => $httpDate]);
        $delayMs = $delayFn(1, $response, new Request('GET', 'https://api.test'));

        // Delay should be approximately 10,000ms (+- 2000ms for clock variance)
        $this->assertEqualsWithDelta(10000, $delayMs, 2000);
    }

    public function testRetryAfterExceedingMaxDelayAbortsRetry(): void
    {
        $middleware = RateLimitRetryMiddleware::create(maxRetries: 3, maxDelayMs: 15000);
        $decider = $middleware->buildDecider();

        $response = new Response(429, ['Retry-After' => '120']); // 120s = 120000ms > 15000ms
        $request = new Request('GET', 'https://api.test');

        $this->assertFalse($decider(0, $request, $response, null));

        // Integration verification: Client should return 429 immediately without retrying
        $mock = new MockHandler([$response]);
        $stack = HandlerStack::create($mock);
        $stack->push($middleware);
        $client = new Client(['handler' => $stack, 'http_errors' => false]);

        $res = $client->request('GET', 'https://api.test/data');
        $this->assertSame(429, $res->getStatusCode());
        $this->assertSame('120', $res->getHeaderLine('Retry-After'));
    }

    public function testRetryAfterWithinMaxDelayIsRespected(): void
    {
        $middleware = RateLimitRetryMiddleware::create(maxRetries: 3, maxDelayMs: 15000);
        $decider = $middleware->buildDecider();
        $delayFn = $middleware->buildDelay();

        $response = new Response(429, ['Retry-After' => '10']); // 10s = 10000ms <= 15000ms
        $request = new Request('GET', 'https://api.test');

        $this->assertTrue($decider(0, $request, $response, null));
        $this->assertSame(10000, $delayFn(1, $response, $request));
    }

    public function testExponentialBackoffWithoutRetryAfter(): void
    {
        $middleware = RateLimitRetryMiddleware::create(
            baseDelayMs: 1000,
            maxDelayMs: 60000,
            enableJitter: false
        );
        $delayFn = $middleware->buildDelay();

        $response = new Response(429);
        $request = new Request('GET', 'https://api.test');

        // Retry 1: 1000 * 2^0 = 1000ms
        $this->assertSame(1000, $delayFn(1, $response, $request));
        // Retry 2: 1000 * 2^1 = 2000ms
        $this->assertSame(2000, $delayFn(2, $response, $request));
        // Retry 3: 1000 * 2^2 = 4000ms
        $this->assertSame(4000, $delayFn(3, $response, $request));
        // Retry 4: 1000 * 2^3 = 8000ms
        $this->assertSame(8000, $delayFn(4, $response, $request));
    }

    public function testExponentialBackoffWithJitter(): void
    {
        $middleware = RateLimitRetryMiddleware::create(
            baseDelayMs: 1000,
            maxDelayMs: 60000,
            enableJitter: true
        );
        $delayFn = $middleware->buildDelay();

        $response = new Response(429);
        $request = new Request('GET', 'https://api.test');

        $delay = $delayFn(1, $response, $request);
        // Base is 1000ms, max jitter is 200ms -> delay between 1000 and 1200
        $this->assertGreaterThanOrEqual(1000, $delay);
        $this->assertLessThanOrEqual(1200, $delay);
    }

    public function testExponentialBackoffCappedAtMaxDelay(): void
    {
        $middleware = RateLimitRetryMiddleware::create(
            baseDelayMs: 10000,
            maxDelayMs: 25000,
            enableJitter: false
        );
        $delayFn = $middleware->buildDelay();

        $response = new Response(429);
        $request = new Request('GET', 'https://api.test');

        // Retry 3 would be 10000 * 4 = 40000, capped at 25000
        $this->assertSame(25000, $delayFn(3, $response, $request));
    }

    public function testNonRetryableStatusCodeIsNotRetried(): void
    {
        $mock = new MockHandler([
            new Response(404, [], '{"title": "Not Found"}'),
        ]);

        $deciderCalled = false;
        $middleware = RateLimitRetryMiddleware::create(
            decider: function (int $retries, RequestInterface $request, ?ResponseInterface $response = null) use (&$deciderCalled): bool {
                $deciderCalled = true;

                return false;
            }
        );

        $stack = HandlerStack::create($mock);
        $stack->push($middleware);
        $client = new Client(['handler' => $stack, 'http_errors' => false]);

        $response = $client->request('GET', 'https://api.test/not-found');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertTrue($deciderCalled);
    }

    public function testCustomRetryStatusCodes(): void
    {
        $mock = new MockHandler([
            new Response(503, [], '{"title": "Service Unavailable"}'),
            new Response(200, [], '{"status": "recovered"}'),
        ]);

        $middleware = RateLimitRetryMiddleware::create(
            maxRetries: 2,
            delay: fn (): int => 0,
            retryStatusCodes: [429, 503]
        );

        $stack = HandlerStack::create($mock);
        $stack->push($middleware);
        $client = new Client(['handler' => $stack]);

        $response = $client->request('GET', 'https://api.test/service');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testKinetiClientIntegrationAutomaticRetryOn429(): void
    {
        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '1',
            ], '{"title": "Rate limit exceeded"}'),
            new Response(200, [
                'Content-Type' => 'application/json',
            ], '{"status": "ok"}'),
        ]);

        $middleware = RateLimitRetryMiddleware::create(
            maxRetries: 2,
            delay: fn (): int => 0 // instant retry for test
        );

        $stack = HandlerStack::create($mock);
        $stack->push($middleware);
        $guzzleClient = new Client(['handler' => $stack]);

        $kineti = new KinetiClient('https://api.test', 'test-key', $guzzleClient);
        $health = $kineti->healthz();

        $this->assertSame('ok', $health->status);
        $this->assertSame(0, $mock->count());
    }

    public function testKinetiClientIntegrationThrowsRateLimitExceededExceptionOnExhaustion(): void
    {
        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '45',
                'X-RateLimit-Limit' => '100',
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => '1726955000',
            ], '{"title": "Too Many Requests", "detail": "Rate limit exceeded. Please wait."}'),
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '45',
                'X-RateLimit-Limit' => '100',
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => '1726955000',
            ], '{"title": "Too Many Requests", "detail": "Rate limit exceeded. Please wait."}'),
        ]);

        $middleware = RateLimitRetryMiddleware::create(
            maxRetries: 1,
            delay: fn (): int => 0
        );

        $stack = HandlerStack::create($mock);
        $stack->push($middleware);
        $guzzleClient = new Client(['handler' => $stack]);

        $kineti = new KinetiClient('https://api.test', 'test-key', $guzzleClient);

        try {
            $kineti->healthz();
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('Rate limit exceeded. Please wait.', $e->getMessage());
            $this->assertSame(429, $e->getCode());
            $this->assertSame(45, $e->getRetryAfter());
            $this->assertSame(45, $e->retryAfter);
            $this->assertSame(100, $e->getLimit());
            $this->assertSame(0, $e->getRemaining());
            $this->assertSame(1726955000, $e->getReset());
            $this->assertInstanceOf(RateLimitException::class, $e);
        }
    }

    public function testKinetiClientThrowsRateLimitExceededExceptionWhenNotConfiguredToRetry(): void
    {
        $mock = new MockHandler([
            new Response(429, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '60',
            ], '{"title": "Too Many Requests"}'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        try {
            $kineti->healthz();
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('Too Many Requests', $e->getMessage());
            $this->assertSame(60, $e->getRetryAfter());
            $this->assertSame(60, $e->retryAfter);
        }
    }
}
