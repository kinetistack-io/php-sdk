<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

/**
 * @implements \IteratorAggregate<int, SearchResultItemDto>
 */
class SearchResponseDto implements \Countable, \IteratorAggregate
{
    /**
     * @param list<SearchResultItemDto> $results
     */
    public function __construct(
        public readonly array $results = [],
        public readonly int $total = 0,
        public readonly ?RagSynthesisDto $synthesis = null,
    ) {
    }

    public function hasSynthesis(): bool
    {
        return $this->synthesis !== null;
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

        return new self($results, $total, $synthesis);
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

        if ($this->synthesis !== null) {
            $data['synthesis'] = $this->synthesis->toArray();
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
