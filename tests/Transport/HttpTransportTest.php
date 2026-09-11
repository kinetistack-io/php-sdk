<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ServiceModuleDisabledException;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\KinetiClient;
use KinetiStack\Sdk\Transport\HttpTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class HttpTransportTest extends TestCase
{
    public function testSuccessfulRequest(): void
    {
        $mockResponse = new MockResponse('{"data": "success"}', [
            'response_headers' => ['Content-Type' => 'application/json']
        ]);
        $client = new MockHttpClient($mockResponse);

        $transport = new HttpTransport('https://api.test', 'test-key', $client);
        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'success'], $response->toArray());

        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.test/v1/test', $mockResponse->getRequestUrl());

        $headers = $mockResponse->getRequestOptions()['headers'];
        $this->assertContains('X-Kineti-Key: test-key', $headers);
    }

    public function testAuthenticationException(): void
    {
        $mockResponse = new MockResponse('{"title": "Unauthorized"}', [
            'http_code' => 401,
            'response_headers' => ['Content-Type' => 'application/problem+json']
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'test-key', $client);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthorized');

        $transport->request('GET', '/v1/test');
    }

    public function testServiceModuleDisabledException(): void
    {
        $errorBody = json_encode([
            'type' => 'https://kinetistack.io/errors/module-disabled',
            'title' => 'Service Module Disabled',
            'status' => 403,
            'detail' => "Service module 'rag' is not enabled for your organization.",
            'module' => 'rag',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 403,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'test-key', $client);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected ServiceModuleDisabledException');
        } catch (ServiceModuleDisabledException $e) {
            $this->assertSame("Service module 'rag' is not enabled for your organization.", $e->getMessage());
            $this->assertSame('rag', $e->moduleIdentifier);
            $this->assertSame('rag', $e->getModuleIdentifier());
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testValidationException(): void
    {
        $errorBody = json_encode([
            'title' => 'Validation Failed',
            'violations' => [
                ['propertyPath' => 'image_url', 'message' => 'Invalid URL']
            ]
        ], JSON_THROW_ON_ERROR);
        $mockResponse = new MockResponse($errorBody, [
            'http_code' => 422,
            'response_headers' => ['Content-Type' => 'application/problem+json']
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'test-key', $client);

        try {
            $transport->request('POST', '/v1/test');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('Validation Failed', $e->getMessage());
            $this->assertCount(1, $e->violations);
            $this->assertSame('image_url', $e->violations[0]['propertyPath']);
        }
    }

    public function testRateLimitExceptionWithRetryAfter(): void
    {
        $mockResponse = new MockResponse('{"title": "Too Many Requests"}', [
            'http_code' => 429,
            'response_headers' => [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '60'
            ]
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'test-key', $client);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame('Too Many Requests', $e->getMessage());
            $this->assertSame(60, $e->retryAfter);
        }
    }

    /**
     * @dataProvider errorStatusCodeProvider
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    public function testErrorStatusCodes(int $statusCode, string $expectedExceptionClass): void
    {
        $mockResponse = new MockResponse('{"title": "Error"}', [
            'http_code' => $statusCode,
            'response_headers' => ['Content-Type' => 'application/problem+json']
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'test-key', $client);

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage('Error');

        $transport->request('GET', '/v1/test');
    }

    /**
     * @return array<int, array{0: int, 1: class-string<\Throwable>}>
     */
    public static function errorStatusCodeProvider(): array
    {
        return [
            [403, \KinetiStack\Sdk\Exception\AuthorizationException::class],
            [404, \KinetiStack\Sdk\Exception\NotFoundException::class],
            [413, \KinetiStack\Sdk\Exception\PayloadTooLargeException::class],
            [500, \KinetiStack\Sdk\Exception\ServerException::class],
            [503, \KinetiStack\Sdk\Exception\ServiceUnavailableException::class],
            [418, \KinetiStack\Sdk\Exception\KinetiException::class], // Fallback
        ];
    }

    public function testDecodingExceptionInterfaceFallback(): void
    {
        // Simulate a 502 Bad Gateway returning HTML
        $mockResponse = new MockResponse('<html><body>502 Bad Gateway</body></html>', [
            'http_code' => 502,
            'response_headers' => ['Content-Type' => 'text/html']
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'test-key', $client);

        $this->expectException(\KinetiStack\Sdk\Exception\KinetiException::class);
        // The message should fall back to the raw content
        $this->expectExceptionMessage('API Error 502: <html><body>502 Bad Gateway</body></html>');

        $transport->request('GET', '/v1/test');
    }

    public function testTransportException(): void
    {
        $mockResponse = new MockResponse('', ['error' => 'cURL error 28: Operation timed out']);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'test-key', $client);

        $this->expectException(\KinetiStack\Sdk\Exception\TransportException::class);
        $transport->request('GET', '/v1/test');
    }

    public function testRetryOnRateLimitRespectsRetryAfterHeader(): void
    {
        $pausedDurations = [];
        $pauseHandler = function (float $duration) use (&$pausedDurations): void {
            $pausedDurations[] = $duration;
        };

        $response1 = new MockResponse('{"title": "Too Many Requests"}', [
            'http_code' => 429,
            'response_headers' => [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '3',
            ],
            'pause_handler' => $pauseHandler,
        ]);
        $response2 = new MockResponse('{"data": "success_after_retry"}', [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
            'pause_handler' => $pauseHandler,
        ]);

        $client = new MockHttpClient([$response1, $response2]);
        $transport = new HttpTransport('https://api.test', 'test-key', $client, [
            'max_retries' => 3,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'success_after_retry'], $response->toArray());
        $this->assertSame(2, $client->getRequestsCount());
        $this->assertCount(1, $pausedDurations);
        $this->assertSame(3.0, $pausedDurations[0]);
    }

    public function testRetryOnServiceUnavailable503(): void
    {
        $pausedDurations = [];
        $pauseHandler = function (float $duration) use (&$pausedDurations): void {
            $pausedDurations[] = $duration;
        };

        $response1 = new MockResponse('{"title": "Service Unavailable"}', [
            'http_code' => 503,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
            'pause_handler' => $pauseHandler,
        ]);
        $response2 = new MockResponse('{"data": "recovered"}', [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
            'pause_handler' => $pauseHandler,
        ]);

        $client = new MockHttpClient([$response1, $response2]);
        $transport = new HttpTransport('https://api.test', 'test-key', $client, [
            'max_retries' => 2,
        ]);

        $response = $transport->request('GET', '/v1/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['data' => 'recovered'], $response->toArray());
        $this->assertSame(2, $client->getRequestsCount());
        $this->assertCount(1, $pausedDurations);
    }

    public function testExhaustedRetriesThrowsServiceUnavailableException(): void
    {
        $response1 = new MockResponse('{"title": "Service Unavailable"}', [
            'http_code' => 503,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
            'pause_handler' => static function (): void {
            },
        ]);
        $response2 = new MockResponse('{"title": "Service Unavailable"}', [
            'http_code' => 503,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
            'pause_handler' => static function (): void {
            },
        ]);

        $client = new MockHttpClient([$response1, $response2]);
        $transport = new HttpTransport('https://api.test', 'test-key', $client, [
            'max_retries' => 1,
        ]);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Service Unavailable');

        try {
            $transport->request('GET', '/v1/test');
        } finally {
            $this->assertSame(2, $client->getRequestsCount());
        }
    }

    public function testExhaustedRetriesThrowsRateLimitExceptionWithRetryAfter(): void
    {
        $response1 = new MockResponse('{"title": "Too Many Requests"}', [
            'http_code' => 429,
            'response_headers' => [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '1',
            ],
            'pause_handler' => static function (): void {
            },
        ]);
        $response2 = new MockResponse('{"title": "Too Many Requests"}', [
            'http_code' => 429,
            'response_headers' => [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '5',
            ],
            'pause_handler' => static function (): void {
            },
        ]);

        $client = new MockHttpClient([$response1, $response2]);
        $transport = new HttpTransport('https://api.test', 'test-key', $client, [
            'max_retries' => 1,
        ]);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame('Too Many Requests', $e->getMessage());
            $this->assertSame(5, $e->retryAfter);
            $this->assertSame(2, $client->getRequestsCount());
        }
    }

    public function testNonTransientErrorsAreNotRetried(): void
    {
        $response = new MockResponse('{"title": "Internal Server Error"}', [
            'http_code' => 500,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);

        $client = new MockHttpClient([$response]);
        $transport = new HttpTransport('https://api.test', 'test-key', $client, [
            'max_retries' => 3,
        ]);

        $this->expectException(\KinetiStack\Sdk\Exception\ServerException::class);
        $this->expectExceptionMessage('Internal Server Error');

        try {
            $transport->request('POST', '/v1/test');
        } finally {
            $this->assertSame(1, $client->getRequestsCount());
        }
    }

    public function testKinetiClientConfiguredWithMaxRetriesRetriesTransparently(): void
    {
        $response1 = new MockResponse('{"title": "Too Many Requests"}', [
            'http_code' => 429,
            'response_headers' => [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '1',
            ],
            'pause_handler' => static function (): void {
            },
        ]);
        $response2 = new MockResponse('{"status": "ok"}', [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/json'],
            'pause_handler' => static function (): void {
            },
        ]);

        $client = new MockHttpClient([$response1, $response2]);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client, [
            'max_retries' => 2,
        ]);

        $health = $kineti->healthz();
        $this->assertSame('ok', $health->status);
        $this->assertSame(2, $client->getRequestsCount());
    }
}
