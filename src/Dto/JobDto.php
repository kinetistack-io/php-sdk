<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

use KinetiStack\Sdk\Enum\JobStatus;
use KinetiStack\Sdk\Enum\WebhookStatus;

class JobDto
{
    /**
     * @param array<string, mixed>|list<mixed>|null $results
     */
    public function __construct(
        public readonly string $jobId,
        public readonly JobStatus $status,
        public readonly ?string $type = null,
        public readonly ?int $totalImages = null,
        public readonly ?int $processedImages = null,
        public readonly ?WebhookStatus $webhookStatus = null,
        public readonly ?array $results = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $completedAt = null,
        public readonly ?string $pollUrl = null,
        public readonly ?string $documentId = null,
        public readonly ?string $externalId = null,
        public readonly ?int $chunksGenerated = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawStatus = $data['status'] ?? 'pending';
        $status = $rawStatus instanceof JobStatus
            ? $rawStatus
            : (JobStatus::tryFrom((string) $rawStatus) ?? JobStatus::Pending);

        $rawWebhookStatus = $data['webhook_status'] ?? $data['webhookStatus'] ?? null;
        $webhookStatus = null;
        if ($rawWebhookStatus instanceof WebhookStatus) {
            $webhookStatus = $rawWebhookStatus;
        } elseif (is_string($rawWebhookStatus)) {
            $webhookStatus = WebhookStatus::tryFrom($rawWebhookStatus);
        }

        $rawResults = $data['results'] ?? null;
        $results = null;
        if (is_array($rawResults)) {
            if (array_is_list($rawResults) && (empty($rawResults) || is_array($rawResults[0]) || $rawResults[0] instanceof BatchJobItemResultDto)) {
                $results = array_map(
                    static function (mixed $resultData): mixed {
                        if (is_array($resultData) && (isset($resultData['external_id']) || isset($resultData['externalId']) || isset($resultData['alt_text']) || isset($resultData['altText']) || isset($resultData['error']))) {
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

        $documentId = isset($data['document_id']) ? (string) $data['document_id'] : (isset($data['documentId']) ? (string) $data['documentId'] : null);
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : (isset($data['externalId']) ? (string) $data['externalId'] : null);
        $chunksGenerated = isset($data['chunks_generated']) ? (int) $data['chunks_generated'] : (isset($data['chunksGenerated']) ? (int) $data['chunksGenerated'] : null);

        if ($documentId === null && is_array($results) && isset($results['document_id'])) {
            $documentId = (string) $results['document_id'];
        }
        if ($externalId === null && is_array($results) && isset($results['external_id'])) {
            $externalId = (string) $results['external_id'];
        }
        if ($chunksGenerated === null && is_array($results) && isset($results['chunks_generated'])) {
            $chunksGenerated = (int) $results['chunks_generated'];
        }

        return new self(
            (string) ($data['job_id'] ?? $data['jobId'] ?? ''),
            $status,
            isset($data['type']) ? (string) $data['type'] : null,
            isset($data['total_images']) ? (int) $data['total_images'] : (isset($data['totalImages']) ? (int) $data['totalImages'] : null),
            isset($data['processed_images']) ? (int) $data['processed_images'] : (isset($data['processedImages']) ? (int) $data['processedImages'] : null),
            $webhookStatus,
            $results,
            $data['created_at'] ?? $data['createdAt'] ?? null,
            $data['completed_at'] ?? $data['completedAt'] ?? null,
            $data['poll_url'] ?? $data['pollUrl'] ?? null,
            $documentId,
            $externalId,
            $chunksGenerated,
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
                    if (is_object($item) && method_exists($item, 'toArray')) {
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
            static fn (mixed $item): BatchJobItemResultDto => is_array($item)
                ? BatchJobItemResultDto::fromArray($item)
                : ($item instanceof BatchJobItemResultDto ? $item : throw new \UnexpectedValueException('Invalid item result')),
            $this->results
        );
    }
}
