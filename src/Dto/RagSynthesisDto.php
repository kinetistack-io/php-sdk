<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class RagSynthesisDto
{
    use ExtractsResponseDataTrait;
    use ExtractsTokenUsageTrait;

    /**
     * @param list<string> $citations List of unique external_ids referenced in the synthesized answer
     */
    public function __construct(
        public readonly ?string $answer = null,
        public readonly array $citations = [],
        public readonly bool $successful = true,
        public readonly ?string $fallbackReason = null,
        public readonly ?int $tokensConsumed = null,
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
        $answer = self::extractNullableString($data['answer'] ?? null);
        $citations = self::extractStringList($data['citations'] ?? []);
        $successful = self::extractBool($data['successful'] ?? true, true);
        $fallbackReason = self::extractNullableString($data['fallback_reason'] ?? $data['fallbackReason'] ?? null);
        $tokensConsumed = self::extractTokensConsumed($data);

        return new self(
            $answer,
            $citations,
            $successful,
            $fallbackReason,
            $tokensConsumed
        );
    }
}
