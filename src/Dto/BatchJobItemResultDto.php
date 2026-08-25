<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class BatchJobItemResultDto
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $altText,
        public readonly ?string $caption,
        public readonly array $tags,
        public readonly ?float $confidenceScore,
        public readonly ?string $error = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['external_id'] ?? '',
            $data['alt_text'] ?? null,
            $data['caption'] ?? null,
            $data['tags'] ?? [],
            isset($data['confidence_score']) ? (float) $data['confidence_score'] : null,
            $data['error'] ?? null,
        );
    }
}
