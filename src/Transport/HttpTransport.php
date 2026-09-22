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
    private readonly string $authHeaderName;
    private readonly string $authHeaderValue;
    private ?RateLimitInfoDto $lastRateLimitInfo = null;

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|array<string, mixed>|null $clientOrDefaultOptions
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        private readonly string $apiHost,
        string $authHeaderName,
        string|HttpClientInterface|ClientInterface|TransportInterface|null $authHeaderValueOrClient = null,
        HttpClientInterface|ClientInterface|TransportInterface|array|null $clientOrDefaultOptions = null,
        array $defaultOptions = []
    ) {
        if (is_string($authHeaderValueOrClient)) {
            $this->authHeaderName = $authHeaderName;
            $this->authHeaderValue = $authHeaderValueOrClient;
            /** @var HttpClientInterface|ClientInterface|TransportInterface|null $actualClient */
            $actualClient = ($clientOrDefaultOptions instanceof HttpClientInterface
                || $clientOrDefaultOptions instanceof ClientInterface
                || $clientOrDefaultOptions instanceof TransportInterface)
                ? $clientOrDefaultOptions
                : null;
            $actualOptions = is_array($clientOrDefaultOptions) ? $clientOrDefaultOptions : $defaultOptions;
        } else {
            // Legacy signature: ($apiHost, $apiKey, $client, $defaultOptions)
            $this->authHeaderName = 'X-Kineti-Key';
            $this->authHeaderValue = $authHeaderName;
            /** @var HttpClientInterface|ClientInterface|TransportInterface|null $actualClient */
            $actualClient = $authHeaderValueOrClient;
            $actualOptions = is_array($clientOrDefaultOptions) ? $clientOrDefaultOptions : $defaultOptions;
        }

        if ($actualClient instanceof TransportInterface) {
            $this->delegate = $actualClient;
        } elseif ($actualClient instanceof HttpClientInterface) {
            $this->delegate = new SymfonyTransport($apiHost, $this->authHeaderName, $this->authHeaderValue, $actualClient, $actualOptions);
        } elseif ($actualClient instanceof ClientInterface) {
            $this->delegate = new Psr18Transport($apiHost, $this->authHeaderName, $this->authHeaderValue, $actualClient, $actualOptions);
        } else {
            $this->delegate = self::createDiscoveredTransport($apiHost, $this->authHeaderName, $this->authHeaderValue, $actualOptions);
        }
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

    public function withAuthHeaderValue(string $authHeaderValue): self
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
     * @param array<string, mixed> $defaultOptions
     */
    private static function createDiscoveredTransport(
        string $apiHost,
        string $authHeaderName,
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
