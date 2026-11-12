<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class RagSynthesisDto
{
    use ExtractsResponseDataTrait;
    use ExtractsTokenUsageTrait;

    /**
     * @param list<string> $citations List of unique external_ids referenced in the synthesized answer
     * @param list<SearchResultItemDto> $results List of hydrated search results if include_results was enabled
     * @param array<string, mixed> $metadata Arbitrary metadata associated with the synthesis
     */
    public function __construct(
        public readonly ?string $answer = null,
        public readonly array $citations = [],
        public readonly bool $successful = true,
        public readonly ?string $fallbackReason = null,
        public readonly ?int $tokensConsumed = null,
        public readonly array $results = [],
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return list<SearchResultItemDto>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    public function hasResults(): bool
    {
        return !empty($this->results);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'answer' => $this->answer,
            'citations' => $this->citations,
            'successful' => $this->successful,
        ];

        if ($this->fallbackReason !== null) {
            $data['fallback_reason'] = $this->fallbackReason;
        }

        if ($this->tokensConsumed !== null) {
            $data['tokens_consumed'] = $this->tokensConsumed;
        }

        if (!empty($this->results)) {
            $data['results'] = array_map(
                static fn (SearchResultItemDto $item): array => $item->toArray(),
                $this->results
            );
        }

        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $answer = self::extractNullableString($data['answer'] ?? null);
        $citations = self::extractStringList($data['citations'] ?? []);
        $successful = self::extractBool($data['successful'] ?? true, true);
        /** @var array<string, mixed> $metadata */
        $metadata = self::extractArray($data['metadata'] ?? []);
        $fallbackReason = self::extractNullableString(
            $data['fallback_reason'] ?? $data['fallbackReason'] ?? $metadata['fallback_reason'] ?? $metadata['fallbackReason'] ?? null
        );
        $tokensConsumed = self::extractTokensConsumed($data);

        /** @var list<SearchResultItemDto> $results */
        $results = [];
        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $item) {
                if ($item instanceof SearchResultItemDto) {
                    $results[] = $item;
                } elseif (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $results[] = SearchResultItemDto::fromArray($item);
                }
            }
        }

        return new self(
            $answer,
            $citations,
            $successful,
            $fallbackReason,
            $tokensConsumed,
            $results,
            $metadata
        );
    }
}
