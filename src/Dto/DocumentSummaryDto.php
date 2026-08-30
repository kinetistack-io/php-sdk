<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class DocumentSummaryDto
{
    /**
     * @param list<string>|null $permissions
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public readonly ?string $id,
        public readonly string $externalId,
        public readonly string $title,
        public readonly string $locale = 'en',
        public readonly ?array $permissions = null,
        public readonly ?array $metadata = null,
        public readonly ?int $chunkCount = null,
        public readonly ?int $chunksGenerated = null,
        public readonly ?string $status = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<string>|null $permissions */
        $permissions = isset($data['permissions']) && is_array($data['permissions'])
            ? array_values(array_map('strval', $data['permissions']))
            : null;

        /** @var array<string, mixed>|null $metadata */
        $metadata = isset($data['metadata']) && is_array($data['metadata'])
            ? $data['metadata']
            : null;

        $chunkCount = isset($data['chunk_count'])
            ? (int) $data['chunk_count']
            : (isset($data['chunkCount']) ? (int) $data['chunkCount'] : null);

        $chunksGenerated = isset($data['chunks_generated'])
            ? (int) $data['chunks_generated']
            : (isset($data['chunksGenerated']) ? (int) $data['chunksGenerated'] : $chunkCount);

        return new self(
            isset($data['id']) ? (string) $data['id'] : null,
            (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            (string) ($data['title'] ?? ''),
            (string) ($data['locale'] ?? 'en'),
            $permissions,
            $metadata,
            $chunkCount,
            $chunksGenerated,
            isset($data['status']) ? (string) $data['status'] : null,
            self::parseDateTime($data['created_at'] ?? $data['createdAt'] ?? null),
            self::parseDateTime($data['updated_at'] ?? $data['updatedAt'] ?? null),
        );
    }

    private static function parseDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
