<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

use KinetiStack\Sdk\Enum\JobStatus;
use KinetiStack\Sdk\Enum\WebhookStatus;

class BatchJobDto
{
    /**
     * @param BatchJobItemResultDto[]|null $results
     */
    public function __construct(
        public readonly string $jobId,
        public readonly JobStatus $status,
        public readonly ?int $totalImages = null,
        public readonly ?int $processedImages = null,
        public readonly ?WebhookStatus $webhookStatus = null,
        public readonly ?array $results = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $completedAt = null,
        public readonly ?string $pollUrl = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $results = null;
        if (isset($data['results']) && is_array($data['results'])) {
            $results = array_map(
                static fn (array $resultData): BatchJobItemResultDto => BatchJobItemResultDto::fromArray($resultData),
                $data['results']
            );
        }

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

        return new self(
            (string) ($data['job_id'] ?? $data['jobId'] ?? ''),
            $status,
            isset($data['total_images']) ? (int) $data['total_images'] : (isset($data['totalImages']) ? (int) $data['totalImages'] : null),
            isset($data['processed_images']) ? (int) $data['processed_images'] : (isset($data['processedImages']) ? (int) $data['processedImages'] : null),
            $webhookStatus,
            $results,
            $data['created_at'] ?? $data['createdAt'] ?? null,
            $data['completed_at'] ?? $data['completedAt'] ?? null,
            $data['poll_url'] ?? $data['pollUrl'] ?? null,
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
                static fn (BatchJobItemResultDto $result): array => $result->toArray(),
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

    public function getProgressPercentage(): ?float
    {
        if ($this->totalImages === null || $this->totalImages <= 0) {
            return null;
        }

        $processed = $this->processedImages ?? 0;

        return round(min(100.0, ($processed / $this->totalImages) * 100.0), 2);
    }
}
