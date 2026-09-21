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
    ) {
        if (trim($this->query) === '') {
            throw new \InvalidArgumentException('Search query cannot be empty.');
        }
    }

    public static function create(string $query): self
    {
        return new self($query);
    }

    public function withLimit(int $limit): self
    {
        return new self(
            $this->query,
            $limit,
            $this->locale,
            $this->userRoles,
            $this->minScore,
            $this->distinctDocuments,
            $this->synthesizeAnswer,
            $this->filters,
            $this->stream
        );
    }

    public function withLocale(?string $locale): self
    {
        return new self(
            $this->query,
            $this->limit,
            $locale,
            $this->userRoles,
            $this->minScore,
            $this->distinctDocuments,
            $this->synthesizeAnswer,
            $this->filters,
            $this->stream
        );
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

        return new self(
            $this->query,
            $this->limit,
            $this->locale,
            $rolesList,
            $this->minScore,
            $this->distinctDocuments,
            $this->synthesizeAnswer,
            $this->filters,
            $this->stream
        );
    }

    public function withMinScore(?float $minScore): self
    {
        return new self(
            $this->query,
            $this->limit,
            $this->locale,
            $this->userRoles,
            $minScore,
            $this->distinctDocuments,
            $this->synthesizeAnswer,
            $this->filters,
            $this->stream
        );
    }

    public function withDistinctDocuments(bool $distinctDocuments = true): self
    {
        return new self(
            $this->query,
            $this->limit,
            $this->locale,
            $this->userRoles,
            $this->minScore,
            $distinctDocuments,
            $this->synthesizeAnswer,
            $this->filters,
            $this->stream
        );
    }

    public function withSynthesis(bool $synthesizeAnswer = true): self
    {
        return new self(
            $this->query,
            $this->limit,
            $this->locale,
            $this->userRoles,
            $this->minScore,
            $this->distinctDocuments,
            $synthesizeAnswer,
            $this->filters,
            $this->stream
        );
    }

    public function withSynthesizeAnswer(bool $synthesizeAnswer = true): self
    {
        return $this->withSynthesis($synthesizeAnswer);
    }

    public function withStream(bool $stream = true): self
    {
        return new self(
            $this->query,
            $this->limit,
            $this->locale,
            $this->userRoles,
            $this->minScore,
            $this->distinctDocuments,
            $this->synthesizeAnswer,
            $this->filters,
            $stream
        );
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

        return new self(
            $this->query,
            $this->limit,
            $this->locale,
            $this->userRoles,
            $this->minScore,
            $this->distinctDocuments,
            $this->synthesizeAnswer,
            $filterDto,
            $this->stream
        );
    }

    /**
     * @param string|array<mixed>|null $permissions
     */
    public function withPermissions(string|array|null $permissions): self
    {
        $currentFilters = $this->filters ?? new SearchFilterDto();
        $newFilters = $currentFilters->withPermissions($permissions);

        return new self(
            $this->query,
            $this->limit,
            $this->locale,
            $this->userRoles,
            $this->minScore,
            $this->distinctDocuments,
            $this->synthesizeAnswer,
            $newFilters,
            $this->stream
        );
    }

    public function withFilter(string $key, mixed $value): self
    {
        $currentFilters = $this->filters ?? new SearchFilterDto();
        $newFilters = $currentFilters->withCustom($key, $value);

        return new self(
            $this->query,
            $this->limit,
            $this->locale,
            $this->userRoles,
            $this->minScore,
            $this->distinctDocuments,
            $this->synthesizeAnswer,
            $newFilters,
            $this->stream
        );
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
            $stream
        );
    }
}
