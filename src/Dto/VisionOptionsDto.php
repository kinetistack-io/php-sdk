<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class VisionOptionsDto
{
    use ExtractsResponseDataTrait;

    /**
     * @param array<string, mixed>|null $custom
     */
    public function __construct(
        public readonly string $language = 'en',
        public readonly bool $includeTags = true,
        public readonly int $maxLength = 150,
        public readonly ?array $custom = null,
        public readonly bool $forceReanalysis = false,
    ) {
        if (trim($this->language) === '') {
            throw new \InvalidArgumentException('Language code cannot be empty.');
        }

        if ($this->maxLength < 1) {
            throw new \InvalidArgumentException('maxLength must be at least 1.');
        }
    }

    public static function create(string $language = 'en', bool $includeTags = true, int $maxLength = 150): self
    {
        return new self($language, $includeTags, $maxLength);
    }

    public function withLanguage(string $language): self
    {
        return new self($language, $this->includeTags, $this->maxLength, $this->custom, $this->forceReanalysis);
    }

    public function withIncludeTags(bool $includeTags = true): self
    {
        return new self($this->language, $includeTags, $this->maxLength, $this->custom, $this->forceReanalysis);
    }

    public function withMaxLength(int $maxLength): self
    {
        return new self($this->language, $this->includeTags, $maxLength, $this->custom, $this->forceReanalysis);
    }

    public function withCustom(string $key, mixed $value): self
    {
        $custom = $this->custom ?? [];
        $custom[$key] = $value;

        return new self($this->language, $this->includeTags, $this->maxLength, $custom, $this->forceReanalysis);
    }

    /**
     * @param array<string, mixed>|null $custom
     */
    public function withCustomOptions(?array $custom): self
    {
        return new self($this->language, $this->includeTags, $this->maxLength, $custom, $this->forceReanalysis);
    }

    public function withForceReanalysis(bool $forceReanalysis = true): self
    {
        return new self($this->language, $this->includeTags, $this->maxLength, $this->custom, $forceReanalysis);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'language' => $this->language,
            'include_tags' => $this->includeTags,
            'max_length' => $this->maxLength,
        ];

        if ($this->forceReanalysis) {
            $data['force_reanalysis'] = true;
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
        $rawLanguage = $data['language'] ?? null;
        $language = 'en';
        if ($rawLanguage !== null) {
            if (!is_scalar($rawLanguage) && !$rawLanguage instanceof \Stringable) {
                throw new \InvalidArgumentException('language must be a string.');
            }
            $language = (string) $rawLanguage;
        }

        $rawIncludeTags = $data['include_tags'] ?? $data['includeTags'] ?? null;
        $includeTags = true;
        if ($rawIncludeTags !== null) {
            if (!is_scalar($rawIncludeTags)) {
                throw new \InvalidArgumentException('include_tags must be a boolean.');
            }
            $includeTags = self::extractBool($rawIncludeTags, true);
        }

        $rawForceReanalysis = $data['force_reanalysis'] ?? $data['forceReanalysis'] ?? null;
        $forceReanalysis = false;
        if ($rawForceReanalysis !== null) {
            if (!is_scalar($rawForceReanalysis)) {
                throw new \InvalidArgumentException('force_reanalysis must be a boolean.');
            }
            $forceReanalysis = self::extractBool($rawForceReanalysis, false);
        }

        $rawMaxLength = $data['max_length'] ?? $data['maxLength'] ?? null;
        $maxLength = 150;
        if ($rawMaxLength !== null) {
            if (!is_int($rawMaxLength) && !is_numeric($rawMaxLength)) {
                throw new \InvalidArgumentException('max_length must be an integer.');
            }
            $maxLength = (int) $rawMaxLength;
        }

        $custom = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['language', 'include_tags', 'includeTags', 'max_length', 'maxLength', 'force_reanalysis', 'forceReanalysis'], true)) {
                $custom[$key] = $value;
            }
        }

        return new self($language, $includeTags, $maxLength, !empty($custom) ? $custom : null, $forceReanalysis);
    }
}
