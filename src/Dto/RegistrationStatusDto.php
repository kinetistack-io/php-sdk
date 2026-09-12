<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

use KinetiStack\Sdk\Enum\RegistrationMode;

class RegistrationStatusDto
{
    public function __construct(
        public readonly RegistrationMode $mode,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawMode = $data['mode'] ?? null;
        $mode = null;
        if ($rawMode instanceof RegistrationMode) {
            $mode = $rawMode;
        } elseif (is_string($rawMode)) {
            $mode = RegistrationMode::tryFrom($rawMode);
        }

        return new self($mode ?? RegistrationMode::Closed);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
        ];
    }

    public function isOpen(): bool
    {
        return $this->mode === RegistrationMode::Open;
    }

    public function isWhitelist(): bool
    {
        return $this->mode === RegistrationMode::Whitelist;
    }

    public function isClosed(): bool
    {
        return $this->mode === RegistrationMode::Closed;
    }
}
