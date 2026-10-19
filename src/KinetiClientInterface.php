<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Dto\ContextHintsDto;
use KinetiStack\Sdk\Dto\DocumentCollectionDto;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentListOptionsDto;
use KinetiStack\Sdk\Dto\HealthStatusDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Dto\VisionOptionsDto;
use KinetiStack\Sdk\Exception\JobTimeoutException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\PayloadTooLargeException;
use KinetiStack\Sdk\Transport\TransportInterface;

/**
 * Interface defining the core API client contract for KinetiStack services.
 *
 * Consumers should type-hint against this interface in applications and unit test suites.
 */
interface KinetiClientInterface
{
    /**
     * Return a new immutable instance configured with the specified API key.
     */
    public function withApiKey(#[\SensitiveParameter] string $apiKey): static;

    public function getTransport(): TransportInterface;

    /**
     * Get the rate limit information from the most recent request, or null if unavailable.
     */
    public function getLastRateLimitInfo(): ?RateLimitInfoDto;

    /**
     * Access module-specific client operations.
     */
    public function modules(): ModuleClientInterface;

    /**
     * Check if the API is alive.
     *
     * @throws KinetiException
     */
    public function healthz(): HealthStatusDto;

    /**
     * Check if the API and its dependencies are ready.
     *
     * @throws KinetiException
     */
    public function readyz(): HealthStatusDto;

    /**
     * @throws KinetiException
     */
    public function analyzeImage(
        string $imageUrl,
        ?ContextHintsDto $contextHints = null,
        ?VisionOptionsDto $options = null
    ): JobDto;

    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws KinetiException
     */
    public function analyzeImageBinary(
        string $filePath,
        ?ContextHintsDto $contextHints = null,
        ?VisionOptionsDto $options = null
    ): JobDto;

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
    ): JobDto;

    /**
     * @throws \RuntimeException
     * @throws \JsonException
     * @throws KinetiException
     */
    public function analyzeImageContent(
        string $binaryData,
        string $filename = 'image.jpg',
        ?ContextHintsDto $contextHints = null,
        ?VisionOptionsDto $options = null
    ): JobDto;

    /**
     * @param array<int, ImageInputDto|array<string, mixed>> $images
     * @throws PayloadTooLargeException
     * @throws KinetiException
     */
    public function submitBatchJob(array $images, ?VisionOptionsDto $options = null): JobDto;

    /**
     * @throws KinetiException
     */
    public function getJob(string $jobId): JobDto;

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
        int $timeoutSeconds = 60,
        int $pollIntervalSeconds = 2,
        ?callable $onProgress = null,
        int $maxPollIntervalSeconds = 10,
        ?callable $sleeper = null
    ): JobDto;

    /**
     * Upsert (create or replace) a document in the index asynchronously.
     *
     * @throws KinetiException
     */
    public function upsertDocument(DocumentDto $document): JobDto;

    /**
     * Delete a document by its external identifier.
     *
     * @throws \InvalidArgumentException
     * @throws KinetiException
     */
    public function deleteDocument(string $externalId): bool;

    /**
     * List documents with optional filtering, sorting, and pagination.
     *
     * @throws KinetiException
     */
    public function listDocuments(?DocumentListOptionsDto $options = null): DocumentCollectionDto;

    /**
     * Execute a semantic vector search and optional RAG synthesis query.
     *
     * @throws KinetiException
     */
    public function search(SearchQueryDto|string $query): SearchResponseDto;

    /**
     * Execute a semantic vector search and automatically paginate through all results.
     *
     * @param SearchQueryDto|string $query
     * @return \Generator<int, SearchResultItemDto>
     *
     * @throws KinetiException
     */
    public function searchAll(SearchQueryDto|string $query): \Generator;

    /**
     * Execute a semantic vector search with streaming RAG synthesis.
     *
     * @param SearchQueryDto|string $query Search query or query string
     * @param string $path Endpoint path (defaults to '/api/v1/search/rag')
     * @return \Generator<int, RagStreamChunkDto>
     *
     * @throws KinetiException
     */
    public function searchStream(SearchQueryDto|string $query, string $path = '/api/v1/search/rag'): \Generator;
}
