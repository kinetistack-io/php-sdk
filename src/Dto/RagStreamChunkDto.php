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
     */
    public function __construct(
        public readonly string $text,
        public readonly bool $isDone = false,
        public readonly array $citations = [],
        public readonly array $metadata = [],
        ?string $chunk = null,
        public readonly ?int $tokensConsumed = null,
    ) {
        $this->chunk = $chunk ?? $this->text;
    }

    public static function create(string $text, bool $isDone = false, ?int $tokensConsumed = null): self
    {
        return new self($text, $isDone, tokensConsumed: $tokensConsumed);
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

        return new self(
            text: $text,
            isDone: $isDone,
            citations: $citations,
            metadata: $metadata,
            chunk: self::extractNullableString($data['chunk'] ?? null),
            tokensConsumed: $tokensConsumed,
        );
    }
}
