<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class SearchFilterDto
{
    /**
     * @param list<string>|null $permissions End-user permission/role filters asserted by the integrating client (server-to-server trust model).
     *                                       If the API key has allowed_asserted_roles configured, the backend validates
     *                                       both userRoles and filters.permissions against the whitelist. Asserting
     *                                       unauthorized roles triggers HTTP 403 AuthorizationException.
     *                                       When allowed_asserted_roles is null, the key is unrestricted.
     * @param array<string, mixed>|null $custom
     */
    public function __construct(
        public readonly ?string $locale = null,
        public readonly ?array $permissions = null,
        public readonly ?array $custom = null,
    ) {
    }

    public function withLocale(?string $locale): self
    {
        return new self($locale, $this->permissions, $this->custom);
    }

    /**
     * @param string|array<mixed>|null $permissions End-user permission/role filters asserted by the integrating client (server-to-server trust model).
     *                                              If the API key has allowed_asserted_roles configured, the backend validates
     *                                              both userRoles and filters.permissions against the whitelist. Asserting
     *                                              unauthorized roles triggers HTTP 403 AuthorizationException.
     *                                              When allowed_asserted_roles is null, the key is unrestricted.
     */
    public function withPermissions(string|array|null $permissions): self
    {
        $permissionsList = null;
        if (is_string($permissions)) {
            $permissionsList = [$permissions];
        } elseif (is_array($permissions)) {
            $permissionsList = [];
            foreach ($permissions as $p) {
                if (!is_scalar($p) && !$p instanceof \Stringable) {
                    throw new \InvalidArgumentException('Permission items must be scalar values.');
                }
                $permissionsList[] = (string) $p;
            }
        }

        return new self($this->locale, $permissionsList, $this->custom);
    }

    public function withCustom(string $key, mixed $value): self
    {
        $custom = $this->custom ?? [];
        $custom[$key] = $value;

        return new self($this->locale, $this->permissions, $custom);
    }

    /**
     * @param array<string, mixed>|null $custom
     */
    public function withCustomFilters(?array $custom): self
    {
        return new self($this->locale, $this->permissions, $custom);
    }

    public function isEmpty(): bool
    {
        return ($this->locale === null || $this->locale === '')
            && empty($this->permissions)
            && empty($this->custom);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->locale !== null && $this->locale !== '') {
            $data['locale'] = $this->locale;
        }

        if (!empty($this->permissions)) {
            $data['permissions'] = $this->permissions;
        }

        if ($this->custom !== null) {
            foreach ($this->custom as $key => $value) {
                if ($value !== null) {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawLocale = $data['locale'] ?? null;
        $locale = null;
        if ($rawLocale !== null) {
            if (!is_scalar($rawLocale) && !$rawLocale instanceof \Stringable) {
                throw new \InvalidArgumentException('locale must be a string.');
            }
            $locale = (string) $rawLocale;
        }

        /** @var list<string>|null $permissions */
        $permissions = null;
        if (isset($data['permissions'])) {
            if (!is_array($data['permissions'])) {
                throw new \InvalidArgumentException('permissions must be an array.');
            }
            $permissions = [];
            foreach ($data['permissions'] as $p) {
                if (!is_scalar($p) && !$p instanceof \Stringable) {
                    throw new \InvalidArgumentException('permissions items must be strings.');
                }
                $permissions[] = (string) $p;
            }
        }

        $custom = [];
        foreach ($data as $key => $value) {
            if ($key !== 'locale' && $key !== 'permissions') {
                $custom[$key] = $value;
            }
        }

        return new self(
            $locale,
            $permissions,
            !empty($custom) ? $custom : null
        );
    }
}
