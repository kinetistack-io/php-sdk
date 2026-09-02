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
        $externalId = (string) ($data['external_id'] ?? $data['externalId'] ?? '');
        $imageUrl = isset($data['image_url']) ? (string) $data['image_url'] : (isset($data['imageUrl']) ? (string) $data['imageUrl'] : null);

        $rawHints = $data['context_hints'] ?? $data['contextHints'] ?? null;
        $contextHints = null;
        if ($rawHints instanceof ContextHintsDto) {
            $contextHints = $rawHints;
        } elseif (is_array($rawHints) && !empty($rawHints)) {
            $contextHints = ContextHintsDto::fromArray($rawHints);
        }

        $imageBase64 = isset($data['image_base64']) ? (string) $data['image_base64'] : (isset($data['imageBase64']) ? (string) $data['imageBase64'] : null);

        return new self(
            externalId: $externalId,
            imageUrl: $imageUrl,
            contextHints: $contextHints,
            imageBase64: $imageBase64,
        );
    }
}
