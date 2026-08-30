<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class SearchResultItemDto
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $title,
        public readonly string $content,
        public readonly int $chunkIndex = 0,
        public readonly float $score = 0.0,
        public readonly ?array $metadata = null,
    ) {
    }

    /**
     * Alias for the matching text content snippet.
     */
    public function getSnippet(): string
    {
        return $this->content;
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
        $externalId = (string) ($data['external_id'] ?? $data['externalId'] ?? '');
        $title = (string) ($data['title'] ?? '');
        $content = (string) ($data['content'] ?? $data['snippet'] ?? '');
        $chunkIndex = (int) ($data['chunk_index'] ?? $data['chunkIndex'] ?? 0);
        $score = (float) ($data['score'] ?? 0.0);

        /** @var array<string, mixed>|null $metadata */
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null;

        return new self(
            $externalId,
            $title,
            $content,
            $chunkIndex,
            $score,
            $metadata
        );
    }
}
