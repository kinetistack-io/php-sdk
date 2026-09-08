<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class UsageSummaryDto
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $service,
        public readonly int $totalTokens,
        public readonly int $requestCount,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'service' => $this->service,
            'total_tokens' => $this->totalTokens,
            'request_count' => $this->requestCount,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['project_id'] ?? $data['projectId'] ?? ''),
            (string) ($data['project_name'] ?? $data['projectName'] ?? ''),
            (string) ($data['service'] ?? ''),
            (int) ($data['total_tokens'] ?? $data['totalTokens'] ?? 0),
            (int) ($data['request_count'] ?? $data['requestCount'] ?? 0),
        );
    }
}
