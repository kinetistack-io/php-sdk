<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class DocumentSummaryDto
{
    use ExtractsResponseDataTrait;

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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'external_id' => $this->externalId,
            'title' => $this->title,
            'locale' => $this->locale,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        if ($this->permissions !== null) {
            $data['permissions'] = $this->permissions;
        }

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        if ($this->chunkCount !== null) {
            $data['chunk_count'] = $this->chunkCount;
        }

        if ($this->chunksGenerated !== null) {
            $data['chunks_generated'] = $this->chunksGenerated;
        }

        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        if ($this->createdAt !== null) {
            $data['created_at'] = $this->createdAt->format(\DateTimeInterface::ATOM);
        }

        if ($this->updatedAt !== null) {
            $data['updated_at'] = $this->updatedAt->format(\DateTimeInterface::ATOM);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<string>|null $permissions */
        $permissions = isset($data['permissions']) && is_array($data['permissions'])
            ? self::extractStringList($data['permissions'])
            : null;

        /** @var array<string, mixed>|null $metadata */
        $metadata = isset($data['metadata']) && is_array($data['metadata'])
            ? $data['metadata']
            : null;

        $chunkCount = self::extractNullableInt($data['chunk_count'] ?? $data['chunkCount'] ?? null);
        $chunksGenerated = self::extractNullableInt($data['chunks_generated'] ?? $data['chunksGenerated'] ?? null, $chunkCount);

        return new self(
            self::extractNullableString($data['id'] ?? null),
            self::extractString($data['external_id'] ?? $data['externalId'] ?? ''),
            self::extractString($data['title'] ?? ''),
            self::extractString($data['locale'] ?? null, 'en'),
            $permissions,
            $metadata,
            $chunkCount,
            $chunksGenerated,
            self::extractNullableString($data['status'] ?? null),
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
