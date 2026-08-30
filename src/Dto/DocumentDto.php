<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class DocumentDto
{
    /**
     * @param list<string>|null $permissions
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $title,
        public readonly string $content,
        public readonly string $locale = 'en',
        public readonly ?array $permissions = null,
        public readonly ?array $metadata = null,
    ) {
        if (trim($this->externalId) === '') {
            throw new \InvalidArgumentException('externalId cannot be empty.');
        }
        if (trim($this->title) === '') {
            throw new \InvalidArgumentException('title cannot be empty.');
        }
        if (trim($this->content) === '') {
            throw new \InvalidArgumentException('content cannot be empty.');
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
            'locale' => $this->locale,
        ];

        if ($this->permissions !== null) {
            $data['permissions'] = $this->permissions;
        }

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
        /** @var list<string>|null $permissions */
        $permissions = isset($data['permissions']) && is_array($data['permissions'])
            ? array_values(array_map('strval', $data['permissions']))
            : null;

        /** @var array<string, mixed>|null $metadata */
        $metadata = isset($data['metadata']) && is_array($data['metadata'])
            ? $data['metadata']
            : null;

        return new self(
            (string) ($data['external_id'] ?? $data['externalId'] ?? ''),
            (string) ($data['title'] ?? ''),
            (string) ($data['content'] ?? ''),
            (string) ($data['locale'] ?? 'en'),
            $permissions,
            $metadata,
        );
    }
}
