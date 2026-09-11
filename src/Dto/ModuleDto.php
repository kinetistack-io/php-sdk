<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ModuleDto
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $label,
        public readonly string $status,
        public readonly bool $accessible,
    ) {
        if (trim($this->identifier) === '') {
            throw new \InvalidArgumentException('identifier cannot be empty.');
        }
        if (trim($this->label) === '') {
            throw new \InvalidArgumentException('label cannot be empty.');
        }
    }

    /**
     * Check if the module is accessible to the authenticated organization.
     */
    public function isAccessible(): bool
    {
        return $this->accessible;
    }

    /**
     * Check if the module is enabled and accessible for the authenticated organization.
     *
     * Alias for isAccessible(). To check the global deployment status regardless of
     * organization permissions, use isGloballyEnabled().
     */
    public function isEnabled(): bool
    {
        return $this->accessible;
    }

    /**
     * Check if the module's global lifecycle status is "enabled".
     */
    public function isGloballyEnabled(): bool
    {
        return strtolower($this->status) === 'enabled';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'label' => $this->label,
            'status' => $this->status,
            'accessible' => $this->accessible,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['identifier'] ?? ''),
            (string) ($data['label'] ?? ''),
            (string) ($data['status'] ?? ''),
            (bool) ($data['accessible'] ?? false),
        );
    }
}
