<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

/**
 * @implements \IteratorAggregate<int, DocumentSummaryDto>
 */
class DocumentCollectionDto implements \Countable, \IteratorAggregate
{
    use ExtractsResponseDataTrait;

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
            $items = [];
            foreach ($data as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $items[] = DocumentSummaryDto::fromArray($item);
                }
            }

            return new self($items, count($items));
        }

        $rawMember = $data['member']
            ?? $data['hydra:member']
            ?? $data['items']
            ?? $data['data']
            ?? [];

        $rawItems = is_array($rawMember) ? array_values($rawMember) : [];

        $items = [];
        foreach ($rawItems as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $items[] = DocumentSummaryDto::fromArray($item);
            }
        }

        $totalRaw = $data['totalItems']
            ?? $data['hydra:totalItems']
            ?? $data['total']
            ?? count($items);

        $total = self::extractInt($totalRaw, count($items));

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
