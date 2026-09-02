<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class DocumentListOptionsDto
{
    /**
     * @param string|array<string, string>|null $createdAt
     * @param string|array<string, string>|null $updatedAt
     * @param string|array<string, string>|null $order
     * @param array<string, mixed>|null $custom
     */
    public function __construct(
        public readonly ?int $page = null,
        public readonly ?int $itemsPerPage = null,
        public readonly ?string $locale = null,
        public readonly ?string $externalId = null,
        public readonly string|array|null $createdAt = null,
        public readonly string|array|null $updatedAt = null,
        public readonly string|array|null $order = null,
        public readonly ?array $custom = null,
    ) {
    }

    public static function create(): self
    {
        return new self();
    }

    public function withPage(?int $page): self
    {
        return new self(
            $page,
            $this->itemsPerPage,
            $this->locale,
            $this->externalId,
            $this->createdAt,
            $this->updatedAt,
            $this->order,
            $this->custom
        );
    }

    public function withItemsPerPage(?int $itemsPerPage): self
    {
        return new self(
            $this->page,
            $itemsPerPage,
            $this->locale,
            $this->externalId,
            $this->createdAt,
            $this->updatedAt,
            $this->order,
            $this->custom
        );
    }

    public function withLimit(?int $limit): self
    {
        return $this->withItemsPerPage($limit);
    }

    public function withLocale(?string $locale): self
    {
        return new self(
            $this->page,
            $this->itemsPerPage,
            $locale,
            $this->externalId,
            $this->createdAt,
            $this->updatedAt,
            $this->order,
            $this->custom
        );
    }

    public function withExternalId(?string $externalId): self
    {
        return new self(
            $this->page,
            $this->itemsPerPage,
            $this->locale,
            $externalId,
            $this->createdAt,
            $this->updatedAt,
            $this->order,
            $this->custom
        );
    }

    /**
     * @param string|array<string, string>|null $createdAt
     */
    public function withCreatedAt(string|array|null $createdAt): self
    {
        return new self(
            $this->page,
            $this->itemsPerPage,
            $this->locale,
            $this->externalId,
            $createdAt,
            $this->updatedAt,
            $this->order,
            $this->custom
        );
    }

    /**
     * @param string|array<string, string>|null $updatedAt
     */
    public function withUpdatedAt(string|array|null $updatedAt): self
    {
        return new self(
            $this->page,
            $this->itemsPerPage,
            $this->locale,
            $this->externalId,
            $this->createdAt,
            $updatedAt,
            $this->order,
            $this->custom
        );
    }

    /**
     * @param string|array<string, string>|null $order
     */
    public function withOrder(string|array|null $order, ?string $direction = null): self
    {
        $normalizedOrder = $order;
        if (is_string($order) && $direction !== null) {
            $normalizedOrder = [$order => $direction];
        }

        return new self(
            $this->page,
            $this->itemsPerPage,
            $this->locale,
            $this->externalId,
            $this->createdAt,
            $this->updatedAt,
            $normalizedOrder,
            $this->custom
        );
    }

    public function withCustom(string $key, mixed $value): self
    {
        $custom = $this->custom ?? [];
        $custom[$key] = $value;

        return new self(
            $this->page,
            $this->itemsPerPage,
            $this->locale,
            $this->externalId,
            $this->createdAt,
            $this->updatedAt,
            $this->order,
            $custom
        );
    }

    /**
     * @param array<string, mixed>|null $custom
     */
    public function withCustomFilters(?array $custom): self
    {
        return new self(
            $this->page,
            $this->itemsPerPage,
            $this->locale,
            $this->externalId,
            $this->createdAt,
            $this->updatedAt,
            $this->order,
            $custom
        );
    }

    public function isEmpty(): bool
    {
        return $this->page === null
            && $this->itemsPerPage === null
            && ($this->locale === null || trim($this->locale) === '')
            && ($this->externalId === null || trim($this->externalId) === '')
            && $this->createdAt === null
            && $this->updatedAt === null
            && $this->order === null
            && empty($this->custom);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->page !== null) {
            $data['page'] = $this->page;
        }

        if ($this->itemsPerPage !== null) {
            $data['itemsPerPage'] = $this->itemsPerPage;
        }

        if ($this->locale !== null && trim($this->locale) !== '') {
            $data['locale'] = $this->locale;
        }

        if ($this->externalId !== null && trim($this->externalId) !== '') {
            $data['external_id'] = $this->externalId;
        }

        if ($this->createdAt !== null) {
            $data['created_at'] = $this->createdAt;
        }

        if ($this->updatedAt !== null) {
            $data['updated_at'] = $this->updatedAt;
        }

        if ($this->order !== null) {
            $data['order'] = $this->order;
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
        $page = isset($data['page']) ? (int) $data['page'] : null;
        $itemsPerPage = isset($data['itemsPerPage'])
            ? (int) $data['itemsPerPage']
            : (isset($data['items_per_page'])
                ? (int) $data['items_per_page']
                : (isset($data['limit']) ? (int) $data['limit'] : null));

        $locale = isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null;
        $externalId = isset($data['external_id']) && is_string($data['external_id'])
            ? $data['external_id']
            : (isset($data['externalId']) && is_string($data['externalId']) ? $data['externalId'] : null);

        /** @var string|array<string, string>|null $createdAt */
        $createdAt = $data['created_at'] ?? $data['createdAt'] ?? null;
        /** @var string|array<string, string>|null $updatedAt */
        $updatedAt = $data['updated_at'] ?? $data['updatedAt'] ?? null;
        /** @var string|array<string, string>|null $order */
        $order = $data['order'] ?? null;

        $custom = [];
        $knownKeys = [
            'page',
            'itemsPerPage',
            'items_per_page',
            'limit',
            'locale',
            'external_id',
            'externalId',
            'created_at',
            'createdAt',
            'updated_at',
            'updatedAt',
            'order',
        ];

        foreach ($data as $key => $value) {
            if (!in_array($key, $knownKeys, true)) {
                $custom[$key] = $value;
            }
        }

        return new self(
            $page,
            $itemsPerPage,
            $locale,
            $externalId,
            $createdAt,
            $updatedAt,
            $order,
            !empty($custom) ? $custom : null
        );
    }
}
