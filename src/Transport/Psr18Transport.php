<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
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

    /**
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        private readonly string $apiHost,
        private readonly string $apiKey,
        ?ClientInterface $client = null,
        private readonly array $defaultOptions = [],
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    public function request(string $method, string $path, array $options = []): TransportResponseInterface
    {
        $url = rtrim($this->apiHost, '/') . '/' . ltrim($path, '/');

        $mergedOptions = array_merge($this->defaultOptions, $options);

        if (!empty($mergedOptions['query']) && is_array($mergedOptions['query'])) {
            $qs = http_build_query($mergedOptions['query']);
            $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
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

        $maxRetries = (int) ($mergedOptions['max_retries'] ?? 0);
        $attempts = 0;

        while (true) {
            $attempts++;

            $request = $this->requestFactory->createRequest($method, $url);
            if ($stream !== null) {
                if ($stream->isSeekable()) {
                    $stream->rewind();
                } elseif ($attempts > 1) {
                    throw new TransportException('Cannot retry request with non-seekable body stream.');
                }
                $request = $request->withBody($stream);
            }

            foreach ($headers as $name => $value) {
                $request = $request->withHeader((string) $name, $value);
            }

            try {
                $psrResponse = $this->client->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                throw new TransportException($e->getMessage(), 0, $e);
            }

            $statusCode = $psrResponse->getStatusCode();

            if (($statusCode === 429 || $statusCode === 503) && $attempts <= $maxRetries) {
                $retryAfter = ResponseErrorHandler::parseRetryAfter($psrResponse->getHeaders());
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

    public function getClient(): ClientInterface
    {
        return $this->client;
    }
}
