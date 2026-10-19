<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use Http\Discovery\Psr18ClientDiscovery;
use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Exception\TransportException;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpTransport implements TransportInterface
{
    private readonly TransportInterface $delegate;
    private ?RateLimitInfoDto $lastRateLimitInfo = null;

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $client
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        private readonly string $apiHost,
        #[\SensitiveParameter]
        private readonly string $authHeaderName,
        #[\SensitiveParameter]
        private readonly string $authHeaderValue = '',
        HttpClientInterface|ClientInterface|TransportInterface|null $client = null,
        array $defaultOptions = [],
    ) {
        if ($client instanceof TransportInterface) {
            $this->delegate = $client;
        } elseif ($client instanceof HttpClientInterface) {
            $this->delegate = new SymfonyTransport($apiHost, $this->authHeaderName, $this->authHeaderValue, $client, $defaultOptions);
        } elseif ($client instanceof ClientInterface) {
            $this->delegate = new Psr18Transport($apiHost, $this->authHeaderName, $this->authHeaderValue, $client, $defaultOptions);
        } else {
            $this->delegate = self::createDiscoveredTransport($apiHost, $this->authHeaderName, $this->authHeaderValue, $defaultOptions);
        }
    }

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $client
     * @param array<string, mixed> $defaultOptions
     */
    public static function withApiKey(
        string $apiHost,
        #[\SensitiveParameter]
        string $apiKey,
        HttpClientInterface|ClientInterface|TransportInterface|null $client = null,
        array $defaultOptions = []
    ): self {
        return new self($apiHost, 'X-Kineti-Key', $apiKey, $client, $defaultOptions);
    }

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $client
     * @param array<string, mixed> $defaultOptions
     */
    public static function withBearerToken(
        string $apiHost,
        #[\SensitiveParameter]
        string $token,
        HttpClientInterface|ClientInterface|TransportInterface|null $client = null,
        array $defaultOptions = []
    ): self {
        $headerValue = $token !== '' ? (str_starts_with($token, 'Bearer ') ? $token : 'Bearer ' . $token) : '';

        return new self($apiHost, 'Authorization', $headerValue, $client, $defaultOptions);
    }

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $client
     * @param array<string, mixed> $defaultOptions
     */
    public static function withHeader(
        string $apiHost,
        #[\SensitiveParameter]
        string $headerName,
        #[\SensitiveParameter]
        string $headerValue,
        HttpClientInterface|ClientInterface|TransportInterface|null $client = null,
        array $defaultOptions = []
    ): self {
        return new self($apiHost, $headerName, $headerValue, $client, $defaultOptions);
    }

    public function request(string $method, string $path, array $options = []): TransportResponseInterface
    {
        try {
            $response = $this->delegate->request($method, $path, $options);
            $this->lastRateLimitInfo = $this->delegate->getLastRateLimitInfo()
                ?? RateLimitInfoDto::fromHeaders($response->getHeaders());

            return $response;
        } catch (\Throwable $e) {
            $this->lastRateLimitInfo = $this->delegate->getLastRateLimitInfo() ?? $this->lastRateLimitInfo;
            throw $e;
        }
    }

    public function requestStream(string $method, string $path, array $options = []): TransportResponseInterface
    {
        try {
            $response = $this->delegate->requestStream($method, $path, $options);
            $this->lastRateLimitInfo = $this->delegate->getLastRateLimitInfo()
                ?? RateLimitInfoDto::fromHeaders($response->getHeaders());

            return $response;
        } catch (\Throwable $e) {
            $this->lastRateLimitInfo = $this->delegate->getLastRateLimitInfo() ?? $this->lastRateLimitInfo;
            throw $e;
        }
    }

    public function withAuthHeaderValue(#[\SensitiveParameter] string $authHeaderValue): self
    {
        return new self(
            $this->apiHost,
            $this->authHeaderName,
            $authHeaderValue,
            $this->delegate->withAuthHeaderValue($authHeaderValue)
        );
    }

    public function getApiHost(): string
    {
        return $this->apiHost;
    }

    public function getDelegate(): TransportInterface
    {
        return $this->delegate;
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

    public function getLastRateLimitInfo(): ?RateLimitInfoDto
    {
        return $this->delegate->getLastRateLimitInfo() ?? $this->lastRateLimitInfo;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $info = get_object_vars($this);
        if ($this->authHeaderValue !== '') {
            $info['authHeaderValue'] = '[REDACTED]';
        }

        return $info;
    }

    /**
     * @param array<string, mixed> $defaultOptions
     */
    private static function createDiscoveredTransport(
        string $apiHost,
        #[\SensitiveParameter]
        string $authHeaderName,
        #[\SensitiveParameter]
        string $authHeaderValue,
        array $defaultOptions = []
    ): TransportInterface {
        try {
            $psr18Client = Psr18ClientDiscovery::find();
            return new Psr18Transport($apiHost, $authHeaderName, $authHeaderValue, $psr18Client, $defaultOptions);
        } catch (\Throwable) {
            if (class_exists(HttpClient::class)) {
                return new SymfonyTransport($apiHost, $authHeaderName, $authHeaderValue, null, $defaultOptions);
            }

            throw new TransportException(
                'No HTTP client found. Please install a PSR-18 HTTP client (e.g. guzzlehttp/guzzle) or symfony/http-client.'
            );
        }
    }
}
