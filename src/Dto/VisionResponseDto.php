<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class VisionResponseDto
{
    use ExtractsResponseDataTrait;
    use ExtractsTokenUsageTrait;

    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $altText,
        public readonly ?string $caption,
        public readonly array $tags,
        public readonly ?float $confidenceScore,
        public readonly ?string $modelUsed,
        public readonly ?int $tokensConsumed = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::extractString($data['alt_text'] ?? $data['altText'] ?? ''),
            self::extractNullableString($data['caption'] ?? null),
            self::extractStringList($data['tags'] ?? []),
            self::extractNullableFloat($data['confidence_score'] ?? $data['confidenceScore'] ?? null),
            self::extractNullableString($data['model_used'] ?? $data['modelUsed'] ?? null),
            self::extractTokensConsumed($data),
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
        if ($this->tokensConsumed !== null) {
            $data['tokens_consumed'] = $this->tokensConsumed;
        }

        return $data;
    }
}
