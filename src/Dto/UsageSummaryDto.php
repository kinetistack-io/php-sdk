<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class UsageSummaryDto
{
    use ExtractsResponseDataTrait;

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
        $projectId = self::extractString($data['project_id'] ?? $data['projectId'] ?? null, '');
        $projectName = self::extractString($data['project_name'] ?? $data['projectName'] ?? null, '');
        $service = self::extractString($data['service'] ?? null, '');
        $totalTokens = self::extractInt($data['total_tokens'] ?? $data['totalTokens'] ?? null, 0);
        $requestCount = self::extractInt($data['request_count'] ?? $data['requestCount'] ?? null, 0);

        return new self(
            $projectId,
            $projectName,
            $service,
            $totalTokens,
            $requestCount,
        );
    }
}
