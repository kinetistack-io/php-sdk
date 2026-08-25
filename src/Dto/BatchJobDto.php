<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class BatchJobDto
{
    /**
     * @param BatchJobItemResultDto[]|null $results
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $status,
        public readonly ?int $totalImages = null,
        public readonly ?int $processedImages = null,
        public readonly ?string $webhookStatus = null,
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
                fn (array $resultData) => BatchJobItemResultDto::fromArray($resultData),
                $data['results']
            );
        }

        return new self(
            $data['job_id'] ?? '',
            $data['status'] ?? 'pending',
            $data['total_images'] ?? null,
            $data['processed_images'] ?? null,
            $data['webhook_status'] ?? null,
            $results,
            $data['created_at'] ?? null,
            $data['completed_at'] ?? null,
            $data['poll_url'] ?? null,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' || $this->status === 'failed';
    }
}
