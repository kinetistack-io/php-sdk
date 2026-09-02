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
        $rawTags = $data['tags'] ?? [];
        $tags = is_array($rawTags) ? array_values(array_map('strval', $rawTags)) : [];

        $confidence = null;
        if (isset($data['confidence_score'])) {
            $confidence = (float) $data['confidence_score'];
        } elseif (isset($data['confidenceScore'])) {
            $confidence = (float) $data['confidenceScore'];
        }

        $caption = null;
        if (isset($data['caption'])) {
            $caption = (string) $data['caption'];
        }

        $modelUsed = null;
        if (isset($data['model_used'])) {
            $modelUsed = (string) $data['model_used'];
        } elseif (isset($data['modelUsed'])) {
            $modelUsed = (string) $data['modelUsed'];
        }

        return new self(
            (string) ($data['alt_text'] ?? $data['altText'] ?? ''),
            $caption,
            $tags,
            $confidence,
            $modelUsed,
        );
    }

    public function hasTags(): bool
    {
        return $this->tags !== [];
    }

    public function getTagsAsString(string $separator = ', '): string
    {
        return implode($separator, $this->tags);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'alt_text' => $this->altText,
            'tags' => $this->tags,
        ];

        if ($this->caption !== null) {
            $data['caption'] = $this->caption;
        }
        if ($this->confidenceScore !== null) {
            $data['confidence_score'] = $this->confidenceScore;
        }
        if ($this->modelUsed !== null) {
            $data['model_used'] = $this->modelUsed;
        }

        return $data;
    }
}
