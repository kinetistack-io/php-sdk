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
        $rawOrgName = $data['org_name'] ?? $data['orgName'] ?? $data['name'] ?? null;
        if ($rawOrgName !== null && !is_scalar($rawOrgName) && !$rawOrgName instanceof \Stringable) {
            throw new \InvalidArgumentException('org_name must be a string.');
        }
        $orgName = (string) ($rawOrgName ?? '');

        $rawEmail = $data['email'] ?? null;
        if ($rawEmail !== null && !is_scalar($rawEmail) && !$rawEmail instanceof \Stringable) {
            throw new \InvalidArgumentException('email must be a string.');
        }
        $email = (string) ($rawEmail ?? '');

        return new self($orgName, $email);
    }
}
