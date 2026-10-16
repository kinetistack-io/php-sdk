<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

use KinetiStack\Sdk\Enum\JobStatus;
use KinetiStack\Sdk\Enum\WebhookStatus;

class JobDto
{
    use ExtractsResponseDataTrait;
    use ExtractsTokenUsageTrait;

    /**
     * @var array<string, mixed>|list<mixed>|null
     */
    public readonly ?array $results;

    /**
     * @var array<string, mixed>|list<mixed>|null
     */
    public readonly ?array $result;

    /**
     * @param array<string, mixed>|list<mixed>|null $results
     * @param array<string, mixed>|list<mixed>|null $result Alias for $results
     */
    public function __construct(
        public readonly string $jobId,
        public readonly JobStatus $status,
        public readonly ?string $type = null,
        public readonly ?int $totalImages = null,
        public readonly ?int $processedImages = null,
        public readonly ?WebhookStatus $webhookStatus = null,
        ?array $results = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $completedAt = null,
        public readonly ?string $pollUrl = null,
        public readonly ?string $documentId = null,
        public readonly ?string $externalId = null,
        public readonly ?int $chunksGenerated = null,
        public readonly ?int $tokensConsumed = null,
        ?array $result = null,
    ) {
        $resolvedResults = $results ?? $result;
        $this->results = $resolvedResults;
        $this->result = $resolvedResults;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawStatus = $data['status'] ?? 'pending';
        $status = $rawStatus instanceof JobStatus
            ? $rawStatus
            : (is_string($rawStatus) ? (JobStatus::tryFrom($rawStatus) ?? JobStatus::Pending) : JobStatus::Pending);

        $rawWebhookStatus = $data['webhook_status'] ?? $data['webhookStatus'] ?? null;
        $webhookStatus = null;
        if ($rawWebhookStatus instanceof WebhookStatus) {
            $webhookStatus = $rawWebhookStatus;
        } elseif (is_string($rawWebhookStatus)) {
            $webhookStatus = WebhookStatus::tryFrom($rawWebhookStatus);
        }

        $rawResults = $data['results'] ?? $data['result'] ?? null;
        $results = null;
        if (is_array($rawResults)) {
            if (array_is_list($rawResults) && (empty($rawResults) || is_array($rawResults[0]) || $rawResults[0] instanceof BatchJobItemResultDto)) {
                $results = array_map(
                    static function (mixed $resultData): mixed {
                        if (is_array($resultData) && (isset($resultData['external_id']) || isset($resultData['externalId']) || isset($resultData['alt_text']) || isset($resultData['altText']) || isset($resultData['error']))) {
                            /** @var array<string, mixed> $resultData */
                            return BatchJobItemResultDto::fromArray($resultData);
                        }
                        return $resultData;
                    },
                    $rawResults
                );
            } else {
                $results = $rawResults;
            }
        }

        $documentId = self::extractNullableString($data['document_id'] ?? $data['documentId'] ?? null);
        $externalId = self::extractNullableString($data['external_id'] ?? $data['externalId'] ?? null);
        $chunksGenerated = self::extractNullableInt($data['chunks_generated'] ?? $data['chunksGenerated'] ?? null);

        if ($documentId === null && is_array($results) && isset($results['document_id'])) {
            $documentId = self::extractNullableString($results['document_id']);
        }
        if ($externalId === null && is_array($results) && isset($results['external_id'])) {
            $externalId = self::extractNullableString($results['external_id']);
        }
        if ($chunksGenerated === null && is_array($results) && isset($results['chunks_generated'])) {
            $chunksGenerated = self::extractNullableInt($results['chunks_generated']);
        }

        $tokensConsumed = self::extractTokensConsumed($data);
        if ($tokensConsumed === null && is_array($results)) {
            /** @var array<string, mixed> $resultsData */
            $resultsData = $results;
            $tokensConsumed = self::extractTokensConsumed($resultsData);
        }

        return new self(
            self::extractString($data['job_id'] ?? $data['jobId'] ?? ''),
            $status,
            self::extractNullableString($data['type'] ?? null),
            self::extractNullableInt($data['total_images'] ?? $data['totalImages'] ?? null),
            self::extractNullableInt($data['processed_images'] ?? $data['processedImages'] ?? null),
            $webhookStatus,
            $results,
            self::extractNullableString($data['created_at'] ?? $data['createdAt'] ?? null),
            self::extractNullableString($data['completed_at'] ?? $data['completedAt'] ?? null),
            self::extractNullableString($data['poll_url'] ?? $data['pollUrl'] ?? null),
            $documentId,
            $externalId,
            $chunksGenerated,
            $tokensConsumed,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'job_id' => $this->jobId,
            'status' => $this->status->value,
        ];

        if ($this->type !== null) {
            $data['type'] = $this->type;
        }
        if ($this->totalImages !== null) {
            $data['total_images'] = $this->totalImages;
        }
        if ($this->processedImages !== null) {
            $data['processed_images'] = $this->processedImages;
        }
        if ($this->webhookStatus !== null) {
            $data['webhook_status'] = $this->webhookStatus->value;
        }
        if ($this->results !== null) {
            $data['results'] = array_map(
                static function (mixed $item): mixed {
                    if ($item instanceof BatchJobItemResultDto) {
                        return $item->toArray();
                    }
                    return $item;
                },
                $this->results
            );
        }
        if ($this->createdAt !== null) {
            $data['created_at'] = $this->createdAt;
        }
        if ($this->completedAt !== null) {
            $data['completed_at'] = $this->completedAt;
        }
        if ($this->pollUrl !== null) {
            $data['poll_url'] = $this->pollUrl;
        }
        if ($this->documentId !== null) {
            $data['document_id'] = $this->documentId;
        }
        if ($this->externalId !== null) {
            $data['external_id'] = $this->externalId;
        }
        if ($this->chunksGenerated !== null) {
            $data['chunks_generated'] = $this->chunksGenerated;
        }
        if ($this->tokensConsumed !== null) {
            $data['tokens_consumed'] = $this->tokensConsumed;
        }

        return $data;
    }

    public function isPending(): bool
    {
        return $this->status === JobStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === JobStatus::Processing;
    }

    public function isCompleted(): bool
    {
        return $this->status->isTerminal();
    }

    public function isFailed(): bool
    {
        return $this->status === JobStatus::Failed;
    }

    public function getProgressPercentage(): float
    {
        if ($this->totalImages === null || $this->totalImages <= 0) {
            return 0.0;
        }

        $processed = max(0, $this->processedImages ?? 0);

        return round(min(100.0, ($processed / $this->totalImages) * 100.0), 2);
    }

    /**
     * @return BatchJobItemResultDto[]|null
     */
    public function getItemResults(): ?array
    {
        if ($this->results === null || !array_is_list($this->results)) {
            return null;
        }

        return array_map(
            static function (mixed $item): BatchJobItemResultDto {
                if ($item instanceof BatchJobItemResultDto) {
                    return $item;
                }
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    return BatchJobItemResultDto::fromArray($item);
                }
                throw new \UnexpectedValueException('Invalid item result');
            },
            $this->results
        );
    }

    public function toVisionResponse(): ?VisionResponseDto
    {
        if ($this->result === null || array_is_list($this->result) || empty($this->result)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $this->result;

        return VisionResponseDto::fromArray($data);
    }

    public function toDocumentResponse(): ?DocumentResponseDto
    {
        if ($this->result === null || array_is_list($this->result) || empty($this->result)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $this->result;

        return DocumentResponseDto::fromArray($data);
    }
}
