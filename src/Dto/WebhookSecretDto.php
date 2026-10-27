<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class WebhookSecretDto
{
    use ExtractsResponseDataTrait;

    public function __construct(
        #[\SensitiveParameter]
        public readonly string $webhookSecret,
        public readonly string $rotatedAt,
    ) {
        if (trim($this->webhookSecret) === '') {
            throw new \InvalidArgumentException('webhookSecret cannot be empty.');
        }

        if (trim($this->rotatedAt) === '') {
            throw new \InvalidArgumentException('rotatedAt cannot be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'webhook_secret' => $this->webhookSecret,
            'rotated_at' => $this->rotatedAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $webhookSecret = self::extractString($data['webhook_secret'] ?? $data['webhookSecret'] ?? null, '');
        $rotatedAt = self::extractString($data['rotated_at'] ?? $data['rotatedAt'] ?? null, '');

        return new self($webhookSecret, $rotatedAt);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $info = get_object_vars($this);
        $info['webhookSecret'] = '[REDACTED]';

        return $info;
    }
}
