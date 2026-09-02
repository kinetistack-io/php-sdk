<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Dto\BatchJobDto;
use KinetiStack\Sdk\Dto\ContextHintsDto;
use KinetiStack\Sdk\Dto\DocumentCollectionDto;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentListOptionsDto;
use KinetiStack\Sdk\Dto\DocumentResponseDto;
use KinetiStack\Sdk\Dto\HealthStatusDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Dto\VisionOptionsDto;
use KinetiStack\Sdk\Dto\VisionResponseDto;
use KinetiStack\Sdk\Exception\BatchJobTimeoutException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\PayloadTooLargeException;
use KinetiStack\Sdk\Transport\HttpTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class KinetiClient
{
    public const MAX_BATCH_PAYLOAD_BYTES = 10 * 1024 * 1024; // 10MB (10485760 bytes)

    private HttpTransport $transport;

    /**
     * @param array<string, mixed> $options Default HTTP options (e.g., timeout, headers)
     */
    public function __construct(
        string $apiHost,
        string $apiKey,
        ?HttpClientInterface $httpClient = null,
        array $options = []
    ) {
        $this->transport = new HttpTransport($apiHost, $apiKey, $httpClient, $options);
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
    ): VisionResponseDto {
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

        return VisionResponseDto::fromArray($response->toArray()['data'] ?? []);
    }

    public function analyzeImageBinary(
        string $filePath,
        ?ContextHintsDto $contextHints = null,
        ?VisionOptionsDto $options = null
    ): VisionResponseDto {
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
    ): VisionResponseDto {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Provided $stream is not a valid resource.');
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

        return VisionResponseDto::fromArray($response->toArray()['data'] ?? []);
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
    ): VisionResponseDto {
        $fields = [
            'file' => [
                'content' => $binaryData,
                'filename' => $filename,
            ],
        ];

        if ($contextHints !== null && !$contextHints->isEmpty()) {
            $fields['context_hints'] = json_encode($contextHints->toArray(), JSON_THROW_ON_ERROR);
        }

        if ($options !== null) {
            $fields['options'] = json_encode($options->toArray(), JSON_THROW_ON_ERROR);
        }

        [$contentType, $body] = $this->createMultipartPayload($fields);

        $response = $this->transport->request('POST', '/api/v1/images/analyze', [
            'headers' => [
                'Content-Type' => $contentType,
            ],
            'body' => $body,
        ]);

        return VisionResponseDto::fromArray($response->toArray()['data'] ?? []);
    }

    /**
     * @param array<int, ImageInputDto|array<string, mixed>> $images
     * @throws PayloadTooLargeException
     * @throws KinetiException
     */
    public function submitBatchJob(array $images, ?VisionOptionsDto $options = null): BatchJobDto
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

        return BatchJobDto::fromArray($response->toArray());
    }

    public function getBatchJobStatus(string $jobId): BatchJobDto
    {
        $response = $this->transport->request('GET', sprintf('/api/v1/jobs/%s', urlencode($jobId)));

        return BatchJobDto::fromArray($response->toArray());
    }

    /**
     * @throws BatchJobTimeoutException
     * @throws KinetiException
     */
    public function waitForBatchJob(string $jobId, int $timeoutSeconds = 60, int $pollIntervalSeconds = 2, ?callable $onProgress = null): BatchJobDto
    {
        $startTime = time();
        $lastDto = null;
        $pollIntervalSeconds = max(1, $pollIntervalSeconds);

        if ($timeoutSeconds <= 0) {
            $lastDto = $this->getBatchJobStatus($jobId);
        }

        while ((time() - $startTime) < $timeoutSeconds) {
            $lastDto = $this->getBatchJobStatus($jobId);

            if ($onProgress) {
                $onProgress($lastDto);
            }

            if ($lastDto->isCompleted()) {
                return $lastDto;
            }

            sleep($pollIntervalSeconds);
        }

        throw new BatchJobTimeoutException(
            sprintf('Batch job %s did not complete within %d seconds.', $jobId, $timeoutSeconds),
            $lastDto ?? $this->getBatchJobStatus($jobId)
        );
    }

    /**
     * Upsert (create or replace) a document in the index.
     *
     * @throws KinetiException
     */
    public function upsertDocument(DocumentDto $document): DocumentResponseDto
    {
        $response = $this->transport->request('POST', '/api/v1/documents', [
            'json' => $document->toArray(),
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return DocumentResponseDto::fromArray($data);
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
     * @param array<string, mixed> $fields
     * @return array{0: string, 1: string}
     */
    private function createMultipartPayload(array $fields): array
    {
        $boundary = bin2hex(random_bytes(16));
        $body = '';

        foreach ($fields as $name => $content) {
            $body .= "--{$boundary}\r\n";
            if (is_array($content) && isset($content['content'], $content['filename'])) {
                $body .= sprintf("Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n", $name, $content['filename']);
                $body .= "Content-Type: application/octet-stream\r\n\r\n";
                $body .= $content['content'] . "\r\n";
            } else {
                $body .= sprintf("Content-Disposition: form-data; name=\"%s\"\r\n\r\n", $name);
                $body .= $content . "\r\n";
            }
        }
        $body .= "--{$boundary}--\r\n";

        return [
            'multipart/form-data; boundary=' . $boundary,
            $body
        ];
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
