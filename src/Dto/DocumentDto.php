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
        $rawExternalId = $data['external_id'] ?? $data['externalId'] ?? null;
        if ($rawExternalId !== null && !is_scalar($rawExternalId) && !$rawExternalId instanceof \Stringable) {
            throw new \InvalidArgumentException('external_id must be a string.');
        }
        $externalId = (string) ($rawExternalId ?? '');

        $rawTitle = $data['title'] ?? null;
        if ($rawTitle !== null && !is_scalar($rawTitle) && !$rawTitle instanceof \Stringable) {
            throw new \InvalidArgumentException('title must be a string.');
        }
        $title = (string) ($rawTitle ?? '');

        $rawContent = $data['content'] ?? null;
        if ($rawContent !== null && !is_scalar($rawContent) && !$rawContent instanceof \Stringable) {
            throw new \InvalidArgumentException('content must be a string.');
        }
        $content = (string) ($rawContent ?? '');

        $rawLocale = $data['locale'] ?? null;
        if ($rawLocale !== null && !is_scalar($rawLocale) && !$rawLocale instanceof \Stringable) {
            throw new \InvalidArgumentException('locale must be a string.');
        }
        $locale = (string) ($rawLocale ?? 'en');

        /** @var list<string>|null $permissions */
        $permissions = null;
        if (isset($data['permissions'])) {
            if (!is_array($data['permissions'])) {
                throw new \InvalidArgumentException('permissions must be an array.');
            }
            $permissions = [];
            foreach ($data['permissions'] as $p) {
                if (!is_scalar($p) && !$p instanceof \Stringable) {
                    throw new \InvalidArgumentException('permissions items must be strings.');
                }
                $permissions[] = (string) $p;
            }
        }

        /** @var array<string, mixed>|null $metadata */
        $metadata = null;
        if (isset($data['metadata'])) {
            if (!is_array($data['metadata'])) {
                throw new \InvalidArgumentException('metadata must be an array.');
            }
            $metadata = $data['metadata'];
        }

        return new self(
            $externalId,
            $title,
            $content,
            $locale,
            $permissions,
            $metadata,
        );
    }
}
