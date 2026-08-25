<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ValidationException;
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
}
