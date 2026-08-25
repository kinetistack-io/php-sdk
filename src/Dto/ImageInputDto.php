<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ImageInputDto
{
    /**
     * @param array<string, mixed> $contextHints
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $imageUrl,
        public readonly array $contextHints = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'image_url' => $this->imageUrl,
            'context_hints' => $this->contextHints,
        ];
    }
}
