<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class VisionOptionsDto
{
    /**
     * @param array<string, mixed>|null $custom
     */
    public function __construct(
        public readonly string $language = 'en',
        public readonly bool $includeTags = true,
        public readonly int $maxLength = 150,
        public readonly ?array $custom = null,
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
        return new self($language, $this->includeTags, $this->maxLength, $this->custom);
    }

    public function withIncludeTags(bool $includeTags = true): self
    {
        return new self($this->language, $includeTags, $this->maxLength, $this->custom);
    }

    public function withMaxLength(int $maxLength): self
    {
        return new self($this->language, $this->includeTags, $maxLength, $this->custom);
    }

    public function withCustom(string $key, mixed $value): self
    {
        $custom = $this->custom ?? [];
        $custom[$key] = $value;

        return new self($this->language, $this->includeTags, $this->maxLength, $custom);
    }

    /**
     * @param array<string, mixed>|null $custom
     */
    public function withCustomOptions(?array $custom): self
    {
        return new self($this->language, $this->includeTags, $this->maxLength, $custom);
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
        $language = isset($data['language']) && is_string($data['language']) ? $data['language'] : 'en';
        $includeTags = isset($data['include_tags'])
            ? (bool) $data['include_tags']
            : (isset($data['includeTags']) ? (bool) $data['includeTags'] : true);
        $maxLength = isset($data['max_length'])
            ? (int) $data['max_length']
            : (isset($data['maxLength']) ? (int) $data['maxLength'] : 150);

        $custom = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['language', 'include_tags', 'includeTags', 'max_length', 'maxLength'], true)) {
                $custom[$key] = $value;
            }
        }

        return new self($language, $includeTags, $maxLength, !empty($custom) ? $custom : null);
    }
}
