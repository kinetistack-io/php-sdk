<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\NotFoundException;
use KinetiStack\Sdk\Exception\PayloadTooLargeException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\Exception\ValidationException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\Retry\RetryStrategyInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HttpTransport
{
    private readonly HttpClientInterface $client;

    /**
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        private readonly string $apiHost,
        private readonly string $apiKey,
        ?HttpClientInterface $client = null,
        private readonly array $defaultOptions = []
    ) {
        $baseClient = $client ?? HttpClient::create();

        if (isset($defaultOptions['max_retries']) && (int) $defaultOptions['max_retries'] > 0 && !$baseClient instanceof RetryableHttpClient) {
            /** @var RetryStrategyInterface $strategy */
            $strategy = $defaultOptions['retry_strategy'] ?? new GenericRetryStrategy(
                [429, 503],
                1000,
                2.0,
                0,
                0.1
            );
            $baseClient = new RetryableHttpClient($baseClient, $strategy, (int) $defaultOptions['max_retries']);
        }

        $this->client = $baseClient;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $url = rtrim($this->apiHost, '/') . '/' . ltrim($path, '/');

        $mergedOptions = array_merge($this->defaultOptions, $options);
        unset($mergedOptions['retry_strategy']);
        if (!$this->client instanceof RetryableHttpClient) {
            unset($mergedOptions['max_retries']);
        }

        $headers = array_merge(
            ['Accept' => 'application/json'],
            $this->defaultOptions['headers'] ?? [],
            $options['headers'] ?? []
        );

        // If authorization is not explicitly provided, default to X-Kineti-Key
        $hasAuth = false;
        foreach (array_keys($headers) as $key) {
            if (strtolower((string) $key) === 'authorization' || strtolower((string) $key) === 'x-kineti-key') {
                $hasAuth = true;
                break;
            }
        }
        if (!$hasAuth && $this->apiKey !== '') {
            $headers['X-Kineti-Key'] = $this->apiKey;
        }

        $mergedOptions['headers'] = $headers;

        try {
            $response = $this->client->request($method, $url, $mergedOptions);
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if ($statusCode >= 400) {
            $this->handleErrorResponse($response, $statusCode);
        }

        return $response;
    }

    private function handleErrorResponse(ResponseInterface $response, int $statusCode): void
    {
        try {
            $content = $response->toArray(false);
        } catch (\Throwable $e) {
            try {
                $rawContent = $response->getContent(false);
                $content = ['detail' => $rawContent !== '' ? $rawContent : 'Failed to read error response: ' . $e->getMessage()];
            } catch (\Throwable $e2) {
                throw new TransportException('Failed to read error response: ' . $e2->getMessage(), 0, $e2);
            }
        }

        $message = $content['detail'] ?? $content['title'] ?? 'Unknown error occurred';

        throw match ($statusCode) {
            401 => new AuthenticationException($message),
            403 => new AuthorizationException($message),
            404 => new NotFoundException($message),
            413 => new PayloadTooLargeException($message),
            422 => new ValidationException($message, $content['violations'] ?? []),
            429 => new RateLimitException($message, $this->parseRetryAfter($response)),
            500 => new ServerException($message),
            503 => new ServiceUnavailableException($message),
            default => new KinetiException(sprintf('API Error %d: %s', $statusCode, $message)),
        };
    }

    private function parseRetryAfter(ResponseInterface $response): ?int
    {
        try {
            $headers = $response->getHeaders(false);
            if (isset($headers['retry-after'][0])) {
                return (int) $headers['retry-after'][0];
            }
        } catch (\Throwable) {
            // Ignore
        }
        return null;
    }
}
