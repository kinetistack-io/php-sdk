<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class OrganizationDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $billingTier = 'free',
        public readonly ?string $createdAt = null,
    ) {
        if (trim($this->id) === '') {
            throw new \InvalidArgumentException('id cannot be empty.');
        }
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('name cannot be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'billing_tier' => $this->billingTier,
        ];

        if ($this->createdAt !== null) {
            $data['created_at'] = $this->createdAt;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? '');
        $name = (string) ($data['name'] ?? '');
        $billingTier = (string) ($data['billing_tier'] ?? $data['billingTier'] ?? 'free');
        $createdAt = isset($data['created_at']) && is_string($data['created_at'])
            ? $data['created_at']
            : (isset($data['createdAt']) && is_string($data['createdAt']) ? $data['createdAt'] : null);

        return new self($id, $name, $billingTier, $createdAt);
    }
}
