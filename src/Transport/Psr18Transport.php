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

        $isGuzzle = $this->client instanceof \GuzzleHttp\ClientInterface;
        $hasCurlFiber = extension_loaded('curl') && function_exists('curl_init') && class_exists(\Fiber::class);
        $curlStreamOption = $mergedOptions['curl_stream'] ?? null;

        $useCurlStream = $curlStreamOption === true
            || (!$isGuzzle && $curlStreamOption !== false && $hasCurlFiber);

        if ($useCurlStream) {
            return $this->requestStreamWithCurl($method, $path, $options);
        }

        /** @var array<string, mixed> $optionHeaders */
        $optionHeaders = is_array($options['headers'] ?? null) ? $options['headers'] : [];
        if (!$this->hasAcceptHeader($optionHeaders)) {
            $options['headers'] = array_merge(
                ['Accept' => 'text/event-stream'],
                $optionHeaders
            );
        }
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

        $hasAccept = $this->hasAcceptHeader($optionHeaders);

        $headers = array_merge(
            $defaultHeaders,
            !$hasAccept ? ['Accept' => 'text/event-stream'] : [],
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
        $bodyFactory = null;
        $streamBody = null;

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
            if (!is_string($rawBody) && is_callable($rawBody)) {
                $bodyFactory = $rawBody;
            } elseif (is_string($rawBody)) {
                $payload = $rawBody;
            } elseif ($rawBody instanceof StreamInterface || is_resource($rawBody) || ($rawBody instanceof \Traversable)) {
                $streamBody = $rawBody;
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

            $currentBody = null;
            if ($bodyFactory !== null) {
                $payload = null;
                $currentBody = $bodyFactory();
                if ($currentBody !== null && !is_string($currentBody) && !($currentBody instanceof StreamInterface) && !is_resource($currentBody) && !($currentBody instanceof \Traversable)) {
                    throw new \InvalidArgumentException('Unsupported request body type for streaming request: ' . get_debug_type($currentBody));
                }
                if (is_string($currentBody)) {
                    $payload = $currentBody;
                    $currentBody = null;
                }
            } else {
                $currentBody = $streamBody;
            }

            if ($payload !== null && $payload !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            } elseif ($currentBody instanceof StreamInterface) {
                if ($currentBody->isSeekable()) {
                    $currentBody->rewind();
                } elseif ($attempts > 1) {
                    throw new TransportException('Cannot retry request with non-seekable body stream.');
                }

                curl_setopt($ch, CURLOPT_UPLOAD, true);
                $size = $currentBody->getSize();
                if ($size !== null && $size >= 0) {
                    curl_setopt($ch, CURLOPT_INFILESIZE, $size);
                }

                curl_setopt($ch, CURLOPT_READFUNCTION, static function (\CurlHandle $_ch, mixed $_fd, int $length) use ($currentBody): string {
                    if ($currentBody->eof()) {
                        return '';
                    }
                    return $currentBody->read($length);
                });
            } elseif (is_resource($currentBody)) {
                $meta = stream_get_meta_data($currentBody);
                if ($meta['seekable']) {
                    rewind($currentBody);
                } elseif ($attempts > 1) {
                    throw new TransportException('Cannot retry request with non-seekable body resource.');
                }

                curl_setopt($ch, CURLOPT_UPLOAD, true);
                $stat = fstat($currentBody);
                $size = $stat['size'] ?? null;
                if ($size !== null && ($size > 0 || ($size === 0 && $meta['seekable']))) {
                    curl_setopt($ch, CURLOPT_INFILESIZE, $size);
                }

                curl_setopt($ch, CURLOPT_READFUNCTION, static function (\CurlHandle $_ch, mixed $_fd, int $length) use ($currentBody): string {
                    if ($length <= 0 || feof($currentBody)) {
                        return '';
                    }
                    $chunk = fread($currentBody, $length);
                    return $chunk !== false ? $chunk : '';
                });
            } elseif ($currentBody instanceof \Traversable) {
                /** @var \Iterator<mixed, mixed> $iterator */
                $iterator = $currentBody instanceof \IteratorAggregate
                    ? $currentBody->getIterator()
                    : ($currentBody instanceof \Iterator ? $currentBody : new \IteratorIterator($currentBody));

                if ($attempts > 1) {
                    if ($currentBody instanceof \Generator) {
                        throw new TransportException('Cannot retry request with non-rewindable Traversable body.');
                    }
                    try {
                        $iterator->rewind();
                    } catch (\Throwable $e) {
                        throw new TransportException('Cannot retry request with non-rewindable Traversable body.', 0, $e);
                    }
                } else {
                    try {
                        $iterator->rewind();
                    } catch (\Throwable) {
                    }
                }

                curl_setopt($ch, CURLOPT_UPLOAD, true);
                $buffer = '';
                curl_setopt($ch, CURLOPT_READFUNCTION, static function (\CurlHandle $_ch, mixed $_fd, int $length) use ($iterator, &$buffer): string {
                    while (strlen($buffer) < $length && $iterator->valid()) {
                        $currentVal = $iterator->current();
                        if (is_scalar($currentVal) || $currentVal instanceof \Stringable) {
                            $buffer .= (string) $currentVal;
                        }
                        $iterator->next();
                    }
                    if ($buffer === '') {
                        return '';
                    }
                    $out = substr($buffer, 0, $length);
                    $buffer = substr($buffer, $length);
                    return $out;
                });
            }

            $curlHeaders = [];
            $hasExpect = false;
            foreach ($headers as $name => $val) {
                $values = is_array($val) ? $val : [$val];
                foreach ($values as $v) {
                    if (!is_scalar($v) && !($v instanceof \Stringable)) {
                        continue;
                    }
                    if (is_numeric($name)) {
                        $rawHeader = (string) $v;
                        if (preg_match('/[\r\n\0]/', $rawHeader) === 1) {
                            throw new \InvalidArgumentException(sprintf('Invalid header line "%s": contains control characters.', $rawHeader));
                        }
                        $parts = explode(':', $rawHeader, 2);
                        if (count($parts) !== 2) {
                            throw new \InvalidArgumentException(sprintf('Invalid header line "%s": missing colon separator.', $rawHeader));
                        }
                        $hName = trim($parts[0]);
                        $hVal = trim($parts[1]);
                        $line = $hName . ': ' . $hVal;
                    } else {
                        $hName = (string) $name;
                        $hVal = (string) $v;
                        $line = $hName . ': ' . $hVal;
                    }

                    if (preg_match('/^[!#$%&\'*+\\-.^_`|~0-9A-Za-z]+$/', $hName) !== 1) {
                        throw new \InvalidArgumentException(sprintf('Invalid header name "%s": does not match RFC 7230 token format.', $hName));
                    }

                    if (preg_match('/[\r\n\0]/', $hVal) === 1) {
                        throw new \InvalidArgumentException(sprintf('Invalid header value for "%s": contains control characters.', $hName));
                    }

                    if (strtolower($hName) === 'expect') {
                        $hasExpect = true;
                    }

                    $curlHeaders[] = $line;
                }
            }

            $isUpload = ($currentBody instanceof StreamInterface) || is_resource($currentBody) || ($currentBody instanceof \Traversable);
            if ($isUpload && !$hasExpect) {
                $curlHeaders[] = 'Expect:';
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

            $verifyPeer = $mergedOptions['verify_peer'] ?? $mergedOptions['verify'] ?? true;
            if ($verifyPeer === false) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            } elseif (is_string($verifyPeer) && $verifyPeer !== '') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                if (is_dir($verifyPeer)) {
                    curl_setopt($ch, CURLOPT_CAPATH, $verifyPeer);
                } else {
                    curl_setopt($ch, CURLOPT_CAINFO, $verifyPeer);
                }
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
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);

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
                $chunk = (string) $stream->read(8192);
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

    /**
     * @param array<mixed, mixed> $headers
     */
    private function hasAcceptHeader(array $headers): bool
    {
        foreach (array_keys($headers) as $key) {
            if (strtolower((string) $key) === 'accept') {
                return true;
            }
        }

        return false;
    }
}
