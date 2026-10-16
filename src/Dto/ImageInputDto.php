<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ImageInputDto
{
    public readonly ?ContextHintsDto $contextHints;

    /**
     * @param ContextHintsDto|array<string, mixed>|null $contextHints
     */
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $imageUrl = null,
        ContextHintsDto|array|null $contextHints = null,
        public readonly ?string $imageBase64 = null,
    ) {
        if (trim($this->externalId) === '') {
            throw new \InvalidArgumentException('externalId cannot be empty.');
        }

        if (($this->imageUrl === null) === ($this->imageBase64 === null)) {
            throw new \InvalidArgumentException('Exactly one of imageUrl or imageBase64 must be provided.');
        }

        if ($this->imageUrl !== null && trim($this->imageUrl) === '') {
            throw new \InvalidArgumentException('imageUrl cannot be empty when provided.');
        }

        if ($this->imageBase64 !== null && trim($this->imageBase64) === '') {
            throw new \InvalidArgumentException('imageBase64 cannot be empty when provided.');
        }

        if ($contextHints instanceof ContextHintsDto) {
            $this->contextHints = $contextHints;
        } elseif (is_array($contextHints)) {
            $this->contextHints = empty($contextHints) ? null : ContextHintsDto::fromArray($contextHints);
        } else {
            $this->contextHints = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'external_id' => $this->externalId,
        ];

        if ($this->imageUrl !== null) {
            $data['image_url'] = $this->imageUrl;
        }

        if ($this->imageBase64 !== null) {
            $data['image_base64'] = $this->imageBase64;
        }

        $data['context_hints'] = $this->contextHints !== null ? $this->contextHints->toArray() : [];

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawExternalId = $data['external_id'] ?? $data['externalId'] ?? null;
        if ($rawExternalId === null || (!is_scalar($rawExternalId) && !$rawExternalId instanceof \Stringable)) {
            throw new \InvalidArgumentException('external_id must be a string.');
        }
        $externalId = (string) $rawExternalId;

        $rawImageUrl = $data['image_url'] ?? $data['imageUrl'] ?? null;
        $imageUrl = null;
        if ($rawImageUrl !== null) {
            if (!is_scalar($rawImageUrl) && !$rawImageUrl instanceof \Stringable) {
                throw new \InvalidArgumentException('image_url must be a string.');
            }
            $imageUrl = (string) $rawImageUrl;
        }

        $rawHints = $data['context_hints'] ?? $data['contextHints'] ?? null;
        $contextHints = null;
        if ($rawHints instanceof ContextHintsDto) {
            $contextHints = $rawHints;
        } elseif (is_array($rawHints) && !empty($rawHints)) {
            /** @var array<string, mixed> $rawHints */
            $contextHints = ContextHintsDto::fromArray($rawHints);
        } elseif ($rawHints !== null && !is_array($rawHints)) {
            throw new \InvalidArgumentException('context_hints must be an array or ContextHintsDto.');
        }

        $rawBase64 = $data['image_base64'] ?? $data['imageBase64'] ?? null;
        $imageBase64 = null;
        if ($rawBase64 !== null) {
            if (!is_scalar($rawBase64) && !$rawBase64 instanceof \Stringable) {
                throw new \InvalidArgumentException('image_base64 must be a string.');
            }
            $imageBase64 = (string) $rawBase64;
        }

        return new self(
            externalId: $externalId,
            imageUrl: $imageUrl,
            contextHints: $contextHints,
            imageBase64: $imageBase64,
        );
    }
}
