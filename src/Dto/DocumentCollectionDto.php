<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

/**
 * @implements \IteratorAggregate<int, DocumentSummaryDto>
 */
class DocumentCollectionDto implements \Countable, \IteratorAggregate
{
    /**
     * @param list<DocumentSummaryDto> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(static fn (DocumentSummaryDto $item): array => $item->toArray(), $this->items),
            'total' => $this->total,
        ];
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $data
     */
    public static function fromArray(array $data): self
    {
        if (array_is_list($data)) {
            /** @var list<array<string, mixed>> $data */
            $items = array_map(
                static fn (array $item): DocumentSummaryDto => DocumentSummaryDto::fromArray($item),
                $data
            );

            return new self($items, count($items));
        }

        /** @var list<array<string, mixed>> $rawItems */
        $rawItems = array_values($data['member']
            ?? $data['hydra:member']
            ?? $data['items']
            ?? $data['data']
            ?? []);

        $items = array_map(
            static fn (array $item): DocumentSummaryDto => DocumentSummaryDto::fromArray($item),
            $rawItems
        );

        $total = (int) (
            $data['totalItems']
            ?? $data['hydra:totalItems']
            ?? $data['total']
            ?? count($items)
        );

        return new self($items, $total);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return \ArrayIterator<int, DocumentSummaryDto>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
}
