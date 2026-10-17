<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class AuthTokenDto
{
    use ExtractsResponseDataTrait;

    public function __construct(
        #[\SensitiveParameter]
        public readonly string $token,
        #[\SensitiveParameter]
        public readonly ?string $refreshToken = null,
    ) {
        if (trim($this->token) === '') {
            throw new \InvalidArgumentException('token cannot be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'token' => $this->token,
        ];

        if ($this->refreshToken !== null) {
            $data['refresh_token'] = $this->refreshToken;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $token = self::extractString($data['token'] ?? null, '');
        $refreshToken = self::extractNullableString($data['refresh_token'] ?? $data['refreshToken'] ?? null);

        return new self($token, $refreshToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $info = get_object_vars($this);
        $info['token'] = '[REDACTED]';
        if ($this->refreshToken !== null) {
            $info['refreshToken'] = '[REDACTED]';
        }

        return $info;
    }
}
