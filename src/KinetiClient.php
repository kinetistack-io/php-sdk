<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Dto\ContextHintsDto;
use KinetiStack\Sdk\Dto\DocumentCollectionDto;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentListOptionsDto;
use KinetiStack\Sdk\Dto\DocumentResponseDto;
use KinetiStack\Sdk\Dto\HealthStatusDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Dto\VisionOptionsDto;
use KinetiStack\Sdk\Dto\VisionResponseDto;
use KinetiStack\Sdk\Exception\JobTimeoutException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\PayloadTooLargeException;
use KinetiStack\Sdk\Transport\HttpTransport;
use KinetiStack\Sdk\Transport\SseParser;
use KinetiStack\Sdk\Transport\TransportInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class KinetiClient
{
    public const MAX_BATCH_PAYLOAD_BYTES = 10 * 1024 * 1024; // 10MB (10485760 bytes)
    public const DEFAULT_POLL_INTERVAL_SECONDS = 2;
    public const DEFAULT_MAX_POLL_INTERVAL_SECONDS = 10;
    public const DEFAULT_TIMEOUT_SECONDS = 60;

    private TransportInterface $transport;
    private ?ModuleClient $moduleClient = null;

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $httpClient
     * @param array<string, mixed> $options Default HTTP options (e.g., timeout, headers)
     */
    public function __construct(
        string $apiHost,
        string $apiKey,
        HttpClientInterface|ClientInterface|TransportInterface|null $httpClient = null,
        array $options = []
    ) {
        $this->transport = $httpClient instanceof TransportInterface
            ? $httpClient
            : new HttpTransport($apiHost, 'X-Kineti-Key', $apiKey, $httpClient, $options);
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * Get the rate limit information from the most recent request, or null if unavailable.
     */
    public function getLastRateLimitInfo(): ?RateLimitInfoDto
    {
        return $this->transport->getLastRateLimitInfo();
    }

    public function modules(): ModuleClient
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

        $meta = stream_get_meta_data($stream);
        if ($meta['seekable']) {
            rewind($stream);
        }

        $fields = [];

        if ($contextHints !== null && !$contextHints->isEmpty()) {
            $fields['context_hints'] = json_encode($contextHints->toArray(), JSON_THROW_ON_ERROR);
        }

        if ($options !== null) {
            $fields['options'] = json_encode($options->toArray(), JSON_THROW_ON_ERROR);
        }

        [$contentType, $body] = $this->createMultipartStreamPayload($stream, $filename, $fields);

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
        $response = $this->transport->request('GET', sprintf('/api/v1/jobs/%s', urlencode($jobId)));

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
        $startTime = time();
        $lastDto = null;
        $pollIntervalSeconds = max(1, $pollIntervalSeconds);
        $maxPollIntervalSeconds = max($pollIntervalSeconds, $maxPollIntervalSeconds);
        $currentInterval = $pollIntervalSeconds;

        if ($timeoutSeconds <= 0) {
            $lastDto = $this->getJob($jobId);
        }

        while ((time() - $startTime) < $timeoutSeconds) {
            $lastDto = $this->getJob($jobId);

            if ($onProgress) {
                $onProgress($lastDto);
            }

            if ($lastDto->isCompleted()) {
                return $lastDto;
            }

            $jitterUs = random_int(0, 500_000);
            $sleepUs = ($currentInterval * 1_000_000) + $jitterUs;

            if ($sleeper !== null) {
                $sleeper($sleepUs);
            } else {
                usleep($sleepUs);
            }

            $currentInterval = min($maxPollIntervalSeconds, $currentInterval * 2);
        }

        throw new JobTimeoutException(
            sprintf('Job %s did not complete within %d seconds.', $jobId, $timeoutSeconds),
            $lastDto ?? $this->getJob($jobId)
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
        if (trim($externalId) === '') {
            throw new \InvalidArgumentException('externalId cannot be empty.');
        }

        $response = $this->transport->request(
            'DELETE',
            sprintf('/api/v1/documents/%s', rawurlencode($externalId))
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
     * Execute a semantic vector search and optional RAG synthesis query.
     *
     * @throws KinetiException
     */
    public function search(SearchQueryDto|string $query): SearchResponseDto
    {
        if (is_string($query)) {
            $query = SearchQueryDto::create($query);
        }

        $response = $this->transport->request('POST', '/api/v1/search/query', [
            'json' => $query->toArray(),
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return SearchResponseDto::fromArray($data);
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
        if (is_string($query)) {
            $query = SearchQueryDto::create($query);
        }

        $page = $query->page ?? 1;
        $limit = max(1, $query->limit ?? 10);
        $currentQuery = $query->withPage($page)->withLimit($limit)->withOffset(null);

        while (true) {
            $response = $this->search($currentQuery);

            $count = 0;
            foreach ($response->results as $result) {
                yield $result;
                $count++;
            }

            if ($count < $limit) {
                break;
            }

            $page++;
            $currentQuery = $currentQuery->withPage($page);
        }
    }

    /**
     * Execute a semantic vector search with streaming RAG synthesis.
     *
     * @param SearchQueryDto|string $query Search query or query string
     * @param string $path Endpoint path (defaults to '/api/v1/search/rag')
     * @return \Generator<int, RagStreamChunkDto>
     *
     * @throws KinetiException
     */
    public function searchStream(SearchQueryDto|string $query, string $path = '/api/v1/search/rag'): \Generator
    {
        if (is_string($query)) {
            $query = SearchQueryDto::create($query)->withStream(true);
        } elseif (!$query->isStreaming()) {
            $query = $query->withStream(true);
        }

        $response = $this->transport->requestStream('POST', $path, [
            'json' => $query->toArray(),
        ]);

        $parser = new SseParser();

        yield from $parser->parse($response->getStreamIterator());
    }


    /**
     * @param resource $stream
     * @param array<string, mixed> $fields
     * @return array{0: string, 1: \Generator<int, string>}
     */
    private function createMultipartStreamPayload($stream, string $filename, array $fields): array
    {
        $boundary = bin2hex(random_bytes(16));

        $generator = function () use ($stream, $filename, $fields, $boundary) {
            foreach ($fields as $name => $content) {
                yield "--{$boundary}\r\n";
                yield sprintf("Content-Disposition: form-data; name=\"%s\"\r\n\r\n", $name);
                yield $content . "\r\n";
            }

            yield "--{$boundary}\r\n";
            yield sprintf("Content-Disposition: form-data; name=\"file\"; filename=\"%s\"\r\n", $filename);
            yield "Content-Type: application/octet-stream\r\n\r\n";

            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk !== false && $chunk !== '') {
                    yield $chunk;
                }
            }

            yield "\r\n--{$boundary}--\r\n";
        };

        return [
            'multipart/form-data; boundary=' . $boundary,
            $generator()
        ];
    }
}
