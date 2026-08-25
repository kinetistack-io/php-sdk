<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class VisionResponseDto
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $altText,
        public readonly ?string $caption,
        public readonly array $tags,
        public readonly ?float $confidenceScore,
        public readonly ?string $modelUsed,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['alt_text'] ?? '',
            $data['caption'] ?? null,
            $data['tags'] ?? [],
            isset($data['confidence_score']) ? (float) $data['confidence_score'] : null,
            $data['model_used'] ?? null,
        );
    }
}
