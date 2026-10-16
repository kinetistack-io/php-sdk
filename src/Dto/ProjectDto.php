<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ProjectDto
{
    use ExtractsResponseDataTrait;

    /**
     * @param array<string, mixed>|null $settings
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $domain,
        public readonly ?string $webhookUrl = null,
        public readonly ?array $settings = null,
        public readonly ?string $createdAt = null,
        public readonly ?int $monthlyQuotaCap = null,
    ) {
        if (trim($this->id) === '') {
            throw new \InvalidArgumentException('id cannot be empty.');
        }
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('name cannot be empty.');
        }
        if (trim($this->domain) === '') {
            throw new \InvalidArgumentException('domain cannot be empty.');
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
            'domain' => $this->domain,
        ];

        if ($this->webhookUrl !== null) {
            $data['webhook_url'] = $this->webhookUrl;
        }

        if ($this->settings !== null) {
            $data['settings'] = $this->settings;
        }

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
        $id = self::extractString($data['id'] ?? '');
        $name = self::extractString($data['name'] ?? '');
        $domain = self::extractString($data['domain'] ?? '');
        $webhookUrl = self::extractNullableString($data['webhook_url'] ?? $data['webhookUrl'] ?? null);
        /** @var array<string, mixed>|null $settings */
        $settings = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : null;
        $createdAt = self::extractNullableString($data['created_at'] ?? $data['createdAt'] ?? null);
        $monthlyQuotaCap = self::extractNullableInt($data['monthly_quota_cap'] ?? $data['monthlyQuotaCap'] ?? null);

        return new self($id, $name, $domain, $webhookUrl, $settings, $createdAt, $monthlyQuotaCap);
    }
}
