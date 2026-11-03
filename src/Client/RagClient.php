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
use KinetiStack\Sdk\Transport\SseParser;
use KinetiStack\Sdk\Transport\TransportHelperTrait;
use KinetiStack\Sdk\Transport\TransportInterface;

class RagClient implements RagClientInterface
{
    use TransportHelperTrait;

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

    /**
     * @return \Generator<int, RagStreamChunkDto>|RagSynthesisDto|SearchResponseDto
     *
     * @throws AuthorizationException
     * @throws KinetiException
     */
    public function query(SearchQueryDto|string $query): \Generator|RagSynthesisDto|SearchResponseDto
    {
        if (is_string($query)) {
            $query = SearchQueryDto::create($query);
        }

        if ($query->isStreaming()) {
            return $this->stream($query);
        }

        $httpMethod = $this->configuration->useHttpQueryMethod() ? 'QUERY' : 'POST';

        $response = $this->transport->request($httpMethod, '/api/v1/rag/query', [
            'json' => $query->toArray(),
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        if (isset($data['results']) || isset($data['member']) || isset($data['hydra:member']) || isset($data['items'])) {
            return SearchResponseDto::fromArray($data);
        }

        if (
            array_key_exists('answer', $data)
            || array_key_exists('citations', $data)
            || isset($data['fallback_reason'])
            || isset($data['fallbackReason'])
            || isset($data['successful'])
        ) {
            return RagSynthesisDto::fromArray($data);
        }

        if (isset($data['synthesis']) && is_array($data['synthesis'])) {
            /** @var array<string, mixed> $synthesisData */
            $synthesisData = $data['synthesis'];
            foreach (['tokens_consumed', 'tokensConsumed', 'usage'] as $tokenKey) {
                if (isset($data[$tokenKey]) && !isset($synthesisData[$tokenKey])) {
                    $synthesisData[$tokenKey] = $data[$tokenKey];
                }
            }

            return RagSynthesisDto::fromArray($synthesisData);
        }

        return SearchResponseDto::fromArray($data);
    }

    /**
     * @return \Generator<int, RagStreamChunkDto>
     *
     * @throws AuthorizationException
     * @throws KinetiException
     */
    public function stream(SearchQueryDto|string $query, string $path = '/api/v1/rag/query'): \Generator
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
