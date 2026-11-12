<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class RagStreamChunkDto implements \Stringable
{
    use ExtractsResponseDataTrait;
    use ExtractsTokenUsageTrait;

    public readonly string $chunk;

    /**
     * @param list<string> $citations
     * @param array<string, mixed> $metadata
     * @param list<SearchResultItemDto> $results
     */
    public function __construct(
        public readonly string $text,
        public readonly bool $isDone = false,
        public readonly array $citations = [],
        public readonly array $metadata = [],
        ?string $chunk = null,
        public readonly ?int $tokensConsumed = null,
        public readonly array $results = [],
        public readonly ?string $fallbackReason = null,
    ) {
        $this->chunk = $chunk ?? $this->text;
    }

    public static function create(
        string $text,
        bool $isDone = false,
        ?int $tokensConsumed = null,
        ?string $fallbackReason = null
    ): self {
        return new self($text, $isDone, tokensConsumed: $tokensConsumed, fallbackReason: $fallbackReason);
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getChunk(): string
    {
        return $this->chunk;
    }

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function getFallbackReason(): ?string
    {
        return $this->fallbackReason;
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

    public function __toString(): string
    {
        return $this->text;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'text' => $this->text,
            'chunk' => $this->chunk,
            'is_done' => $this->isDone,
            'citations' => $this->citations,
            'metadata' => $this->metadata,
        ];

        if ($this->tokensConsumed !== null) {
            $data['tokens_consumed'] = $this->tokensConsumed;
        }

        if ($this->fallbackReason !== null) {
            $data['fallback_reason'] = $this->fallbackReason;
        }

        if (!empty($this->results)) {
            $data['results'] = array_map(
                static fn (SearchResultItemDto $item): array => $item->toArray(),
                $this->results
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $text = self::extractString($data['chunk'] ?? $data['text'] ?? '');
        $isDone = self::extractBool($data['is_done'] ?? $data['done'] ?? false);
        $citations = self::extractStringList($data['citations'] ?? []);
        /** @var array<string, mixed> $metadata */
        $metadata = self::extractArray($data['metadata'] ?? []);

        $tokensConsumed = self::extractTokensConsumed($data);
        if ($tokensConsumed === null && !empty($metadata)) {
            $tokensConsumed = self::extractTokensConsumed($metadata);
        }

        $fallbackReason = self::extractNullableString(
            $data['fallback_reason'] ?? $data['fallbackReason'] ?? $metadata['fallback_reason'] ?? $metadata['fallbackReason'] ?? null
        );

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
            text: $text,
            isDone: $isDone,
            citations: $citations,
            metadata: $metadata,
            chunk: self::extractNullableString($data['chunk'] ?? null),
            tokensConsumed: $tokensConsumed,
            results: $results,
            fallbackReason: $fallbackReason,
        );
    }
}
