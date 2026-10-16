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
            $taxonomyList = [];
            foreach ($taxonomy as $item) {
                if (!is_scalar($item) && !$item instanceof \Stringable) {
                    throw new \InvalidArgumentException('Taxonomy items must be scalar values.');
                }
                $taxonomyList[] = (string) $item;
            }
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
        $rawTitle = $data['page_title'] ?? $data['pageTitle'] ?? null;
        $pageTitle = null;
        if ($rawTitle !== null) {
            if (!is_scalar($rawTitle) && !$rawTitle instanceof \Stringable) {
                throw new \InvalidArgumentException('page_title must be a string.');
            }
            $pageTitle = (string) $rawTitle;
        }

        /** @var list<string>|null $taxonomy */
        $taxonomy = null;
        if (isset($data['taxonomy'])) {
            if (is_array($data['taxonomy'])) {
                $taxonomy = [];
                foreach ($data['taxonomy'] as $item) {
                    if (!is_scalar($item) && !$item instanceof \Stringable) {
                        throw new \InvalidArgumentException('Taxonomy items must be scalar values.');
                    }
                    $taxonomy[] = (string) $item;
                }
            } elseif (is_scalar($data['taxonomy']) || $data['taxonomy'] instanceof \Stringable) {
                $taxonomy = [(string) $data['taxonomy']];
            } else {
                throw new \InvalidArgumentException('taxonomy must be an array or scalar.');
            }
        }

        $rawSurrounding = $data['surrounding_text'] ?? $data['surroundingText'] ?? null;
        $surroundingText = null;
        if ($rawSurrounding !== null) {
            if (!is_scalar($rawSurrounding) && !$rawSurrounding instanceof \Stringable) {
                throw new \InvalidArgumentException('surrounding_text must be a string.');
            }
            $surroundingText = (string) $rawSurrounding;
        }

        $custom = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['page_title', 'pageTitle', 'taxonomy', 'surrounding_text', 'surroundingText'], true)) {
                $custom[$key] = $value;
            }
        }

        return new self($pageTitle, $taxonomy, $surroundingText, !empty($custom) ? $custom : null);
    }
}
