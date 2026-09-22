<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

final class FacetRequestDto
{
    /**
     * @param list<string> $fields
     * @param array<string, list<string>> $filters
     */
    public function __construct(
        public readonly array $fields = [],
        public readonly array $filters = [],
    ) {
    }

    /**
     * @param string|array<mixed> $values
     */
    public function withFilter(string $field, string|array $values): self
    {
        /** @var list<string> $valuesList */
        $valuesList = [];
        if (is_array($values)) {
            foreach ($values as $value) {
                if (is_scalar($value) || $value instanceof \Stringable) {
                    $valuesList[] = (string) $value;
                }
            }
        } else {
            $valuesList = [$values];
        }

        $filters = $this->filters;
        $filters[$field] = $valuesList;

        return new self($this->fields, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'fields' => $this->fields,
        ];

        if (!empty($this->filters)) {
            $data['filters'] = $this->filters;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<string> $fields */
        $fields = [];
        if (isset($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $field) {
                if (is_scalar($field) || $field instanceof \Stringable) {
                    $fields[] = (string) $field;
                }
            }
        }

        /** @var array<string, list<string>> $filters */
        $filters = [];
        if (isset($data['filters']) && is_array($data['filters'])) {
            foreach ($data['filters'] as $field => $values) {
                if (is_array($values)) {
                    $fieldValues = [];
                    foreach ($values as $value) {
                        if (is_scalar($value) || $value instanceof \Stringable) {
                            $fieldValues[] = (string) $value;
                        }
                    }
                    $filters[(string) $field] = $fieldValues;
                }
            }
        }

        return new self($fields, $filters);
    }
}
