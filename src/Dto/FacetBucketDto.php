<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

final class FacetBucketDto
{
    use ExtractsResponseDataTrait;

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
        $value = self::extractString($data['value'] ?? null, '');
        $count = self::extractInt($data['count'] ?? null, 0);

        return new self($value, $count);
    }
}
