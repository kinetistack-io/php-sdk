<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Client;

use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Dto\ApiKeyCreatedDto;
use KinetiStack\Sdk\Dto\ApiKeyDto;
use KinetiStack\Sdk\Dto\AuthTokenDto;
use KinetiStack\Sdk\Dto\BatchJobDto;
use KinetiStack\Sdk\Dto\OrganizationDto;
use KinetiStack\Sdk\Dto\ProjectDto;
use KinetiStack\Sdk\Dto\RegisterDto;
use KinetiStack\Sdk\Dto\RegisterResponseDto;
use KinetiStack\Sdk\Dto\RegistrationStatusDto;
use KinetiStack\Sdk\Dto\UsageSummaryDto;
use KinetiStack\Sdk\Dto\UserDto;
use KinetiStack\Sdk\Dto\UserProjectAssignmentDto;
use KinetiStack\Sdk\Exception\ConflictException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\Transport\TransportInterface;

interface AdminClientInterface
{
    public function withToken(string $jwtToken): static;

    public function getTransport(): TransportInterface;

    public function getApiHost(): string;

    public function getJwtToken(): string;

    /**
     * @throws KinetiException
     */
    public function login(string $email, string $password): AuthTokenDto;

    /**
     * @throws KinetiException
     */
    public function refreshToken(): AuthTokenDto;

    /**
     * @param RegisterDto|array<string, mixed>|string $orgNameOrData
     * @throws KinetiException
     */
    public function register(
        RegisterDto|array|string $orgNameOrData,
        ?string $email = null,
        ?string $password = null
    ): RegisterResponseDto;

    /**
     * @throws KinetiException
     */
    public function getRegistrationStatus(): RegistrationStatusDto;

    /**
     * @param array<string, mixed>|string $name
     * @throws KinetiException
     */
    public function createOrganization(array|string $name, ?string $billingTier = null): OrganizationDto;

    /**
     * @param array<string, mixed> $options
     * @return list<OrganizationDto>
     * @throws KinetiException
     */
    public function listOrganizations(array $options = []): array;

    /**
     * @throws KinetiException
     */
    public function getOrganization(string $id): OrganizationDto;

    /**
     * @param array<string, mixed> $data
     * @throws KinetiException
     */
    public function updateOrganization(string $id, array $data): OrganizationDto;

    /**
     * @param array<string, mixed>|string $nameOrData
     * @param array<string, mixed>|null $settings
     * @throws KinetiException
     */
    public function createProject(
        array|string $nameOrData,
        ?string $domain = null,
        ?string $webhookUrl = null,
        ?array $settings = null
    ): ProjectDto;

    /**
     * @param array<string, mixed> $options
     * @return list<ProjectDto>
     * @throws KinetiException
     */
    public function listProjects(array $options = []): array;

    /**
     * @throws KinetiException
     */
    public function getProject(string $id): ProjectDto;

    /**
     * @param array<string, mixed> $data
     * @throws KinetiException
     */
    public function updateProject(string $id, array $data): ProjectDto;

    /**
     * @throws KinetiException
     */
    public function deleteProject(string $id): void;

    /**
     * @param array<string, mixed>|string $projectOrData
     * @param array<string, mixed>|string $nameOrData
     * @throws KinetiException
     */
    public function createApiKey(
        array|string $projectOrData,
        array|string $nameOrData = [],
        string $scope = 'all',
        ?int $rateLimitPerMinute = null,
        ?string $expiresAt = null
    ): ApiKeyCreatedDto;

    /**
     * @param array<string, mixed> $options
     * @return list<ApiKeyDto>
     * @throws KinetiException
     */
    public function listApiKeys(?string $projectId = null, array $options = []): array;

    /**
     * @throws KinetiException
     */
    public function revokeApiKey(string $projectIdOrKeyId, ?string $keyId = null): void;

    /**
     * @throws KinetiException
     */
    public function rotateApiKey(string $projectId, string $keyId): ApiKeyCreatedDto;

    /**
     * @param array<string, mixed>|string|null $fromOrOptions
     * @return list<UsageSummaryDto>
     * @throws KinetiException
     */
    public function getUsage(
        array|string|null $fromOrOptions = null,
        ?string $to = null,
        ?string $projectId = null
    ): array;

    /**
     * @param array<string, mixed>|string|null $groupByOrOptions
     * @throws KinetiException
     */
    public function getAnalytics(
        array|string|null $groupByOrOptions = 'day',
        ?string $from = null,
        ?string $to = null,
        ?string $projectId = null
    ): AnalyticsDto;

    /**
     * @param array<string, mixed> $options
     * @return list<BatchJobDto>
     * @throws KinetiException
     */
    public function listProjectJobs(string $projectId, int $page = 1, array $options = []): array;

    /**
     * @throws KinetiException
     */
    public function retryJob(string $jobId): BatchJobDto;

    /**
     * @throws KinetiException
     */
    public function getJob(string $jobId): BatchJobDto;

    /**
     * List users in the organization.
     *
     * @param array<string, mixed> $options
     * @return list<UserDto>
     * @throws KinetiException
     */
    public function listUsers(array $options = []): array;

    /**
     * Get a user by ID.
     *
     * @throws KinetiException
     */
    public function getUser(string $id): UserDto;

    /**
     * Create a new user.
     *
     * @param array<string, mixed> $payload
     * @throws ConflictException When email already exists (HTTP 409).
     * @throws ValidationException When payload fails validation (HTTP 422).
     * @throws KinetiException
     */
    public function createUser(array $payload): UserDto;

    /**
     * Update an existing user.
     *
     * @param array<string, mixed> $payload
     * @throws ValidationException When payload fails validation or violates constraints (HTTP 422).
     * @throws KinetiException
     */
    public function updateUser(string $id, array $payload): UserDto;

    /**
     * Delete a user by ID.
     *
     * @throws KinetiException
     */
    public function deleteUser(string $id): void;

    /**
     * List members assigned to a project.
     *
     * @param array<string, mixed> $options
     * @return list<UserProjectAssignmentDto>
     * @throws KinetiException
     */
    public function listProjectMembers(string $projectId, array $options = []): array;

    /**
     * Assign a user to a project.
     *
     * @param array<string, mixed> $payload
     * @throws ConflictException When assignment already exists (HTTP 409).
     * @throws ValidationException When payload fails validation (HTTP 422).
     * @throws KinetiException
     */
    public function assignProjectMember(string $projectId, array $payload): UserProjectAssignmentDto;

    /**
     * Remove a user from a project.
     *
     * @throws KinetiException
     */
    public function removeProjectMember(string $projectId, string $userId): void;

    /**
     * Request a password reset or invitation email.
     *
     * @throws ValidationException When email is invalid or unprocessable (HTTP 422).
     * @throws KinetiException
     */
    public function requestPasswordReset(string $email): void;

    /**
     * Reset a user's password using a reset or invitation token.
     *
     * @throws ValidationException When token is invalid/expired or password does not satisfy validation rules (HTTP 422).
     * @throws KinetiException
     */
    public function resetPassword(string $token, #[\SensitiveParameter] string $newPassword): void;
}
