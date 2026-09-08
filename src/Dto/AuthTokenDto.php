<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class AuthTokenDto
{
    public function __construct(
        public readonly string $token,
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
        $token = (string) ($data['token'] ?? '');
        $refreshToken = isset($data['refresh_token']) && is_string($data['refresh_token'])
            ? $data['refresh_token']
            : (isset($data['refreshToken']) && is_string($data['refreshToken']) ? $data['refreshToken'] : null);

        return new self($token, $refreshToken);
    }
}
