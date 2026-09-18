<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class UserProjectAssignmentDto
{
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
        $userId = (string) ($data['user_id'] ?? $data['userId'] ?? '');
        $resolvedProjectId = (string) ($projectId ?? $data['project_id'] ?? $data['projectId'] ?? '');
        $permission = (string) ($data['permission'] ?? 'member');
        $assignedAt = (string) ($data['assigned_at'] ?? $data['assignedAt'] ?? '');
        $email = isset($data['email']) && is_string($data['email']) ? $data['email'] : null;

        return new self($userId, $resolvedProjectId, $permission, $assignedAt, $email);
    }
}
