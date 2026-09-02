<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class ContextHintsDto
{
    /**
     * @param list<string>|null $taxonomy
     * @param array<string, mixed>|null $custom
     */
    public function __construct(
        public readonly ?string $pageTitle = null,
        public readonly ?array $taxonomy = null,
        public readonly ?string $surroundingText = null,
        public readonly ?array $custom = null,
    ) {
    }

    public static function create(?string $pageTitle = null): self
    {
        return new self($pageTitle);
    }

    public function withPageTitle(?string $pageTitle): self
    {
        return new self($pageTitle, $this->taxonomy, $this->surroundingText, $this->custom);
    }

    /**
     * @param string|array<mixed>|null $taxonomy
     */
    public function withTaxonomy(string|array|null $taxonomy): self
    {
        $taxonomyList = null;
        if (is_string($taxonomy)) {
            $taxonomyList = [$taxonomy];
        } elseif (is_array($taxonomy)) {
            $taxonomyList = array_values(array_map('strval', $taxonomy));
        }

        return new self($this->pageTitle, $taxonomyList, $this->surroundingText, $this->custom);
    }

    public function withSurroundingText(?string $surroundingText): self
    {
        return new self($this->pageTitle, $this->taxonomy, $surroundingText, $this->custom);
    }

    public function withCustom(string $key, mixed $value): self
    {
        $custom = $this->custom ?? [];
        $custom[$key] = $value;

        return new self($this->pageTitle, $this->taxonomy, $this->surroundingText, $custom);
    }

    /**
     * @param array<string, mixed>|null $custom
     */
    public function withCustomHints(?array $custom): self
    {
        return new self($this->pageTitle, $this->taxonomy, $this->surroundingText, $custom);
    }

    public function isEmpty(): bool
    {
        return ($this->pageTitle === null || trim($this->pageTitle) === '')
            && empty($this->taxonomy)
            && ($this->surroundingText === null || trim($this->surroundingText) === '')
            && empty($this->custom);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->pageTitle !== null && trim($this->pageTitle) !== '') {
            $data['page_title'] = $this->pageTitle;
        }

        if (!empty($this->taxonomy)) {
            $data['taxonomy'] = $this->taxonomy;
        }

        if ($this->surroundingText !== null && trim($this->surroundingText) !== '') {
            $data['surrounding_text'] = $this->surroundingText;
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
        $pageTitle = isset($data['page_title']) && is_string($data['page_title'])
            ? $data['page_title']
            : (isset($data['pageTitle']) && is_string($data['pageTitle']) ? $data['pageTitle'] : null);

        /** @var list<string>|null $taxonomy */
        $taxonomy = null;
        if (isset($data['taxonomy'])) {
            if (is_array($data['taxonomy'])) {
                $taxonomy = array_values(array_map('strval', $data['taxonomy']));
            } elseif (is_string($data['taxonomy'])) {
                $taxonomy = [$data['taxonomy']];
            }
        }

        $surroundingText = isset($data['surrounding_text']) && is_string($data['surrounding_text'])
            ? $data['surrounding_text']
            : (isset($data['surroundingText']) && is_string($data['surroundingText']) ? $data['surroundingText'] : null);

        $custom = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['page_title', 'pageTitle', 'taxonomy', 'surrounding_text', 'surroundingText'], true)) {
                $custom[$key] = $value;
            }
        }

        return new self($pageTitle, $taxonomy, $surroundingText, !empty($custom) ? $custom : null);
    }
}
