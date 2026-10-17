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
        #[\SensitiveParameter]
        string $authHeaderName,
        #[\SensitiveParameter]
        string|HttpClientInterface|ClientInterface|TransportInterface|null $authHeaderValueOrClient = null,
        HttpClientInterface|ClientInterface|TransportInterface|array|null $clientOrDefaultOptions = null,
        array $defaultOptions = []
    ) {
        $isStandardAuthHeader = in_array(strtolower($authHeaderName), ['authorization', 'x-kineti-key'], true);

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
        } elseif ($authHeaderValueOrClient === null && $isStandardAuthHeader) {
            $this->authHeaderName = $authHeaderName;
            $this->authHeaderValue = '';
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
    public static function withHeader(
        string $apiHost,
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
