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
use KinetiStack\Sdk\Transport\SseParser;
use KinetiStack\Sdk\Transport\TransportHelperTrait;
use KinetiStack\Sdk\Transport\TransportInterface;

class SearchClient implements SearchClientInterface
{
    use TransportHelperTrait;

    public const MAX_SEARCH_OFFSET = 1000;

    private readonly Configuration $configuration;

    /**
     * @param Configuration|array<string, mixed> $configuration SDK configuration options
     */
    public function __construct(
        private readonly TransportInterface $transport,
        Configuration|array $configuration = [],
    ) {
        $this->configuration = Configuration::fromOptions($configuration);
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function query(SearchQueryDto|string $query): SearchResponseDto
    {
        if (is_string($query)) {
            $query = SearchQueryDto::create($query);
        }

        $httpMethod = $this->configuration->useHttpQueryMethod() ? 'QUERY' : 'POST';

        $response = $this->transport->request($httpMethod, '/api/v1/search/query', [
            'json' => $query->toArray(),
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return SearchResponseDto::fromArray($data);
    }

    public function search(SearchQueryDto|string $query): SearchResponseDto
    {
        return $this->query($query);
    }

    /**
     * @return \Generator<int, SearchResultItemDto>
     *
     * @throws KinetiException
     */
    public function all(SearchQueryDto|string $query): \Generator
    {
        if (is_string($query)) {
            $query = SearchQueryDto::create($query);
        }

        $limit = max(1, $query->limit ?? 10);
        $offset = max(0, $query->offset ?? 0);

        if ($offset > self::MAX_SEARCH_OFFSET) {
            return;
        }

        $baseQuery = $query->withLimit($limit);

        while (true) {
            $response = $this->query($baseQuery->withOffset($offset));

            $count = count($response->results);
            foreach ($response->results as $result) {
                yield $result;
            }

            if ($count === 0) {
                break;
            }

            if ($response->hasMorePresent && !$response->hasMore) {
                break;
            }

            if ($count < $limit) {
                break;
            }

            $nextOffset = $response->nextOffset ?? ($offset + $limit);
            if ($nextOffset <= $offset || $nextOffset > self::MAX_SEARCH_OFFSET) {
                break;
            }

            $offset = $nextOffset;
        }
    }

    /**
     * @return \Generator<int, RagStreamChunkDto>
     *
     * @throws AuthorizationException
     * @throws KinetiException
     */
    public function stream(SearchQueryDto|string $query, string $path = '/api/v1/search/rag'): \Generator
    {
        $this->validatePath($path);

        return $this->doStream($query, $path);
    }

    private function doStream(SearchQueryDto|string $query, string $path): \Generator
    {
        if (is_string($query)) {
            $query = SearchQueryDto::create($query)->withStream(true);
        } elseif (!$query->isStreaming()) {
            $query = $query->withStream(true);
        }

        $httpMethod = $this->configuration->useHttpQueryMethod() ? 'QUERY' : 'POST';

        $response = $this->transport->requestStream($httpMethod, $path, [
            'json' => $query->toArray(),
        ]);

        $parser = new SseParser();

        yield from $parser->parse($response->getStreamIterator());
    }
}
