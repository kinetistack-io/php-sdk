<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class SearchQueryDto
{
    /**
     * @param list<string>|null $userRoles
     */
    public function __construct(
        public readonly string $query,
        public readonly ?int $limit = null,
        public readonly ?string $locale = null,
        public readonly ?array $userRoles = null,
        public readonly ?float $minScore = null,
        public readonly ?bool $distinctDocuments = null,
        public readonly ?bool $synthesizeAnswer = null,
        public readonly ?SearchFilterDto $filters = null,
        public readonly ?bool $stream = null,
        public readonly ?int $page = null,
        public readonly ?int $offset = null,
    ) {
        if (trim($this->query) === '') {
            throw new \InvalidArgumentException('Search query cannot be empty.');
        }
    }

    public static function create(string $query): self
    {
        return new self($query);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function cloneWith(array $overrides): self
    {
        /** @var array<string, mixed> $vars */
        $vars = get_object_vars($this);
        /** @var array<string, mixed> $merged */
        $merged = array_merge($vars, $overrides);

        return new self(...$merged);
    }

    public function withLimit(?int $limit): self
    {
        return $this->cloneWith(['limit' => $limit]);
    }

    public function withPage(?int $page): self
    {
        return $this->cloneWith(['page' => $page]);
    }

    public function withOffset(?int $offset): self
    {
        return $this->cloneWith(['offset' => $offset]);
    }

    public function setLimit(?int $limit): self
    {
        return $this->withLimit($limit);
    }

    public function setPage(?int $page): self
    {
        return $this->withPage($page);
    }

    public function setOffset(?int $offset): self
    {
        return $this->withOffset($offset);
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function withLocale(?string $locale): self
    {
        return $this->cloneWith(['locale' => $locale]);
    }

    /**
     * @param string|array<mixed>|null $userRoles
     */
    public function withUserRoles(string|array|null $userRoles): self
    {
        $rolesList = null;
        if (is_string($userRoles)) {
            $rolesList = [$userRoles];
        } elseif (is_array($userRoles)) {
            $rolesList = array_values(array_map('strval', $userRoles));
        }

        return $this->cloneWith(['userRoles' => $rolesList]);
    }

    public function withMinScore(?float $minScore): self
    {
        return $this->cloneWith(['minScore' => $minScore]);
    }

    public function withDistinctDocuments(bool $distinctDocuments = true): self
    {
        return $this->cloneWith(['distinctDocuments' => $distinctDocuments]);
    }

    public function withSynthesis(bool $synthesizeAnswer = true): self
    {
        return $this->cloneWith(['synthesizeAnswer' => $synthesizeAnswer]);
    }

    public function withSynthesizeAnswer(bool $synthesizeAnswer = true): self
    {
        return $this->withSynthesis($synthesizeAnswer);
    }

    public function withStream(bool $stream = true): self
    {
        return $this->cloneWith(['stream' => $stream]);
    }

    public function isStreaming(): bool
    {
        return $this->stream === true;
    }

    /**
     * @param SearchFilterDto|array<string, mixed>|null $filters
     */
    public function withFilters(SearchFilterDto|array|null $filters): self
    {
        $filterDto = is_array($filters) ? SearchFilterDto::fromArray($filters) : $filters;

        return $this->cloneWith(['filters' => $filterDto]);
    }

    /**
     * @param string|array<mixed>|null $permissions
     */
    public function withPermissions(string|array|null $permissions): self
    {
        $currentFilters = $this->filters ?? new SearchFilterDto();
        $newFilters = $currentFilters->withPermissions($permissions);

        return $this->cloneWith(['filters' => $newFilters]);
    }

    public function withFilter(string $key, mixed $value): self
    {
        $currentFilters = $this->filters ?? new SearchFilterDto();
        $newFilters = $currentFilters->withCustom($key, $value);

        return $this->cloneWith(['filters' => $newFilters]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'query' => $this->query,
        ];

        if ($this->limit !== null) {
            $data['limit'] = $this->limit;
        }

        if ($this->page !== null) {
            $data['page'] = $this->page;
        }

        if ($this->offset !== null) {
            $data['offset'] = $this->offset;
        }

        if ($this->locale !== null && $this->locale !== '') {
            $data['locale'] = $this->locale;
        }

        if ($this->userRoles !== null && !empty($this->userRoles)) {
            $data['user_roles'] = $this->userRoles;
        }

        if ($this->minScore !== null) {
            $data['min_score'] = $this->minScore;
        }

        if ($this->distinctDocuments !== null) {
            $data['distinct_documents'] = $this->distinctDocuments;
        }

        if ($this->synthesizeAnswer !== null) {
            $data['synthesize_answer'] = $this->synthesizeAnswer;
        }

        if ($this->stream !== null) {
            $data['stream'] = $this->stream;
        }

        if ($this->filters !== null && !$this->filters->isEmpty()) {
            $data['filters'] = $this->filters->toArray();
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $query = (string) ($data['query'] ?? '');
        $limit = isset($data['limit']) ? (int) $data['limit'] : null;
        $page = isset($data['page']) ? (int) $data['page'] : null;
        $offset = isset($data['offset']) ? (int) $data['offset'] : null;
        $locale = isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null;

        /** @var list<string>|null $userRoles */
        $userRoles = null;
        if (isset($data['user_roles']) && is_array($data['user_roles'])) {
            $userRoles = array_values(array_map('strval', $data['user_roles']));
        } elseif (isset($data['userRoles']) && is_array($data['userRoles'])) {
            $userRoles = array_values(array_map('strval', $data['userRoles']));
        }

        $minScore = ($val = $data['min_score'] ?? $data['minScore'] ?? null) !== null ? (float) $val : null;
        $distinctDocuments = ($val = $data['distinct_documents'] ?? $data['distinctDocuments'] ?? null) !== null ? (bool) $val : null;
        $synthesizeAnswer = ($val = $data['synthesize_answer'] ?? $data['synthesizeAnswer'] ?? null) !== null ? (bool) $val : null;
        $stream = ($val = $data['stream'] ?? null) !== null ? (bool) $val : null;

        $filters = null;
        if (isset($data['filters']) && is_array($data['filters'])) {
            $filters = SearchFilterDto::fromArray($data['filters']);
        }

        return new self(
            $query,
            $limit,
            $locale,
            $userRoles,
            $minScore,
            $distinctDocuments,
            $synthesizeAnswer,
            $filters,
            $stream,
            $page,
            $offset
        );
    }
}
