<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class DocumentResponseDto
{
    public function __construct(
        public readonly string $documentId,
        public readonly string $externalId,
        public readonly int $chunksGenerated,
        public readonly string $status = 'indexed',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'external_id' => $this->externalId,
            'chunks_generated' => $this->chunksGenerated,
            'status' => $this->status,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['document_id'] ?? $data['documentId'] ?? ''),
            (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            (int) ($data['chunks_generated'] ?? $data['chunksGenerated'] ?? 0),
            (string) ($data['status'] ?? 'indexed'),
        );
    }
}
