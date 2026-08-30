<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class RagSynthesisDto
{
    /**
     * @param list<string> $citations List of unique external_ids referenced in the synthesized answer
     */
    public function __construct(
        public readonly ?string $answer = null,
        public readonly array $citations = [],
        public readonly bool $successful = true,
        public readonly ?string $fallbackReason = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'answer' => $this->answer,
            'citations' => $this->citations,
            'successful' => $this->successful,
        ];

        if ($this->fallbackReason !== null) {
            $data['fallback_reason'] = $this->fallbackReason;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $answer = isset($data['answer']) && is_string($data['answer']) ? $data['answer'] : null;

        /** @var list<string> $citations */
        $citations = isset($data['citations']) && is_array($data['citations'])
            ? array_values(array_map('strval', $data['citations']))
            : [];

        $successful = (bool) ($data['successful'] ?? true);

        $fallbackReason = null;
        if (isset($data['fallback_reason']) && is_string($data['fallback_reason'])) {
            $fallbackReason = $data['fallback_reason'];
        } elseif (isset($data['fallbackReason']) && is_string($data['fallbackReason'])) {
            $fallbackReason = $data['fallbackReason'];
        }

        return new self(
            $answer,
            $citations,
            $successful,
            $fallbackReason
        );
    }
}
