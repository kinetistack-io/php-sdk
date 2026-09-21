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
    private readonly string $authHeaderName;
    private readonly string $authHeaderValue;
    /** @var array<string, mixed> */
    private readonly array $defaultOptions;

    /**
     * @param HttpClientInterface|array<string, mixed>|null $clientOrDefaultOptions
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        private readonly string $apiHost,
        string $authHeaderName,
        string|HttpClientInterface|null $authHeaderValueOrClient = null,
        HttpClientInterface|array|null $clientOrDefaultOptions = null,
        array $defaultOptions = []
    ) {
        if (is_string($authHeaderValueOrClient)) {
            $this->authHeaderName = $authHeaderName;
            $this->authHeaderValue = $authHeaderValueOrClient;
            /** @var HttpClientInterface|null $baseClient */
            $baseClient = $clientOrDefaultOptions instanceof HttpClientInterface ? $clientOrDefaultOptions : null;
            $this->defaultOptions = is_array($clientOrDefaultOptions) ? $clientOrDefaultOptions : $defaultOptions;
        } else {
            // Legacy signature: ($apiHost, $apiKey, $client, $defaultOptions)
            $this->authHeaderName = 'X-Kineti-Key';
            $this->authHeaderValue = $authHeaderName;
            /** @var HttpClientInterface|null $baseClient */
            $baseClient = $authHeaderValueOrClient;
            $this->defaultOptions = is_array($clientOrDefaultOptions) ? $clientOrDefaultOptions : $defaultOptions;
        }

        $baseClient = $baseClient ?? HttpClient::create();

        if (
            isset($this->defaultOptions['max_retries'])
            && (int) $this->defaultOptions['max_retries'] > 0
            && !$baseClient instanceof RetryableHttpClient
            && class_exists(RetryableHttpClient::class)
        ) {
            /** @var RetryStrategyInterface $strategy */
            $strategy = $this->defaultOptions['retry_strategy'] ?? new GenericRetryStrategy(
                [429, 503],
                1000,
                2.0,
                0,
                0.1
            );
            $baseClient = new RetryableHttpClient($baseClient, $strategy, (int) $this->defaultOptions['max_retries']);
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
        $targetHeaderLower = strtolower($this->authHeaderName);
        foreach (array_keys($headers) as $key) {
            $keyLower = strtolower((string) $key);
            if ($keyLower === $targetHeaderLower || $keyLower === 'authorization' || $keyLower === 'x-kineti-key') {
                $hasAuth = true;
                break;
            }
        }
        if (!$hasAuth && $this->authHeaderValue !== '' && $this->authHeaderName !== '') {
            $headers[$this->authHeaderName] = $this->authHeaderValue;
        }

        $mergedOptions['headers'] = $headers;

        try {
            $response = $this->client->request($method, $url, $mergedOptions);
            $transportResponse = new SymfonyTransportResponse($response, $this->client);
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

    public function requestStream(string $method, string $path, array $options = []): TransportResponseInterface
    {
        $options['buffer'] = false;
        $options['headers'] = array_merge(
            ['Accept' => 'text/event-stream'],
            $options['headers'] ?? []
        );

        return $this->request($method, $path, $options);
    }

    public function withAuthHeaderValue(string $authHeaderValue): self
    {
        return new self(
            $this->apiHost,
            $this->authHeaderName,
            $authHeaderValue,
            $this->client,
            $this->defaultOptions
        );
    }

    public function getApiHost(): string
    {
        return $this->apiHost;
    }

    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }

    public function getAuthHeaderName(): string
    {
        return $this->authHeaderName;
    }

    public function getAuthHeaderValue(): string
    {
        return $this->authHeaderValue;
    }

    public function getApiKey(): string
    {
        return $this->authHeaderValue;
    }
}
