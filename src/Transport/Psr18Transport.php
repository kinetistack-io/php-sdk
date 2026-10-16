<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class Psr18Transport implements TransportInterface
{
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly string $authHeaderName;
    private readonly string $authHeaderValue;
    /** @var array<string, mixed> */
    private readonly array $defaultOptions;
    private ?RateLimitInfoDto $lastRateLimitInfo = null;

    /**
     * @param ClientInterface|array<string, mixed>|null $clientOrDefaultOptions
     * @param array<string, mixed>|RequestFactoryInterface|null $defaultOptionsOrRequestFactory
     */
    public function __construct(
        private readonly string $apiHost,
        #[\SensitiveParameter]
        string $authHeaderName,
        #[\SensitiveParameter]
        string|ClientInterface|null $authHeaderValueOrClient = null,
        ClientInterface|array|null $clientOrDefaultOptions = null,
        array|RequestFactoryInterface|null $defaultOptionsOrRequestFactory = [],
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        if (is_string($authHeaderValueOrClient)) {
            $this->authHeaderName = $authHeaderName;
            $this->authHeaderValue = $authHeaderValueOrClient;
            /** @var ClientInterface|null $actualClient */
            $actualClient = $clientOrDefaultOptions instanceof ClientInterface ? $clientOrDefaultOptions : null;
            $this->defaultOptions = is_array($clientOrDefaultOptions)
                ? $clientOrDefaultOptions
                : (is_array($defaultOptionsOrRequestFactory) ? $defaultOptionsOrRequestFactory : []);
            $actualReqFactory = $requestFactory ?? ($defaultOptionsOrRequestFactory instanceof RequestFactoryInterface ? $defaultOptionsOrRequestFactory : null);
            $actualStreamFactory = $streamFactory;
        } else {
            // Legacy signature: ($apiHost, $apiKey, $client, $defaultOptions, $requestFactory, $streamFactory)
            $this->authHeaderName = 'X-Kineti-Key';
            $this->authHeaderValue = $authHeaderName;
            /** @var ClientInterface|null $actualClient */
            $actualClient = $authHeaderValueOrClient;
            $this->defaultOptions = is_array($clientOrDefaultOptions) ? $clientOrDefaultOptions : [];
            $actualReqFactory = $defaultOptionsOrRequestFactory instanceof RequestFactoryInterface ? $defaultOptionsOrRequestFactory : $requestFactory;
            $actualStreamFactory = $streamFactory ?? ($requestFactory instanceof StreamFactoryInterface ? $requestFactory : null);
        }

        $this->client = $actualClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $actualReqFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $actualStreamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    public function request(string $method, string $path, array $options = []): TransportResponseInterface
    {
        $url = rtrim($this->apiHost, '/') . '/' . ltrim($path, '/');

        $mergedOptions = array_merge($this->defaultOptions, $options);

        if (!empty($mergedOptions['query']) && is_array($mergedOptions['query'])) {
            $qs = http_build_query($mergedOptions['query']);
            $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
        }

        /** @var array<string, mixed> $defaultHeaders */
        $defaultHeaders = is_array($this->defaultOptions['headers'] ?? null) ? $this->defaultOptions['headers'] : [];
        /** @var array<string, mixed> $optionHeaders */
        $optionHeaders = is_array($options['headers'] ?? null) ? $options['headers'] : [];
        $headers = array_merge(
            ['Accept' => 'application/json'],
            $defaultHeaders,
            $optionHeaders
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

        $stream = null;
        if (isset($mergedOptions['json'])) {
            try {
                $json = json_encode($mergedOptions['json'], JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new TransportException('Failed to JSON encode request payload: ' . $e->getMessage(), 0, $e);
            }
            $stream = $this->streamFactory->createStream($json);
            $hasContentType = false;
            foreach (array_keys($headers) as $key) {
                if (strtolower((string) $key) === 'content-type') {
                    $hasContentType = true;
                    break;
                }
            }
            if (!$hasContentType) {
                $headers['Content-Type'] = 'application/json';
            }
        } elseif (isset($mergedOptions['body'])) {
            $body = $mergedOptions['body'];
            $isCallable = is_callable($body) && !is_string($body);
            $bodyFactory = $isCallable ? $body : null;
            if ($isCallable) {
                $body = $body();
            }

            if (is_string($body)) {
                $stream = $this->streamFactory->createStream($body);
            } elseif (is_resource($body)) {
                $stream = $this->streamFactory->createStreamFromResource($body);
            } elseif ($body instanceof \Traversable) {
                $temp = fopen('php://temp', 'w+b');
                if ($temp === false) {
                    throw new TransportException('Failed to open temporary stream for request body.');
                }
                foreach ($body as $chunk) {
                    fwrite($temp, (string) $chunk);
                }
                rewind($temp);
                $stream = $this->streamFactory->createStreamFromResource($temp);
            } elseif ($body instanceof StreamInterface) {
                $stream = $body;
            }
        }

        $maxRetries = isset($mergedOptions['max_retries']) && is_numeric($mergedOptions['max_retries'])
            ? (int) $mergedOptions['max_retries']
            : 0;
        $attempts = 0;

        while (true) {
            $attempts++;

            $request = $this->requestFactory->createRequest($method, $url);
            if ($stream !== null) {
                if ($stream->isSeekable()) {
                    $stream->rewind();
                } elseif (isset($bodyFactory)) {
                    $recreated = $bodyFactory();
                    if ($recreated instanceof \Traversable) {
                        $temp = fopen('php://temp', 'w+b');
                        if ($temp === false) {
                            throw new TransportException('Failed to open temporary stream for request body.');
                        }
                        foreach ($recreated as $chunk) {
                            fwrite($temp, (string) $chunk);
                        }
                        rewind($temp);
                        $stream = $this->streamFactory->createStreamFromResource($temp);
                    } elseif (is_resource($recreated)) {
                        $stream = $this->streamFactory->createStreamFromResource($recreated);
                    } elseif (is_string($recreated)) {
                        $stream = $this->streamFactory->createStream($recreated);
                    } elseif ($recreated instanceof StreamInterface) {
                        $stream = $recreated;
                    }
                } elseif ($attempts > 1) {
                    throw new TransportException('Cannot retry request with non-seekable body stream.');
                }
                $request = $request->withBody($stream);
            }

            foreach ($headers as $name => $value) {
                if (is_string($value) || (is_array($value) && array_is_list($value))) {
                    /** @var string|list<string> $value */
                    $request = $request->withHeader((string) $name, $value);
                } elseif (is_scalar($value)) {
                    $request = $request->withHeader((string) $name, (string) $value);
                }
            }

            try {
                $psrResponse = $this->client->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                throw new TransportException($e->getMessage(), 0, $e);
            }

            $statusCode = $psrResponse->getStatusCode();

            if (($statusCode === 429 || $statusCode === 503) && $attempts <= $maxRetries) {
                $retryAfter = RateLimitInfoDto::fromHeaders($psrResponse->getHeaders())?->retryAfter;
                $delaySeconds = $retryAfter !== null ? (float) $retryAfter : (1.0 * (2 ** ($attempts - 1)));

                $pauseHandler = $mergedOptions['pause_handler'] ?? null;
                if (is_callable($pauseHandler)) {
                    $pauseHandler($delaySeconds);
                } else {
                    $delaySeconds = max(0.0, $delaySeconds);
                    $intSeconds = (int) floor($delaySeconds);
                    $microSeconds = (int) round(($delaySeconds - $intSeconds) * 1_000_000);

                    if ($intSeconds > 0) {
                        sleep($intSeconds);
                    }
                    if ($microSeconds > 0) {
                        usleep($microSeconds);
                    }
                }

                continue;
            }

            $transportResponse = new Psr18TransportResponse($psrResponse);
            $this->lastRateLimitInfo = RateLimitInfoDto::fromHeaders($transportResponse->getHeaders());

            if ($statusCode >= 400) {
                ResponseErrorHandler::handleError(
                    $statusCode,
                    $transportResponse->getHeaders(),
                    $transportResponse->getContent()
                );
            }

            return $transportResponse;
        }
    }

    public function requestStream(string $method, string $path, array $options = []): TransportResponseInterface
    {
        /** @var array<string, mixed> $optionHeaders */
        $optionHeaders = is_array($options['headers'] ?? null) ? $options['headers'] : [];
        $options['headers'] = array_merge(
            ['Accept' => 'text/event-stream'],
            $optionHeaders
        );

        return $this->request($method, $path, $options);
    }

    public function withAuthHeaderValue(#[\SensitiveParameter] string $authHeaderValue): self
    {
        return new self(
            $this->apiHost,
            $this->authHeaderName,
            $authHeaderValue,
            $this->client,
            $this->defaultOptions,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function getApiHost(): string
    {
        return $this->apiHost;
    }

    public function getClient(): ClientInterface
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

    public function getLastRateLimitInfo(): ?RateLimitInfoDto
    {
        return $this->lastRateLimitInfo;
    }
}
