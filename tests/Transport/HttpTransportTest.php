<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\RateLimitExceededException;
use KinetiStack\Sdk\Exception\ServiceModuleDisabledException;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\KinetiClient;
use KinetiStack\Sdk\Transport\HttpTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HttpTransportTest extends TestCase
{
    public function testSuccessfulRequest(): void
    {
        $mockResponse = new MockResponse('{"data": "success"}', [
            'response_headers' => ['Content-Type' => 'application/json']
        ]);
        $client = new MockHttpClient($mockResponse);

        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);
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
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        try {
            $transport->request('POST', '/v1/test');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('Validation Failed', $e->getMessage());
            $this->assertCount(1, $e->violations);
            $this->assertSame('image_url', $e->violations[0]['propertyPath']);
        }
    }

    public function testRateLimitExceededExceptionWithRetryAfter(): void
    {
        $mockResponse = new MockResponse('{"title": "Too Many Requests"}', [
            'http_code' => 429,
            'response_headers' => [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => '60'
            ]
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('Too Many Requests', $e->getMessage());
            $this->assertSame(60, $e->retryAfter);
            $this->assertSame(60, $e->getRetryAfter());
        }
    }

    public function testRateLimitExceededExceptionWithAllRateLimitHeaders(): void
    {
        $mockResponse = new MockResponse('{"title": "Too Many Requests"}', [
            'http_code' => 429,
            'response_headers' => [
                'Content-Type' => 'application/problem+json',
                'X-RateLimit-Limit' => '100',
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => '1726950000',
                'Retry-After' => '30',
            ],
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('Too Many Requests', $e->getMessage());
            $this->assertSame(100, $e->limit);
            $this->assertSame(100, $e->getLimit());
            $this->assertSame(0, $e->remaining);
            $this->assertSame(0, $e->getRemaining());
            $this->assertSame(1726950000, $e->reset);
            $this->assertSame(1726950000, $e->getReset());
            $this->assertSame(30, $e->retryAfter);
            $this->assertSame(30, $e->getRetryAfter());

            $info = $transport->getLastRateLimitInfo();
            $this->assertNotNull($info);
            $this->assertSame(100, $info->limit);
            $this->assertSame(0, $info->remaining);
            $this->assertSame(1726950000, $info->reset);
            $this->assertSame(30, $info->retryAfter);
        }
    }

    public function testSuccessfulResponseCapturesRateLimitHeaders(): void
    {
        $mockResponse = new MockResponse('{"data": "success"}', [
            'http_code' => 200,
            'response_headers' => [
                'Content-Type' => 'application/json',
                'X-RateLimit-Limit' => '500',
                'X-RateLimit-Remaining' => '499',
                'X-RateLimit-Reset' => '1726959999',
            ],
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $this->assertNull($transport->getLastRateLimitInfo());

        $response = $transport->request('GET', '/v1/data');
        $this->assertSame(200, $response->getStatusCode());

        $info = $transport->getLastRateLimitInfo();
        $this->assertInstanceOf(RateLimitInfoDto::class, $info);
        $this->assertSame(500, $info->limit);
        $this->assertSame(500, $info->getLimit());
        $this->assertSame(499, $info->remaining);
        $this->assertSame(499, $info->getRemaining());
        $this->assertSame(1726959999, $info->reset);
        $this->assertSame(1726959999, $info->getReset());
        $this->assertNull($info->retryAfter);
    }

    public function testSuccessfulResponseWithoutRateLimitHeadersReturnsNull(): void
    {
        $mockResponse = new MockResponse('{"data": "success"}', [
            'http_code' => 200,
            'response_headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $transport->request('GET', '/v1/data');
        $this->assertNull($transport->getLastRateLimitInfo());
    }

    /**
     * @param class-string<\Throwable> $expectedExceptionClass
     */
    #[DataProvider('errorStatusCodeProvider')]
    public function testErrorStatusCodes(int $statusCode, string $expectedExceptionClass): void
    {
        $mockResponse = new MockResponse('{"title": "Error"}', [
            'http_code' => $statusCode,
            'response_headers' => ['Content-Type' => 'application/problem+json']
        ]);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

        $this->expectException(\KinetiStack\Sdk\Exception\KinetiException::class);
        // The message should fall back to the raw content
        $this->expectExceptionMessage('API Error 502: <html><body>502 Bad Gateway</body></html>');

        $transport->request('GET', '/v1/test');
    }

    public function testTransportException(): void
    {
        $mockResponse = new MockResponse('', ['error' => 'cURL error 28: Operation timed out']);
        $client = new MockHttpClient($mockResponse);
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client);

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
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
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
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
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
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
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

    public function testExhaustedRetriesThrowsRateLimitExceededExceptionWithRetryAfter(): void
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
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
            'max_retries' => 1,
        ]);

        try {
            $transport->request('GET', '/v1/test');
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
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
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', $client, [
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

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidApiHostProvider(): array
    {
        return [
            'query parameter' => ['https://api.test?foo=bar'],
            'fragment' => ['https://api.test#fragment'],
            'user and pass' => ['https://user:pass@api.test'],
            'user only' => ['https://user@api.test'],
            'subpath' => ['https://api.test/subpath'],
        ];
    }

    #[DataProvider('invalidApiHostProvider')]
    public function testInvalidApiHostThrowsException(string $invalidHost): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid API host URL');

        new HttpTransport($invalidHost, 'X-Kineti-Key', 'test-key', new MockHttpClient());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPathProvider(): array
    {
        return [
            'relative dot-dot' => ['..'],
            'relative dot-dot slash' => ['../'],
            'relative dot-dot backslash' => ['..\\'],
            'double encoded traversal' => ['/%252e%252e/'],
            'triple encoded traversal' => ['/%25252e%25252e/'],
            'quadruple encoded traversal' => ['/%2525252e%2525252e/'],
            'quadruple encoded backslash' => ['/api/%2525255c/resource'],
            'null byte' => ["\0"],
            'carriage return' => ["\r"],
            'newline' => ["\n"],
            'path with leading slash and null byte' => ["/\0"],
            'path with leading slash and carriage return' => ["/\r"],
            'path with leading slash and newline' => ["/\n"],
            'path with backslash' => ['/api/\\/resource'],
            'encoded backslash' => ['/api/%5c/resource'],
            'dot segment' => ['/api/./resource'],
            'trailing dot segment' => ['/api/.'],
            'dot-dot segment' => ['/api/../resource'],
            'trailing dot-dot segment' => ['/api/..'],
        ];
    }

    #[DataProvider('invalidPathProvider')]
    public function testInvalidPathThrowsException(string $invalidPath): void
    {
        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'test-key', new MockHttpClient());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request path. Must be a relative path starting with a single forward slash and cannot contain traversal segments.');

        $transport->request('GET', $invalidPath);
    }

    public function testDebugInfoRedactsSensitiveHeadersAndOptions(): void
    {
        $options = [
            'timeout' => 15,
            'auth' => 'secret-auth',
            'auth_bearer' => 'secret-bearer-token',
            'auth_basic' => ['admin', 'secret-password'],
            'proxy' => 'http://proxyuser:proxypass@proxy.test:8080',
            'headers' => [
                'Authorization' => 'Bearer secret-bearer',
                'x-kineti-key' => 'secret-kineti-key',
                'Proxy-Authorization' => 'Basic proxy-secret',
                'Cookie' => 'session_id=abcdef12345',
                'Set-Cookie' => 'auth=xyz',
                'X-Api-Key' => 'secret-x-api-key',
                'api-key' => 'secret-api-key',
                'Token' => 'secret-token-header',
                'X-Public-Header' => 'public-value',
            ],
        ];

        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'header-key', new MockHttpClient(), $options);
        $debugInfo = $transport->__debugInfo();

        $this->assertSame('[REDACTED]', $debugInfo['authHeaderValue']);
        $delegate = $debugInfo['delegate'];
        $this->assertInstanceOf(\KinetiStack\Sdk\Transport\SymfonyTransport::class, $delegate);
        $delegateDebug = $delegate->__debugInfo();

        $defaultOptions = $delegateDebug['defaultOptions'];
        $this->assertIsArray($defaultOptions);
        $this->assertSame(15, $defaultOptions['timeout']);
        $this->assertSame('[REDACTED]', $defaultOptions['auth']);
        $this->assertSame('[REDACTED]', $defaultOptions['auth_bearer']);
        $this->assertSame('[REDACTED]', $defaultOptions['auth_basic']);
        $this->assertSame('http://[REDACTED]:[REDACTED]@proxy.test:8080', $defaultOptions['proxy']);

        $headers = $defaultOptions['headers'];
        $this->assertIsArray($headers);
        $this->assertSame('[REDACTED]', $headers['Authorization']);
        $this->assertSame('[REDACTED]', $headers['x-kineti-key']);
        $this->assertSame('[REDACTED]', $headers['Proxy-Authorization']);
        $this->assertSame('[REDACTED]', $headers['Cookie']);
        $this->assertSame('[REDACTED]', $headers['Set-Cookie']);
        $this->assertSame('[REDACTED]', $headers['X-Api-Key']);
        $this->assertSame('[REDACTED]', $headers['api-key']);
        $this->assertSame('[REDACTED]', $headers['Token']);
        $this->assertSame('public-value', $headers['X-Public-Header']);
    }

    public function testDebugInfoRedactsArrayProxyCredentials(): void
    {
        $options = [
            'proxy' => [
                'http' => 'http://usr:pwd@proxy.test:8080',
                'username' => 'testuser',
                'password' => 'testpass',
                'host' => 'proxy.test',
            ],
        ];

        $transport = new HttpTransport('https://api.test', 'X-Kineti-Key', 'header-key', new MockHttpClient(), $options);
        $debugInfo = $transport->__debugInfo();
        $delegate = $debugInfo['delegate'];
        $this->assertInstanceOf(\KinetiStack\Sdk\Transport\SymfonyTransport::class, $delegate);
        $delegateDebug = $delegate->__debugInfo();

        $defaultOptions = $delegateDebug['defaultOptions'];
        $this->assertIsArray($defaultOptions);
        $proxy = $defaultOptions['proxy'];
        $this->assertIsArray($proxy);
        $this->assertSame('http://[REDACTED]:[REDACTED]@proxy.test:8080', $proxy['http']);
        $this->assertSame('[REDACTED]', $proxy['username']);
        $this->assertSame('[REDACTED]', $proxy['password']);
        $this->assertSame('proxy.test', $proxy['host']);
    }
}
