<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class SearchQueryDto
{
    use ExtractsResponseDataTrait;

    /**
     * @param list<string>|null $userRoles End-user roles asserted by the integrating client (server-to-server trust model).
     *                                     If the API key has allowed_asserted_roles configured, the backend validates
     *                                     both userRoles and filters.permissions against the whitelist. Asserting
     *                                     unauthorized roles triggers HTTP 403 AuthorizationException.
     *                                     When allowed_asserted_roles is null, the key is unrestricted.
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
        public readonly ?int $offset = null,
        public readonly ?FacetRequestDto $facets = null,
        public readonly ?bool $rerank = null,
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
        return new self(
            array_key_exists('query', $overrides) ? (is_string($overrides['query']) ? $overrides['query'] : $this->query) : $this->query,
            array_key_exists('limit', $overrides) ? (is_int($overrides['limit']) ? $overrides['limit'] : null) : $this->limit,
            array_key_exists('locale', $overrides) ? (is_string($overrides['locale']) ? $overrides['locale'] : null) : $this->locale,
            array_key_exists('userRoles', $overrides) ? (is_array($overrides['userRoles']) ? array_values(array_filter($overrides['userRoles'], 'is_string')) : null) : $this->userRoles,
            array_key_exists('minScore', $overrides) ? (is_float($overrides['minScore']) || is_int($overrides['minScore']) ? (float) $overrides['minScore'] : null) : $this->minScore,
            array_key_exists('distinctDocuments', $overrides) ? (is_bool($overrides['distinctDocuments']) ? $overrides['distinctDocuments'] : null) : $this->distinctDocuments,
            array_key_exists('synthesizeAnswer', $overrides) ? (is_bool($overrides['synthesizeAnswer']) ? $overrides['synthesizeAnswer'] : null) : $this->synthesizeAnswer,
            array_key_exists('filters', $overrides) ? ($overrides['filters'] instanceof SearchFilterDto ? $overrides['filters'] : null) : $this->filters,
            array_key_exists('stream', $overrides) ? (is_bool($overrides['stream']) ? $overrides['stream'] : null) : $this->stream,
            array_key_exists('offset', $overrides) ? (is_int($overrides['offset']) ? $overrides['offset'] : null) : $this->offset,
            array_key_exists('facets', $overrides) ? ($overrides['facets'] instanceof FacetRequestDto ? $overrides['facets'] : null) : $this->facets,
            array_key_exists('rerank', $overrides) ? (is_bool($overrides['rerank']) ? $overrides['rerank'] : null) : $this->rerank,
        );
    }

    public function withLimit(?int $limit): self
    {
        return $this->cloneWith(['limit' => $limit]);
    }

    public function withOffset(?int $offset): self
    {
        return $this->cloneWith(['offset' => $offset]);
    }

    public function setLimit(?int $limit): self
    {
        return $this->withLimit($limit);
    }

    public function setOffset(?int $offset): self
    {
        return $this->withOffset($offset);
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
     * @param string|array<mixed>|null $userRoles End-user roles asserted by the integrating client (server-to-server trust model).
     *                                            If the API key has allowed_asserted_roles configured, the backend validates
     *                                            both userRoles and filters.permissions against the whitelist. Asserting
     *                                            unauthorized roles triggers HTTP 403 AuthorizationException.
     *                                            When allowed_asserted_roles is null, the key is unrestricted.
     */
    public function withUserRoles(string|array|null $userRoles): self
    {
        $rolesList = null;
        if (is_string($userRoles)) {
            $rolesList = [$userRoles];
        } elseif (is_array($userRoles)) {
            $rolesList = [];
            foreach ($userRoles as $role) {
                if (!is_string($role) && !is_int($role)) {
                    throw new \InvalidArgumentException('Each user role must be a string.');
                }
                $rolesList[] = (string) $role;
            }
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
     * @param string|array<mixed>|null $permissions End-user permission/role filters asserted by the integrating client (server-to-server trust model).
     *                                              If the API key has allowed_asserted_roles configured, the backend validates
     *                                              both userRoles and filters.permissions against the whitelist. Asserting
     *                                              unauthorized roles triggers HTTP 403 AuthorizationException.
     *                                              When allowed_asserted_roles is null, the key is unrestricted.
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

    public function withFacets(?FacetRequestDto $facets): self
    {
        return $this->cloneWith(['facets' => $facets]);
    }

    public function withRerank(?bool $rerank = true): self
    {
        return $this->cloneWith(['rerank' => $rerank]);
    }

    public function getFacets(): ?FacetRequestDto
    {
        return $this->facets;
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

        if ($this->rerank !== null) {
            $data['rerank'] = $this->rerank;
        }

        if ($this->filters !== null && !$this->filters->isEmpty()) {
            $data['filters'] = $this->filters->toArray();
        }

        if ($this->facets !== null) {
            $data['facets'] = $this->facets->toArray();
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawQuery = $data['query'] ?? '';
        if (!is_scalar($rawQuery) && !$rawQuery instanceof \Stringable) {
            throw new \InvalidArgumentException('query must be a string.');
        }
        $query = (string) $rawQuery;

        $rawLimit = $data['limit'] ?? null;
        $limit = null;
        if ($rawLimit !== null) {
            if (!is_int($rawLimit) && !is_numeric($rawLimit)) {
                throw new \InvalidArgumentException('limit must be an integer.');
            }
            $limit = (int) $rawLimit;
        }

        $rawOffset = $data['offset'] ?? null;
        $offset = null;
        if ($rawOffset !== null) {
            if (!is_int($rawOffset) && !is_numeric($rawOffset)) {
                throw new \InvalidArgumentException('offset must be an integer.');
            }
            $offset = (int) $rawOffset;
        }

        $rawLocale = $data['locale'] ?? null;
        $locale = null;
        if ($rawLocale !== null) {
            if (!is_scalar($rawLocale) && !$rawLocale instanceof \Stringable) {
                throw new \InvalidArgumentException('locale must be a string.');
            }
            $locale = (string) $rawLocale;
        }

        /** @var list<string>|null $userRoles */
        $userRoles = null;
        $rawRoles = $data['user_roles'] ?? $data['userRoles'] ?? null;
        if ($rawRoles !== null) {
            if (!is_array($rawRoles)) {
                throw new \InvalidArgumentException('user_roles must be an array.');
            }
            $userRoles = [];
            foreach ($rawRoles as $role) {
                if (!is_scalar($role) && !$role instanceof \Stringable) {
                    throw new \InvalidArgumentException('user_roles items must be strings.');
                }
                $userRoles[] = (string) $role;
            }
        }

        $rawMinScore = $data['min_score'] ?? $data['minScore'] ?? null;
        $minScore = null;
        if ($rawMinScore !== null) {
            if (!is_float($rawMinScore) && !is_numeric($rawMinScore)) {
                throw new \InvalidArgumentException('min_score must be a float.');
            }
            $minScore = (float) $rawMinScore;
        }

        $rawDistinct = $data['distinct_documents'] ?? $data['distinctDocuments'] ?? null;
        $distinctDocuments = null;
        if ($rawDistinct !== null) {
            if (!is_scalar($rawDistinct)) {
                throw new \InvalidArgumentException('distinct_documents must be a boolean.');
            }
            $distinctDocuments = self::extractNullableBool($rawDistinct);
        }

        $rawSynth = $data['synthesize_answer'] ?? $data['synthesizeAnswer'] ?? $data['synthesize'] ?? null;
        $synthesizeAnswer = null;
        if ($rawSynth !== null) {
            if (!is_scalar($rawSynth)) {
                throw new \InvalidArgumentException('synthesize_answer must be a boolean.');
            }
            $synthesizeAnswer = self::extractNullableBool($rawSynth);
        }

        $rawStream = $data['stream'] ?? null;
        $stream = null;
        if ($rawStream !== null) {
            if (!is_scalar($rawStream)) {
                throw new \InvalidArgumentException('stream must be a boolean.');
            }
            $stream = self::extractNullableBool($rawStream);
        }

        $rawRerank = $data['rerank'] ?? null;
        $rerank = null;
        if ($rawRerank !== null) {
            if (!is_scalar($rawRerank)) {
                throw new \InvalidArgumentException('rerank must be a boolean.');
            }
            $rerank = self::extractNullableBool($rawRerank);
        }

        $filters = null;
        if (isset($data['filters'])) {
            if (!is_array($data['filters'])) {
                throw new \InvalidArgumentException('filters must be an array.');
            }
            /** @var array<string, mixed> $filtersData */
            $filtersData = $data['filters'];
            $filters = SearchFilterDto::fromArray($filtersData);
        }

        $facets = null;
        if (isset($data['facets'])) {
            if (!is_array($data['facets'])) {
                throw new \InvalidArgumentException('facets must be an array.');
            }
            /** @var array<string, mixed> $facetsData */
            $facetsData = $data['facets'];
            $facets = FacetRequestDto::fromArray($facetsData);
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
            $offset,
            $facets,
            $rerank
        );
    }
}
