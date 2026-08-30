<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class SearchFilterDto
{
    /**
     * @param list<string>|null $permissions
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
     * @param string|array<mixed>|null $permissions
     */
    public function withPermissions(string|array|null $permissions): self
    {
        $permissionsList = null;
        if (is_string($permissions)) {
            $permissionsList = [$permissions];
        } elseif (is_array($permissions)) {
            $permissionsList = array_values(array_map('strval', $permissions));
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
        $locale = isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null;

        /** @var list<string>|null $permissions */
        $permissions = isset($data['permissions']) && is_array($data['permissions'])
            ? array_values(array_map('strval', $data['permissions']))
            : null;

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
