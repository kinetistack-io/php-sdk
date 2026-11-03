<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Client;

use KinetiStack\Sdk\Configuration;
use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Dto\RagSynthesisDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\TransportInterface;

/**
 * Interface defining RAG operations for KinetiStack retrieval augmented generation.
 */
interface RagClientInterface
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
     * Execute a RAG query.
     *
     * Dispatches RFC 10008 HTTP QUERY (or POST when fallback is configured) to /api/v1/rag/query.
     * If $query specifies streaming, returns a generator of RagStreamChunkDto.
     * Otherwise returns RagSynthesisDto or SearchResponseDto.
     *
     * @param SearchQueryDto|string $query
     * @return \Generator<int, RagStreamChunkDto>|RagSynthesisDto|SearchResponseDto
     *
     * @throws AuthorizationException
     * @throws KinetiException
     */
    public function query(SearchQueryDto|string $query): \Generator|RagSynthesisDto|SearchResponseDto;

    /**
     * Execute a streaming RAG query.
     *
     * @param SearchQueryDto|string $query
     * @param string $path Endpoint path (defaults to '/api/v1/rag/query')
     * @return \Generator<int, RagStreamChunkDto>
     *
     * @throws AuthorizationException
     * @throws KinetiException
     */
    public function stream(SearchQueryDto|string $query, string $path = '/api/v1/rag/query'): \Generator;
}
