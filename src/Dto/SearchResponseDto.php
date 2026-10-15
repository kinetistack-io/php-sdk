<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

/**
 * @implements \IteratorAggregate<int, SearchResultItemDto>
 */
class SearchResponseDto implements \Countable, \IteratorAggregate
{
    use ExtractsTokenUsageTrait;

    /**
     * Whether top-K pagination metadata was present in the source response payload.
     */
    public readonly bool $hasMorePresent;

    /**
     * @param list<SearchResultItemDto> $results
     * @param int $total Number of hits returned in this response page (not a global match count); maximum reachable rank is K
     * @param array<string, list<FacetBucketDto>> $facets
     * @param bool $hasMore Whether additional matching candidates exist beyond the current page within reachable rank K
     * @param int|null $nextOffset Offset to supply in the next query to retrieve the subsequent page, or null if no further results exist within rank K
     * @param bool|null $hasMorePresent Whether top-K pagination metadata was present in the source response payload
     */
    public function __construct(
        public readonly array $results = [],
        /** Number of hits returned in this response page (not a global match count); maximum reachable rank is K. */
        public readonly int $total = 0,
        public readonly ?RagSynthesisDto $synthesis = null,
        public readonly ?int $page = null,
        public readonly ?int $limit = null,
        public readonly array $facets = [],
        public readonly ?int $tokensConsumed = null,
        /** Whether additional matching candidates exist beyond the current page within reachable rank K. */
        public readonly bool $hasMore = false,
        /** Offset to supply in the next query to retrieve the subsequent page, or null if no further results exist within rank K. */
        public readonly ?int $nextOffset = null,
        ?bool $hasMorePresent = null,
    ) {
        $this->hasMorePresent = $hasMorePresent ?? ($this->hasMore || $this->nextOffset !== null);
    }

    public function hasSynthesis(): bool
    {
        return $this->synthesis !== null;
    }

    /**
     * @return array<string, list<FacetBucketDto>>
     */
    public function getFacets(): array
    {
        return $this->facets;
    }

    public function hasFacets(): bool
    {
        return !empty($this->facets);
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function getNextOffset(): ?int
    {
        return $this->nextOffset;
    }

    /**
     * Whether top-K pagination metadata was explicitly provided in the response payload.
     */
    public function isHasMorePresent(): bool
    {
        return $this->hasMorePresent;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawList = null;
        foreach (['results', 'member', 'hydra:member', 'items', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $rawList = $data[$key];
                break;
            }
        }

        /** @var list<array<string, mixed>> $rawResults */
        $rawResults = array_values($rawList ?? []);

        $results = array_map(
            static fn (array $item): SearchResultItemDto => SearchResultItemDto::fromArray($item),
            $rawResults
        );

        $total = (int) (
            $data['total']
            ?? $data['totalItems']
            ?? $data['hydra:totalItems']
            ?? count($results)
        );

        $synthesis = null;
        if (isset($data['synthesis']) && is_array($data['synthesis'])) {
            $synthesis = RagSynthesisDto::fromArray($data['synthesis']);
        }

        $page = isset($data['page']) ? (int) $data['page'] : null;
        $limitValue = $data['limit'] ?? $data['itemsPerPage'] ?? $data['per_page'] ?? null;
        $limit = $limitValue !== null ? (int) $limitValue : null;

        /** @var array<string, list<FacetBucketDto>> $facets */
        $facets = [];
        if (isset($data['facets']) && is_array($data['facets'])) {
            foreach ($data['facets'] as $field => $buckets) {
                if (is_array($buckets)) {
                    $fieldBuckets = [];
                    foreach ($buckets as $bucket) {
                        if (is_array($bucket)) {
                            $fieldBuckets[] = FacetBucketDto::fromArray($bucket);
                        }
                    }
                    $facets[(string) $field] = $fieldBuckets;
                }
            }
        }

        $tokensConsumed = self::extractTokensConsumed($data);

        $hasMorePresent = array_key_exists('has_more', $data) || array_key_exists('hasMore', $data);
        $hasMore = $hasMorePresent ? (bool) ($data['has_more'] ?? $data['hasMore']) : false;

        $nextOffset = null;
        if (isset($data['next_offset']) && is_numeric($data['next_offset'])) {
            $nextOffset = (int) $data['next_offset'];
        } elseif (isset($data['nextOffset']) && is_numeric($data['nextOffset'])) {
            $nextOffset = (int) $data['nextOffset'];
        }

        return new self($results, $total, $synthesis, $page, $limit, $facets, $tokensConsumed, $hasMore, $nextOffset, $hasMorePresent);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'results' => array_map(static fn (SearchResultItemDto $item): array => $item->toArray(), $this->results),
            'total' => $this->total,
        ];

        if ($this->hasMorePresent || $this->hasMore) {
            $data['has_more'] = $this->hasMore;
        }

        if ($this->nextOffset !== null) {
            $data['next_offset'] = $this->nextOffset;
        }

        if ($this->synthesis !== null) {
            $data['synthesis'] = $this->synthesis->toArray();
        }

        if ($this->page !== null) {
            $data['page'] = $this->page;
        }

        if ($this->limit !== null) {
            $data['limit'] = $this->limit;
        }

        if ($this->tokensConsumed !== null) {
            $data['tokens_consumed'] = $this->tokensConsumed;
        }

        if (!empty($this->facets)) {
            $facetsData = [];
            foreach ($this->facets as $field => $buckets) {
                $facetsData[$field] = array_map(
                    static fn (FacetBucketDto $bucket): array => $bucket->toArray(),
                    $buckets
                );
            }
            $data['facets'] = $facetsData;
        }

        return $data;
    }

    public function count(): int
    {
        return count($this->results);
    }

    /**
     * @return \ArrayIterator<int, SearchResultItemDto>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->results);
    }
}
