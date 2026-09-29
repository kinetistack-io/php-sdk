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
        public readonly ?int $monthlyQuotaCap = null,
    ) {
        if (trim($this->id) === '') {
            throw new \InvalidArgumentException('id cannot be empty.');
        }
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('name cannot be empty.');
        }
        if ($this->monthlyQuotaCap !== null && $this->monthlyQuotaCap < 0) {
            throw new \InvalidArgumentException('monthlyQuotaCap must be greater than or equal to 0.');
        }
    }

    public function getMonthlyQuotaCap(): ?int
    {
        return $this->monthlyQuotaCap;
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

        if ($this->monthlyQuotaCap !== null) {
            $data['monthly_quota_cap'] = $this->monthlyQuotaCap;
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
        $monthlyQuotaCap = null;
        if (isset($data['monthly_quota_cap']) && is_numeric($data['monthly_quota_cap'])) {
            $monthlyQuotaCap = (int) $data['monthly_quota_cap'];
        } elseif (isset($data['monthlyQuotaCap']) && is_numeric($data['monthlyQuotaCap'])) {
            $monthlyQuotaCap = (int) $data['monthlyQuotaCap'];
        }

        return new self($id, $name, $billingTier, $createdAt, $monthlyQuotaCap);
    }
}
