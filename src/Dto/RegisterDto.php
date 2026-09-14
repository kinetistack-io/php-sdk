<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class RegisterDto
{
    /**
     * @param string $orgName Name of the organization to register.
     * @param string $email Email address of the organization admin.
     * @param string $password Password for the organization admin.
     *                         @sensitive $password
     */
    public function __construct(
        public readonly string $orgName,
        public readonly string $email,
        public readonly string $password,
    ) {
        if (trim($this->orgName) === '') {
            throw new \InvalidArgumentException('org_name cannot be empty.');
        }

        if (trim($this->email) === '') {
            throw new \InvalidArgumentException('email cannot be empty.');
        }

        if ($this->password === '') {
            throw new \InvalidArgumentException('password cannot be empty.');
        }
    }

    /**
     * @return array{org_name: string, email: string, password: string}
     */
    public function toArray(): array
    {
        return [
            'org_name' => $this->orgName,
            'email' => $this->email,
            'password' => $this->password,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $orgName = (string) ($data['org_name'] ?? $data['orgName'] ?? $data['name'] ?? '');
        $email = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        return new self($orgName, $email, $password);
    }
}
