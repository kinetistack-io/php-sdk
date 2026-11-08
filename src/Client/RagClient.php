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
use KinetiStack\Sdk\Transport\TransportResponseInterface;

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

        if ($this->isEventStreamResponse($response)) {
            return $this->aggregateSseResponse($response);
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

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
            if (isset($data['results']) && !isset($synthesisData['results'])) {
                $synthesisData['results'] = $data['results'];
            }
            foreach (['tokens_consumed', 'tokensConsumed', 'usage'] as $tokenKey) {
                if (isset($data[$tokenKey]) && !isset($synthesisData[$tokenKey])) {
                    $synthesisData[$tokenKey] = $data[$tokenKey];
                }
            }

            return RagSynthesisDto::fromArray($synthesisData);
        }

        if (isset($data['results']) || isset($data['member']) || isset($data['hydra:member']) || isset($data['items'])) {
            return SearchResponseDto::fromArray($data);
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

    private function isEventStreamResponse(TransportResponseInterface $response): bool
    {
        $hasContentType = false;

        foreach ($response->getHeaders() as $name => $values) {
            if (strtolower($name) !== 'content-type') {
                continue;
            }

            $hasContentType = true;

            foreach ($values as $value) {
                if (str_contains(strtolower($value), 'text/event-stream')) {
                    return true;
                }
            }
        }

        if ($hasContentType) {
            return false;
        }

        $trimmed = ltrim($response->getContent());

        return $trimmed !== ''
            && (
                str_starts_with($trimmed, 'data:')
                || str_starts_with($trimmed, 'event:')
                || str_starts_with($trimmed, ':')
            );
    }

    private function aggregateSseResponse(TransportResponseInterface $response): RagSynthesisDto
    {
        $parser = new SseParser();
        $answer = '';
        $seenCitations = [];
        $citations = [];
        $tokensConsumed = null;
        $results = [];

        foreach ($parser->parse([$response->getContent()]) as $chunk) {
            if (!empty($chunk->results)) {
                $results = array_merge($results, $chunk->results);
            }
            $answer .= $chunk->text;
            foreach ($chunk->citations as $citation) {
                if (!isset($seenCitations[$citation])) {
                    $seenCitations[$citation] = true;
                    $citations[] = $citation;
                }
            }
            if ($chunk->tokensConsumed !== null) {
                $tokensConsumed = $chunk->tokensConsumed;
            }
        }

        return new RagSynthesisDto(
            answer: $answer,
            citations: $citations,
            successful: true,
            tokensConsumed: $tokensConsumed,
            results: $results,
        );
    }
}
