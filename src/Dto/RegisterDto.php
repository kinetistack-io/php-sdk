<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class RegisterDto
{
    /**
     * @param string $orgName Name of the organization to register.
     * @param string $email Email address of the organization admin.
     */
    public function __construct(
        public readonly string $orgName,
        public readonly string $email,
    ) {
        if (trim($this->orgName) === '') {
            throw new \InvalidArgumentException('org_name cannot be empty.');
        }

        if (trim($this->email) === '') {
            throw new \InvalidArgumentException('email cannot be empty.');
        }
    }

    /**
     * @return array{org_name: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'org_name' => $this->orgName,
            'email' => $this->email,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $orgName = (string) ($data['org_name'] ?? $data['orgName'] ?? $data['name'] ?? '');
        $email = (string) ($data['email'] ?? '');

        return new self($orgName, $email);
    }
}
