<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class OrganizationDto
{
    use ExtractsResponseDataTrait;

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
        return new self(
            self::extractString($data['id'] ?? ''),
            self::extractString($data['name'] ?? ''),
            self::extractString($data['billing_tier'] ?? $data['billingTier'] ?? null, 'free'),
            self::extractNullableString($data['created_at'] ?? $data['createdAt'] ?? null),
            self::extractNullableInt($data['monthly_quota_cap'] ?? $data['monthlyQuotaCap'] ?? null)
        );
    }
}
