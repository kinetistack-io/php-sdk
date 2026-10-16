<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class UserProjectAssignmentDto
{
    use ExtractsResponseDataTrait;

    public function __construct(
        public readonly string $userId,
        public readonly string $projectId = '',
        public readonly string $permission = 'member',
        public readonly string $assignedAt = '',
        public readonly ?string $email = null,
    ) {
        if (trim($this->userId) === '') {
            throw new \InvalidArgumentException('userId cannot be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'user_id' => $this->userId,
            'project_id' => $this->projectId,
            'permission' => $this->permission,
            'assigned_at' => $this->assignedAt,
        ];

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?string $projectId = null): self
    {
        $userId = self::extractString($data['user_id'] ?? $data['userId'] ?? null, '');
        $extractedProjectId = self::extractString($data['project_id'] ?? $data['projectId'] ?? null, '');
        $resolvedProjectId = $projectId ?? $extractedProjectId;
        $permission = self::extractString($data['permission'] ?? null, 'member');
        $assignedAt = self::extractString($data['assigned_at'] ?? $data['assignedAt'] ?? null, '');
        $email = self::extractNullableString($data['email'] ?? null);

        return new self($userId, $resolvedProjectId, $permission, $assignedAt, $email);
    }
}
