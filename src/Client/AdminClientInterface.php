<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Client;

use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Dto\ApiKeyCreatedDto;
use KinetiStack\Sdk\Dto\ApiKeyDto;
use KinetiStack\Sdk\Dto\AuthTokenDto;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Dto\OrganizationDto;
use KinetiStack\Sdk\Dto\ProjectDto;
use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use KinetiStack\Sdk\Dto\RegisterDto;
use KinetiStack\Sdk\Dto\RegisterResponseDto;
use KinetiStack\Sdk\Dto\RegistrationStatusDto;
use KinetiStack\Sdk\Dto\UsageSummaryDto;
use KinetiStack\Sdk\Dto\UserDto;
use KinetiStack\Sdk\Dto\UserProjectAssignmentDto;
use KinetiStack\Sdk\Enum\AnalyticsGrouping;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\ConflictException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\NotFoundException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\Transport\TransportInterface;

interface AdminClientInterface
{
    public function withToken(string $jwtToken): static;

    public function getTransport(): TransportInterface;

    public function getLastRateLimitInfo(): ?RateLimitInfoDto;

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
     * Verify whether the current admin session or JWT token is valid and active.
     *
     * @return bool True if session is valid and active.
     *
     * @throws AuthenticationException When the admin session or JWT token is expired, invalid, or unauthenticated (HTTP 401).
     * @throws AuthorizationException When the authenticated user lacks admin privileges (HTTP 403).
     * @throws KinetiException
     */
    public function ping(): bool;

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
     * Resend an email verification message for an unverified user account.
     *
     * @throws ValidationException When email is invalid or unprocessable (HTTP 422).
     * @throws KinetiException
     */
    public function resendVerificationEmail(string $email): void;

    /**
     * Verify a user's email address using a verification token.
     *
     * @param string $token The verification token received via email.
     *
     * @throws \InvalidArgumentException When the token is empty or whitespace.
     * @throws ValidationException When the token is invalid or expired (HTTP 422).
     * @throws KinetiException
     */
    public function verifyEmail(string $token): void;

    /**
     * @deprecated Organizations cannot be created directly. Organizations are single-tenant roots initialized during registration via register() and retrieved via getOrganization($id). This method will be removed in the next major version.
     *
     * @param array<string, mixed>|string $name
     * @throws \BadMethodCallException Because organization creation via this endpoint is not supported by the backend.
     * @throws KinetiException
     */
    public function createOrganization(
        array|string $name,
        ?string $billingTier = null,
        ?int $monthlyQuotaCap = null
    ): OrganizationDto;

    /**
     * @deprecated Organizations cannot be listed directly. Organizations are single-tenant roots initialized during registration and retrieved via getOrganization($id). This method will be removed in the next major version.
     *
     * @param array<string, mixed> $options
     * @return list<OrganizationDto>
     * @throws \BadMethodCallException Because organization listing is not supported by the backend.
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
        ?array $settings = null,
        ?int $monthlyQuotaCap = null
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
     * @param array<string, mixed>|string $projectOrData Project ID string or array containing project_id/projectId
     * @param array<string, mixed>|string $nameOrData
     * @throws \InvalidArgumentException When project ID is empty.
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
     * @param string|null $projectId Project ID string. Must not be empty.
     * @param array<string, mixed> $options
     * @return list<ApiKeyDto>
     * @throws \InvalidArgumentException When $projectId is null or empty.
     * @throws KinetiException
     */
    public function listApiKeys(?string $projectId = null, array $options = []): array;

    /**
     * Retrieve metadata and status for a specific API key without fetching the entire project key collection.
     *
     * @param string $projectId The project identifier.
     * @param string $keyId The API key identifier.
     *
     * @throws \InvalidArgumentException When $projectId or $keyId is empty.
     * @throws NotFoundException When the API key is not found (HTTP 404).
     * @throws KinetiException
     */
    public function getApiKey(string $projectId, string $keyId): ApiKeyDto;

    /**
     * @param string $projectIdOrKeyId The project identifier (named $projectIdOrKeyId for backwards compatibility).
     * @param string|null $keyId The API key identifier. Must not be empty.
     * @throws \InvalidArgumentException When $projectIdOrKeyId or $keyId is empty.
     * @throws KinetiException
     */
    public function revokeApiKey(string $projectIdOrKeyId, ?string $keyId = null): void;

    /**
     * @throws \InvalidArgumentException When $projectId or $keyId is empty.
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

    public function analytics(): AnalyticsClientInterface;

    /**
     * @throws KinetiException
     */
    public function getAnalytics(
        AnalyticsGrouping $grouping = AnalyticsGrouping::DAY,
        ?string $from = null,
        ?string $to = null,
        ?string $projectId = null
    ): AnalyticsDto;

    /**
     * @param array<string, mixed> $options
     * @return list<JobDto>
     * @throws KinetiException
     */
    public function listProjectJobs(string $projectId, int $page = 1, array $options = []): array;

    /**
     * @throws KinetiException
     */
    public function retryJob(string $jobId): JobDto;

    /**
     * @throws KinetiException
     */
    public function getJob(string $jobId): JobDto;

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

    /**
     * Get the currently authenticated user's profile.
     *
     * @throws KinetiException
     */
    public function getMe(): UserDto;

    /**
     * Update the currently authenticated user's profile.
     *
     * @param array<string, mixed> $payload
     * @throws ConflictException When email already exists (HTTP 409).
     * @throws ValidationException When payload fails validation (HTTP 422).
     * @throws KinetiException
     */
    public function updateMe(array $payload): UserDto;

    /**
     * Update the currently authenticated user's password.
     *
     * @throws ValidationException When current password is incorrect or new password does not satisfy validation rules (HTTP 422).
     * @throws KinetiException
     */
    public function updateMyPassword(
        #[\SensitiveParameter] string $currentPassword,
        #[\SensitiveParameter] string $newPassword
    ): void;
}
