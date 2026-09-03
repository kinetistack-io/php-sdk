<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\Retry\RetryStrategyInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyTransport implements TransportInterface
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

        if (
            isset($defaultOptions['max_retries'])
            && (int) $defaultOptions['max_retries'] > 0
            && !$baseClient instanceof RetryableHttpClient
            && class_exists(RetryableHttpClient::class)
        ) {
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

    public function request(string $method, string $path, array $options = []): TransportResponseInterface
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
            $transportResponse = new SymfonyTransportResponse($response);
            $statusCode = $transportResponse->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if ($statusCode >= 400) {
            ResponseErrorHandler::handleError(
                $statusCode,
                $transportResponse->getHeaders(),
                $transportResponse->getContent()
            );
        }

        return $transportResponse;
    }

    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }
}
