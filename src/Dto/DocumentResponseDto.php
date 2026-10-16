<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class DocumentResponseDto
{
    use ExtractsResponseDataTrait;
    use ExtractsTokenUsageTrait;

    public function __construct(
        public readonly string $documentId,
        public readonly string $externalId,
        public readonly int $chunksGenerated,
        public readonly string $status = 'indexed',
        public readonly ?int $tokensConsumed = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'document_id' => $this->documentId,
            'external_id' => $this->externalId,
            'chunks_generated' => $this->chunksGenerated,
            'status' => $this->status,
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
        return new self(
            self::extractString($data['document_id'] ?? $data['documentId'] ?? ''),
            self::extractString($data['external_id'] ?? $data['externalId'] ?? ''),
            self::extractInt($data['chunks_generated'] ?? $data['chunksGenerated'] ?? 0),
            self::extractString($data['status'] ?? null, 'indexed'),
            self::extractTokensConsumed($data),
        );
    }
}
