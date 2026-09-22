<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

final class FacetBucketDto
{
    public function __construct(
        public readonly string $value = '',
        public readonly int $count = 0,
    ) {
    }

    /**
     * @return array{value: string, count: int}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'count' => $this->count,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $value = (string) ($data['value'] ?? '');
        $count = (int) ($data['count'] ?? 0);

        return new self($value, $count);
    }
}
