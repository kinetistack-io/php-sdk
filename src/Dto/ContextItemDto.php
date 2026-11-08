<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ContextItemDto
{
    use ExtractsResponseDataTrait;

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $title = '',
        public readonly string $content = '',
        public readonly int $chunkIndex = 0,
        public readonly float $score = 0.0,
        public readonly ?array $metadata = null,
    ) {
        if (trim($this->externalId) === '') {
            throw new \InvalidArgumentException('Context item externalId cannot be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'external_id' => $this->externalId,
            'title' => $this->title,
            'content' => $this->content,
            'chunk_index' => $this->chunkIndex,
            'score' => $this->score,
        ];

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed>|null $metadata */
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null;

        return new self(
            externalId: self::extractString($data['external_id'] ?? $data['externalId'] ?? ''),
            title: self::extractString($data['title'] ?? ''),
            content: self::extractString($data['content'] ?? $data['snippet'] ?? ''),
            chunkIndex: self::extractInt($data['chunk_index'] ?? $data['chunkIndex'] ?? 0),
            score: self::extractFloat($data['score'] ?? 0.0),
            metadata: $metadata,
        );
    }

    public static function fromSearchResultItem(SearchResultItemDto $item): self
    {
        return new self(
            externalId: $item->externalId,
            title: $item->title,
            content: $item->content,
            chunkIndex: $item->chunkIndex,
            score: $item->score,
            metadata: $item->metadata,
        );
    }
}
