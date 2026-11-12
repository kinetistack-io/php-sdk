<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Client\ClientHelperTrait;
use KinetiStack\Sdk\Client\RagClient;
use KinetiStack\Sdk\Client\RagClientInterface;
use KinetiStack\Sdk\Client\SearchClient;
use KinetiStack\Sdk\Client\SearchClientInterface;
use KinetiStack\Sdk\Dto\ContextHintsDto;
use KinetiStack\Sdk\Dto\DocumentCollectionDto;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentListOptionsDto;
use KinetiStack\Sdk\Dto\HealthStatusDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Dto\VisionOptionsDto;
use KinetiStack\Sdk\Exception\JobTimeoutException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\PayloadTooLargeException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\Transport\HttpTransport;
use KinetiStack\Sdk\Transport\TransportInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class KinetiClient implements KinetiClientInterface
{
    use ClientHelperTrait;

    public const MAX_BATCH_PAYLOAD_BYTES = 10 * 1024 * 1024; // 10MB (10485760 bytes)
    public const DEFAULT_POLL_INTERVAL_SECONDS = 2;
    public const DEFAULT_MAX_POLL_INTERVAL_SECONDS = 10;
    public const DEFAULT_TIMEOUT_SECONDS = 60;
    public const MAX_SEARCH_OFFSET = SearchClient::MAX_SEARCH_OFFSET;

    private readonly string $apiHost;
    private string $apiKey;
    private readonly HttpClientInterface|ClientInterface|TransportInterface|null $httpClient;
    private TransportInterface $transport;
    private Configuration $configuration;
    private ?ModuleClientInterface $moduleClient = null;
    private ?SearchClientInterface $searchClient = null;
    private ?RagClientInterface $ragClient = null;

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $httpClient
     * @param Configuration|array<string, mixed> $options Default HTTP options and SDK configuration
     */
    public function __construct(
        string $apiHost,
        #[\SensitiveParameter]
        string $apiKey,
        HttpClientInterface|ClientInterface|TransportInterface|null $httpClient = null,
        Configuration|array $options = []
    ) {
        $this->apiHost = $apiHost;
        $this->apiKey = $apiKey;
        $this->httpClient = $httpClient;
        $this->configuration = Configuration::fromOptions($options);
        $httpOptions = $this->configuration->getHttpOptions();

        if ($httpClient instanceof TransportInterface) {
            $this->transport = $apiKey !== '' ? $httpClient->withAuthHeaderValue($apiKey) : $httpClient;
        } else {
            $this->transport = new HttpTransport($apiHost, 'X-Kineti-Key', $apiKey, $httpClient, $httpOptions);
        }
    }

    public function __clone(): void
    {
        $this->moduleClient = null;
        $this->searchClient = null;
        $this->ragClient = null;
        $this->transport = $this->transport->withAuthHeaderValue($this->apiKey);
    }

    /**
     * Return a new immutable instance configured with the specified API key.
     */
    public function withApiKey(#[\SensitiveParameter] string $apiKey): static
    {
        $clone = clone $this;
        $clone->apiKey = $apiKey;
        $clone->transport = $this->transport->withAuthHeaderValue($apiKey);

        return $clone;
    }

    /**
     * Return a new immutable instance configured with the specified SDK configuration.
     *
     * @param Configuration|array<string, mixed> $configuration
     */
    public function withConfiguration(Configuration|array $configuration): static
    {
        $clone = clone $this;
        if (is_array($configuration)) {
            $mergedOptions = array_merge($this->configuration->toArray(), $configuration);
            $clone->configuration = Configuration::fromOptions($mergedOptions);
        } else {
            $clone->configuration = $configuration;
        }

        $newHttpOptions = $clone->configuration->getHttpOptions();
        if ($newHttpOptions !== $this->configuration->getHttpOptions()) {
            if (method_exists($this->transport, 'withDefaultOptions')) {
                $clone->transport = $this->transport->withDefaultOptions($newHttpOptions);
            } elseif (!$this->httpClient instanceof TransportInterface) {
                $clone->transport = new HttpTransport(
                    $this->apiHost,
                    'X-Kineti-Key',
                    $this->apiKey,
                    $this->httpClient,
                    $newHttpOptions
                );
            }
        }

        return $clone;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * Get the SDK configuration.
     */
    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $info = get_object_vars($this);
        if ($this->apiKey !== '') {
            $info['apiKey'] = '[REDACTED]';
        }

        return $info;
    }

    /**
     * Get the rate limit information from the most recent request, or null if unavailable.
     */
    public function getLastRateLimitInfo(): ?RateLimitInfoDto
    {
        return $this->transport->getLastRateLimitInfo();
    }

    public function modules(): ModuleClientInterface
    {
        return $this->moduleClient ??= new ModuleClient($this->transport);
    }

    /**
     * Check if the API is alive.
     *
     * @throws KinetiException
     */
    public function healthz(): HealthStatusDto
    {
        $response = $this->transport->request('GET', '/healthz');

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return HealthStatusDto::fromArray($data);
    }

    /**
     * Check if the API and its dependencies are ready.
     *
     * @throws KinetiException
     */
    public function readyz(): HealthStatusDto
    {
        $response = $this->transport->request('GET', '/readyz');

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return HealthStatusDto::fromArray($data);
    }

    public function analyzeImage(
        string $imageUrl,
        ?ContextHintsDto $contextHints = null,
        ?VisionOptionsDto $options = null
    ): JobDto {
        $payload = [
            'image_url' => $imageUrl,
        ];

        if ($contextHints !== null && !$contextHints->isEmpty()) {
            $payload['context_hints'] = $contextHints->toArray();
        }

        if ($options !== null) {
            $payload['options'] = $options->toArray();
        }

        $response = $this->transport->request('POST', '/api/v1/images/analyze', [
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return JobDto::fromArray($data);
    }

    public function analyzeImageBinary(
        string $filePath,
        ?ContextHintsDto $contextHints = null,
        ?VisionOptionsDto $options = null
    ): JobDto {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('File does not exist: %s', $filePath));
        }

        $stream = fopen($filePath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException(sprintf('Failed to open file: %s', $filePath));
        }

        try {
            return $this->analyzeImageStream($stream, basename($filePath), $contextHints, $options);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param resource $stream
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws KinetiException
     */
    public function analyzeImageStream(
        $stream,
        string $filename = 'image.jpg',
        ?ContextHintsDto $contextHints = null,
        ?VisionOptionsDto $options = null
    ): JobDto {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Provided $stream is not a valid resource.');
        }

        $initialOffset = null;
        $meta = stream_get_meta_data($stream);
        if (!empty($meta['seekable'])) {
            $offset = ftell($stream);
            $initialOffset = is_int($offset) ? $offset : 0;
        }

        $fields = [];

        if ($contextHints !== null && !$contextHints->isEmpty()) {
            $fields['context_hints'] = json_encode($contextHints->toArray(), JSON_THROW_ON_ERROR);
        }

        if ($options !== null) {
            $fields['options'] = json_encode($options->toArray(), JSON_THROW_ON_ERROR);
        }

        [$contentType, $body] = $this->createMultipartStreamPayload($stream, $filename, $fields, $initialOffset);

        $response = $this->transport->request('POST', '/api/v1/images/analyze', [
            'headers' => [
                'Content-Type' => $contentType,
            ],
            'body' => $body,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return JobDto::fromArray($data);
    }

    /**
     * @throws \JsonException
     * @throws KinetiException
     */
    public function analyzeImageContent(
        string $binaryData,
        string $filename = 'image.jpg',
        ?ContextHintsDto $contextHints = null,
        ?VisionOptionsDto $options = null
    ): JobDto {
        $stream = fopen('php://temp', 'w+b');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Failed to open temporary stream for image content.');
        }

        try {
            $written = fwrite($stream, $binaryData);
            if ($written === false || $written < strlen($binaryData)) {
                throw new \RuntimeException('Failed to write complete image data to temporary stream.');
            }
            rewind($stream);

            return $this->analyzeImageStream($stream, $filename, $contextHints, $options);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param array<int, ImageInputDto|array<string, mixed>> $images
     * @throws PayloadTooLargeException
     * @throws KinetiException
     */
    public function submitBatchJob(array $images, ?VisionOptionsDto $options = null): JobDto
    {
        $formattedImages = array_map(function ($image) {
            if ($image instanceof ImageInputDto) {
                return $image->toArray();
            }
            return $image;
        }, $images);

        $payload = [
            'images' => $formattedImages,
        ];

        if ($options !== null) {
            $payload['options'] = $options->toArray();
        }

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        if (strlen($jsonPayload) > self::MAX_BATCH_PAYLOAD_BYTES) {
            throw new PayloadTooLargeException(sprintf(
                'Batch payload size exceeds the maximum limit of 10MB (%d bytes).',
                self::MAX_BATCH_PAYLOAD_BYTES
            ));
        }

        $response = $this->transport->request('POST', '/api/v1/jobs/batch-images', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $jsonPayload,
        ]);

        return JobDto::fromArray($response->toArray());
    }

    public function getJob(string $jobId): JobDto
    {
        $response = $this->transport->request('GET', sprintf('/api/v1/jobs/%s', $this->encodePathSegment($jobId, 'jobId')));

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return JobDto::fromArray($data);
    }

    /**
     * Poll an asynchronous job until it completes or times out.
     *
     * @param string $jobId
     * @param int $timeoutSeconds
     * @param int $pollIntervalSeconds
     * @param (callable(JobDto): void)|null $onProgress Optional callback called on each poll with the latest JobDto
     * @param int $maxPollIntervalSeconds
     * @param (callable(int): void)|null $sleeper Optional sleeper callback receiving microseconds, useful for testing without delays
     * @throws JobTimeoutException
     * @throws KinetiException
     */
    public function waitForJob(
        string $jobId,
        int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        int $pollIntervalSeconds = self::DEFAULT_POLL_INTERVAL_SECONDS,
        ?callable $onProgress = null,
        int $maxPollIntervalSeconds = self::DEFAULT_MAX_POLL_INTERVAL_SECONDS,
        ?callable $sleeper = null
    ): JobDto {
        $pollIntervalSeconds = max(1, $pollIntervalSeconds);
        $maxPollIntervalSeconds = max($pollIntervalSeconds, $maxPollIntervalSeconds);
        $currentInterval = $pollIntervalSeconds;

        $startNano = hrtime(true);
        $simulatedElapsedUs = 0;

        $lastDto = $this->getJob($jobId);

        if ($onProgress !== null) {
            $onProgress($lastDto);
        }

        if ($lastDto->isTerminal()) {
            return $lastDto;
        }

        if ($timeoutSeconds <= 0) {
            throw new JobTimeoutException(
                sprintf('Job %s did not complete within %d seconds.', $jobId, $timeoutSeconds),
                $lastDto
            );
        }

        $budgetUs = $timeoutSeconds * 1_000_000;

        while (true) {
            $elapsedUs = $sleeper !== null
                ? $simulatedElapsedUs
                : (int) ((hrtime(true) - $startNano) / 1_000);

            $remainingUs = max(0, $budgetUs - $elapsedUs);
            if ($remainingUs <= 0) {
                break;
            }

            $jitterUs = random_int(0, 500_000);
            $sleepUs = min(($currentInterval * 1_000_000) + $jitterUs, $remainingUs);

            if ($sleeper !== null) {
                $sleeper($sleepUs);
                $simulatedElapsedUs += $sleepUs;
            } else {
                usleep($sleepUs);
            }

            $lastDto = $this->getJob($jobId);

            if ($onProgress !== null) {
                $onProgress($lastDto);
            }

            if ($lastDto->isTerminal()) {
                return $lastDto;
            }

            $currentInterval = min($maxPollIntervalSeconds, $currentInterval * 2);
        }

        throw new JobTimeoutException(
            sprintf('Job %s did not complete within %d seconds.', $jobId, $timeoutSeconds),
            $lastDto
        );
    }

    /**
     * Upsert (create or replace) a document in the index asynchronously.
     *
     * @throws KinetiException
     */
    public function upsertDocument(DocumentDto $document): JobDto
    {
        $response = $this->transport->request('POST', '/api/v1/documents', [
            'json' => $document->toArray(),
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return JobDto::fromArray($data);
    }

    /**
     * Delete a document by its external identifier.
     *
     * @throws KinetiException
     */
    public function deleteDocument(string $externalId): bool
    {
        $response = $this->transport->request(
            'DELETE',
            sprintf('/api/v1/documents/%s', $this->encodePathSegment($externalId, 'externalId'))
        );

        return $response->getStatusCode() === 204;
    }

    /**
     * List documents with optional filtering, sorting, and pagination.
     *
     * @throws KinetiException
     */
    public function listDocuments(?DocumentListOptionsDto $options = null): DocumentCollectionDto
    {
        $requestOptions = [];
        if ($options !== null && !$options->isEmpty()) {
            $requestOptions['query'] = $options->toArray();
        }

        $response = $this->transport->request('GET', '/api/v1/documents', $requestOptions);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();

        return DocumentCollectionDto::fromArray($data);
    }

    /**
     * Access search client operations or execute a search query.
     *
     * When called without arguments ($query is null), returns SearchClientInterface.
     * When called with $query, executes the search query for backwards compatibility.
     *
     * @param SearchQueryDto|string|null $query
     * @return ($query is null ? SearchClientInterface : SearchResponseDto)
     *
     * @throws KinetiException
     */
    public function search(SearchQueryDto|string|null $query = null): SearchClientInterface|SearchResponseDto
    {
        $client = $this->searchClient ??= new SearchClient($this->transport, $this->configuration);

        if ($query === null) {
            return $client;
        }

        return $client->query($query);
    }

    /**
     * Access RAG client operations.
     */
    public function rag(): RagClientInterface
    {
        return $this->ragClient ??= new RagClient($this->transport, $this->configuration);
    }

    /**
     * Execute a semantic vector search and automatically paginate through all results.
     *
     * @param SearchQueryDto|string $query
     * @return \Generator<int, SearchResultItemDto>
     *
     * @throws KinetiException
     */
    public function searchAll(SearchQueryDto|string $query): \Generator
    {
        return $this->search()->all($query);
    }

    /**
     * @param resource $stream
     * @param array<string, string> $fields
     * @return array{0: string, 1: \Closure(): \Generator<int, string>}
     */
    private function createMultipartStreamPayload($stream, string $filename, array $fields, ?int $initialOffset = null): array
    {
        $boundary = bin2hex(random_bytes(16));
        $invocations = 0;

        $generator = function () use ($stream, $filename, $fields, $boundary, &$invocations, $initialOffset) {
            $invocations++;
            $meta = is_resource($stream) ? stream_get_meta_data($stream) : [];
            $isSeekable = !empty($meta['seekable']);

            if ($invocations > 1 && !$isSeekable) {
                throw new TransportException('Cannot retry request with non-seekable body stream.');
            }

            if ($isSeekable && $initialOffset !== null) {
                fseek($stream, $initialOffset);
            }

            foreach ($fields as $name => $content) {
                $safeName = $this->sanitizeMultipartHeaderValue((string) $name);
                yield "--{$boundary}\r\n";
                yield sprintf("Content-Disposition: form-data; name=\"%s\"\r\n\r\n", $safeName);
                yield (string) $content . "\r\n";
            }

            $safeFilename = $this->sanitizeMultipartHeaderValue($filename);
            yield "--{$boundary}\r\n";
            yield sprintf("Content-Disposition: form-data; name=\"file\"; filename=\"%s\"\r\n", $safeFilename);
            yield "Content-Type: application/octet-stream\r\n\r\n";

            while (!feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk !== false && $chunk !== '') {
                    yield $chunk;
                }
            }

            yield "\r\n--{$boundary}--\r\n";
        };

        return [
            'multipart/form-data; boundary=' . $boundary,
            $generator,
        ];
    }

    /**
     * Sanitize header parameter values against CRLF injection and quote breakout.
     */
    private function sanitizeMultipartHeaderValue(string $value): string
    {
        $clean = str_replace(["\r", "\n", "\0"], '', $value);

        return addcslashes($clean, "\\\"");
    }
}
