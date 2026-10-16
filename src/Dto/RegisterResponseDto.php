<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class RegisterResponseDto
{
    use ExtractsResponseDataTrait;

    public function __construct(
        public readonly string $message,
        public readonly string $email,
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return array{message: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'email' => $this->email,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $message = self::extractString($data['message'] ?? null, '');
        $email = self::extractString($data['email'] ?? null, '');

        return new self($message, $email);
    }
}
