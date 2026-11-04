<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Client;

use KinetiStack\Sdk\Configuration;
use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\TransportInterface;

/**
 * Interface defining search operations for KinetiStack vector search and RAG synthesis.
 */
interface SearchClientInterface
{
    /**
     * Get the SDK configuration.
     */
    public function getConfiguration(): Configuration;

    /**
     * Get the underlying transport.
     */
    public function getTransport(): TransportInterface;

    /**
     * Execute a semantic vector search query.
     *
     * Dispatches RFC 10008 HTTP QUERY (or POST when fallback is configured) to /api/v1/search/query.
     *
     * @param SearchQueryDto|string $query Search query DTO or raw query string
     *
     * @throws KinetiException
     */
    public function query(SearchQueryDto|string $query): SearchResponseDto;

    /**
     * Alias for query().
     *
     * @param SearchQueryDto|string $query Search query DTO or raw query string
     *
     * @throws KinetiException
     */
    public function search(SearchQueryDto|string $query): SearchResponseDto;

    /**
     * Execute a semantic vector search and automatically paginate through all results.
     *
     * @param SearchQueryDto|string $query Search query DTO or raw query string
     * @return \Generator<int, SearchResultItemDto>
     *
     * @throws KinetiException
     */
    public function all(SearchQueryDto|string $query): \Generator;

    /**
     * Execute a semantic vector search with streaming RAG synthesis.
     *
     * @param SearchQueryDto|string $query Search query DTO or raw query string
     * @param string $path Endpoint path (defaults to '/api/v1/rag/query')
     * @return \Generator<int, RagStreamChunkDto>
     *
     * @throws AuthorizationException When the API key lacks the required scope (rag or all)
     * @throws KinetiException
     */
    public function stream(SearchQueryDto|string $query, string $path = '/api/v1/rag/query'): \Generator;
}
