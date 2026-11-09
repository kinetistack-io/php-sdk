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
    use TransportHelperTrait;

    public const DEFAULT_MAX_RETRY_DELAY_SECONDS = 60.0;

    private readonly ClientInterface|\GuzzleHttp\ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private ?RateLimitInfoDto $lastRateLimitInfo = null;

    /**
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        private readonly string $apiHost,
        #[\SensitiveParameter]
        private readonly string $authHeaderName,
        #[\SensitiveParameter]
        private readonly string $authHeaderValue = '',
        ClientInterface|\GuzzleHttp\ClientInterface|null $client = null,
        private readonly array $defaultOptions = [],
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->validateApiHost($apiHost);

        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    /**
     * @param array<string, mixed> $defaultOptions
     */
    public static function withApiKey(
        string $apiHost,
        #[\SensitiveParameter]
        string $apiKey,
        ClientInterface|\GuzzleHttp\ClientInterface|null $client = null,
        array $defaultOptions = [],
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): self {
        return new self($apiHost, 'X-Kineti-Key', $apiKey, $client, $defaultOptions, $requestFactory, $streamFactory);
    }

    /**
     * @param array<string, mixed> $defaultOptions
     */
    public static function withBearerToken(
        string $apiHost,
        #[\SensitiveParameter]
        string $token,
        ClientInterface|\GuzzleHttp\ClientInterface|null $client = null,
        array $defaultOptions = [],
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): self {
        $headerValue = $token !== '' ? (str_starts_with($token, 'Bearer ') ? $token : 'Bearer ' . $token) : '';

        return new self($apiHost, 'Authorization', $headerValue, $client, $defaultOptions, $requestFactory, $streamFactory);
    }

    /**
     * @param array<string, mixed> $defaultOptions
     */
    public static function withHeader(
        string $apiHost,
        #[\SensitiveParameter]
        string $headerName,
        #[\SensitiveParameter]
        string $headerValue,
        ClientInterface|\GuzzleHttp\ClientInterface|null $client = null,
        array $defaultOptions = [],
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ): self {
        return new self($apiHost, $headerName, $headerValue, $client, $defaultOptions, $requestFactory, $streamFactory);
    }

    public function request(string $method, string $path, array $options = []): TransportResponseInterface
    {
        $this->validatePath($path);

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
        $bodyFactory = null;
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
            if (!is_string($body) && is_callable($body)) {
                $bodyFactory = $body;
            } else {
                $stream = $this->createStreamFromBody($body);
            }
        }

        $maxRetries = isset($mergedOptions['max_retries']) && is_numeric($mergedOptions['max_retries'])
            ? (int) $mergedOptions['max_retries']
            : 0;
        $attempts = 0;

        while (true) {
            $attempts++;

            $request = $this->requestFactory->createRequest($method, $url);
            if ($bodyFactory !== null) {
                $rawBody = $bodyFactory();
                $stream = $this->createStreamFromBody($rawBody);
            }

            if ($stream !== null) {
                if (!$stream->isSeekable() && $maxRetries > 0) {
                    $stream = $this->bufferStream($stream);
                }

                if ($stream->isSeekable()) {
                    $stream->rewind();
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
                if ($this->client instanceof \GuzzleHttp\ClientInterface) {
                    $guzzleOptions = $this->buildGuzzleOptions($mergedOptions, !empty($mergedOptions['stream']));
                    $psrResponse = $this->client->send($request, $guzzleOptions);
                } else {
                    $psrResponse = $this->client->sendRequest($request);
                }
            } catch (ClientExceptionInterface|\GuzzleHttp\Exception\GuzzleException $e) {
                if ($attempts <= $maxRetries) {
                    $this->delayBackoff($attempts, $mergedOptions);
                    continue;
                }
                throw new TransportException($e->getMessage(), 0, $e);
            }

            $statusCode = $psrResponse->getStatusCode();

            if (($statusCode === 429 || $statusCode === 503) && $attempts <= $maxRetries) {
                $retryAfter = RateLimitInfoDto::fromHeaders($psrResponse->getHeaders())?->retryAfter;
                $this->delayBackoff($attempts, $mergedOptions, $retryAfter !== null ? (float) $retryAfter : null);

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

    /**
     * @param array<string, mixed> $options
     * @return array{http_errors: false, stream?: true, timeout?: float, verify?: bool|string}
     */
    private function buildGuzzleOptions(array $options, bool $isStream = false): array
    {
        /** @var array{http_errors: false, stream?: true, timeout?: float, verify?: bool|string} $guzzleOptions */
        $guzzleOptions = [
            'http_errors' => false,
        ];

        if ($isStream) {
            $guzzleOptions['stream'] = true;
        }

        if (isset($options['timeout']) && is_numeric($options['timeout'])) {
            $guzzleOptions['timeout'] = (float) $options['timeout'];
        }

        if (array_key_exists('verify_peer', $options) && $options['verify_peer'] === false) {
            $guzzleOptions['verify'] = false;
        } elseif (array_key_exists('verify_host', $options) && ($options['verify_host'] === false || $options['verify_host'] === 0)) {
            $guzzleOptions['verify'] = false;
        } elseif (array_key_exists('verify', $options)) {
            $verify = $options['verify'];
            if (is_bool($verify) || is_string($verify)) {
                $guzzleOptions['verify'] = $verify;
            }
        }

        return $guzzleOptions;
    }

    public function requestStream(string $method, string $path, array $options = []): TransportResponseInterface
    {
        $mergedOptions = array_merge($this->defaultOptions, $options);
        $url = rtrim($this->apiHost, '/') . '/' . ltrim($path, '/');
        $host = (string) parse_url($url, PHP_URL_HOST);

        $isMock = str_ends_with($host, '.test')
            || str_ends_with($host, '.example')
            || (interface_exists(\PHPUnit\Framework\MockObject\MockObject::class) && $this->client instanceof \PHPUnit\Framework\MockObject\MockObject);

        $isGuzzle = $this->client instanceof \GuzzleHttp\ClientInterface;
        $hasCurlFiber = extension_loaded('curl') && function_exists('curl_init') && class_exists(\Fiber::class);

        $useCurlStream = ($mergedOptions['curl_stream'] ?? null) === true
            || (($mergedOptions['curl_stream'] ?? null) !== false && $isGuzzle && !$isMock && $hasCurlFiber);

        if ($useCurlStream) {
            return $this->requestStreamWithCurl($method, $path, $options);
        }

        /** @var array<string, mixed> $optionHeaders */
        $optionHeaders = is_array($options['headers'] ?? null) ? $options['headers'] : [];
        $options['headers'] = array_merge(
            ['Accept' => 'text/event-stream'],
            $optionHeaders
        );
        $options['stream'] = true;

        return $this->request($method, $path, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function requestStreamWithCurl(string $method, string $path, array $options = []): TransportResponseInterface
    {
        $this->validatePath($path);
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
            $defaultHeaders,
            ['Accept' => 'text/event-stream'],
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

        $payload = null;
        if (isset($mergedOptions['json'])) {
            try {
                $payload = json_encode($mergedOptions['json'], JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new TransportException('Failed to JSON encode request payload: ' . $e->getMessage(), 0, $e);
            }
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
            $rawBody = $mergedOptions['body'];
            if (is_callable($rawBody)) {
                $rawBody = $rawBody();
            }

            if ($rawBody instanceof \Psr\Http\Message\StreamInterface) {
                try {
                    if ($rawBody->isSeekable()) {
                        $rawBody->rewind();
                    }
                    $payload = $rawBody->getContents();
                } catch (\Throwable $e) {
                    throw new TransportException('Failed to read request body stream: ' . $e->getMessage(), 0, $e);
                }
            } elseif (is_resource($rawBody)) {
                $payload = stream_get_contents($rawBody);
                if ($payload === false) {
                    throw new TransportException('Failed to read request body resource.');
                }
            } elseif ($rawBody instanceof \Traversable) {
                $chunks = [];
                foreach ($rawBody as $chunk) {
                    $chunks[] = (string) $chunk;
                }
                $payload = implode('', $chunks);
            } elseif (is_string($rawBody)) {
                $payload = $rawBody;
            } elseif ($rawBody !== null) {
                throw new \InvalidArgumentException('Unsupported request body type for streaming request: ' . get_debug_type($rawBody));
            }
        }

        $maxRetries = isset($mergedOptions['max_retries']) && is_numeric($mergedOptions['max_retries'])
            ? (int) $mergedOptions['max_retries']
            : 0;
        $attempts = 0;

        while (true) {
            $attempts++;

            $ch = curl_init($url);
            if ($ch === false) {
                throw new TransportException('Failed to initialize cURL handle.');
            }

            $httpMethod = $method !== '' ? $method : 'GET';
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
            if ($payload !== null && $payload !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            $curlHeaders = [];
            foreach ($headers as $name => $val) {
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if (is_scalar($v) || $v instanceof \Stringable) {
                            $curlHeaders[] = is_numeric($name) ? (string) $v : (string) $name . ': ' . (string) $v;
                        }
                    }
                } elseif (is_scalar($val) || $val instanceof \Stringable) {
                    $curlHeaders[] = is_numeric($name) ? (string) $val : (string) $name . ': ' . (string) $val;
                }
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

            $verifyPeer = $mergedOptions['verify_peer'] ?? $mergedOptions['verify'] ?? true;
            if ($verifyPeer === false) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            } elseif (is_string($verifyPeer) && $verifyPeer !== '') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_CAINFO, $verifyPeer);
            }

            if (array_key_exists('verify_host', $mergedOptions) && ($mergedOptions['verify_host'] === false || $mergedOptions['verify_host'] === 0)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $timeout = $mergedOptions['timeout'] ?? null;
            if (isset($timeout) && is_numeric($timeout)) {
                $timeoutMs = (int) ceil(((float) $timeout) * 1000);
                if ($timeoutMs > 0) {
                    curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, min($timeoutMs, 10000));
                }
            }

            curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

            try {
                $transportResponse = CurlFiberTransportResponse::fromCurlHandle($ch);
            } catch (TransportException $e) {
                if ($attempts <= $maxRetries) {
                    $this->delayBackoff($attempts, $mergedOptions);
                    continue;
                }
                throw $e;
            }

            $statusCode = $transportResponse->getStatusCode();
            $this->lastRateLimitInfo = RateLimitInfoDto::fromHeaders($transportResponse->getHeaders());

            if (($statusCode === 429 || $statusCode === 503) && $attempts <= $maxRetries) {
                $transportResponse->getContent();
                $retryAfter = $this->lastRateLimitInfo?->retryAfter;
                $this->delayBackoff($attempts, $mergedOptions, $retryAfter !== null ? (float) $retryAfter : null);
                continue;
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

    public function getClient(): ClientInterface|\GuzzleHttp\ClientInterface
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

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $info = get_object_vars($this);
        if ($this->authHeaderValue !== '') {
            $info['authHeaderValue'] = '[REDACTED]';
        }

        $info['client'] = $this->client::class;
        $info['requestFactory'] = $this->requestFactory::class;
        $info['streamFactory'] = $this->streamFactory::class;
        $info['defaultOptions'] = $this->redactDebugOptions($this->defaultOptions);

        return $info;
    }

    /**
     * @param \Traversable<mixed> $body
     */
    private function createStreamFromTraversable(\Traversable $body): StreamInterface
    {
        $temp = fopen('php://temp', 'w+b');
        if ($temp === false) {
            throw new TransportException('Failed to open temporary stream for request body.');
        }

        try {
            foreach ($body as $chunk) {
                if (!is_scalar($chunk) && !$chunk instanceof \Stringable) {
                    throw new TransportException(sprintf(
                        'Expected stream chunk to be scalar or \Stringable, got "%s".',
                        get_debug_type($chunk)
                    ));
                }

                fwrite($temp, (string) $chunk);
            }
            rewind($temp);

            return $this->streamFactory->createStreamFromResource($temp);
        } catch (\Throwable $e) {
            if (is_resource($temp)) {
                fclose($temp);
            }

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $mergedOptions
     */
    private function delayBackoff(int $attempts, array $mergedOptions, ?float $retryAfter = null): void
    {
        $maxRetryDelay = max(
            0.0,
            isset($mergedOptions['max_retry_delay']) && is_numeric($mergedOptions['max_retry_delay'])
                ? (float) $mergedOptions['max_retry_delay']
                : self::DEFAULT_MAX_RETRY_DELAY_SECONDS
        );

        $delaySeconds = $retryAfter !== null
            ? $retryAfter
            : (1.0 * (2 ** ($attempts - 1))) + (random_int(0, 250) / 1000.0);

        $delaySeconds = min(max(0.0, $delaySeconds), $maxRetryDelay);

        $pauseHandler = $mergedOptions['pause_handler'] ?? null;
        if (is_callable($pauseHandler)) {
            $pauseHandler($delaySeconds);
        } else {
            $intSeconds = (int) floor($delaySeconds);
            $microSeconds = (int) round(($delaySeconds - $intSeconds) * 1_000_000);

            if ($intSeconds > 0) {
                sleep($intSeconds);
            }
            if ($microSeconds > 0) {
                usleep($microSeconds);
            }
        }
    }

    private function createStreamFromBody(mixed $body): ?StreamInterface
    {
        if (is_string($body)) {
            return $this->streamFactory->createStream($body);
        }
        if (is_resource($body)) {
            return $this->streamFactory->createStreamFromResource($body);
        }
        if ($body instanceof \Traversable) {
            return $this->createStreamFromTraversable($body);
        }
        if ($body instanceof StreamInterface) {
            return $body;
        }

        return null;
    }

    private function bufferStream(StreamInterface $stream): StreamInterface
    {
        $temp = fopen('php://temp', 'w+b');
        if ($temp === false) {
            throw new TransportException('Failed to open temporary stream for request body.');
        }

        try {
            while (!$stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk === '') {
                    break;
                }
                fwrite($temp, $chunk);
            }
            rewind($temp);

            return $this->streamFactory->createStreamFromResource($temp);
        } catch (\Throwable $e) {
            if (is_resource($temp)) {
                fclose($temp);
            }

            throw $e;
        }
    }
}
