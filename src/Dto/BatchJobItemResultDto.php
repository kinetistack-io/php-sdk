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
            (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            $data['alt_text'] ?? $data['altText'] ?? null,
            $data['caption'] ?? null,
            is_array($data['tags'] ?? null) ? $data['tags'] : [],
            isset($data['confidence_score']) ? (float) $data['confidence_score'] : (isset($data['confidenceScore']) ? (float) $data['confidenceScore'] : null),
            $data['error'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'external_id' => $this->externalId,
            'tags' => $this->tags,
        ];

        if ($this->altText !== null) {
            $data['alt_text'] = $this->altText;
        }
        if ($this->caption !== null) {
            $data['caption'] = $this->caption;
        }
        if ($this->confidenceScore !== null) {
            $data['confidence_score'] = $this->confidenceScore;
        }
        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        return $data;
    }
}
