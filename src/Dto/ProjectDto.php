<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ProjectDto
{
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
        $id = (string) ($data['id'] ?? '');
        $name = (string) ($data['name'] ?? '');
        $domain = (string) ($data['domain'] ?? '');
        $webhookUrl = isset($data['webhook_url']) && is_string($data['webhook_url'])
            ? $data['webhook_url']
            : (isset($data['webhookUrl']) && is_string($data['webhookUrl']) ? $data['webhookUrl'] : null);
        /** @var array<string, mixed>|null $settings */
        $settings = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : null;
        $createdAt = isset($data['created_at']) && is_string($data['created_at'])
            ? $data['created_at']
            : (isset($data['createdAt']) && is_string($data['createdAt']) ? $data['createdAt'] : null);
        $monthlyQuotaCap = null;
        if (isset($data['monthly_quota_cap']) && is_numeric($data['monthly_quota_cap'])) {
            $monthlyQuotaCap = (int) $data['monthly_quota_cap'];
        } elseif (isset($data['monthlyQuotaCap']) && is_numeric($data['monthlyQuotaCap'])) {
            $monthlyQuotaCap = (int) $data['monthlyQuotaCap'];
        }

        return new self($id, $name, $domain, $webhookUrl, $settings, $createdAt, $monthlyQuotaCap);
    }
}
