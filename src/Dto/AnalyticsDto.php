<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class AnalyticsDto
{
    /**
     * @param list<array<string, mixed>> $data
     */
    public function __construct(
        public readonly array $data = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
        ];
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var mixed $rawItems */
        $rawItems = $data['data'] ?? $data;
        /** @var list<array<string, mixed>> $items */
        $items = [];

        if (is_array($rawItems)) {
            foreach ($rawItems as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $items[] = $item;
                }
            }
        }

        return new self($items);
    }
}
