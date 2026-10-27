<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Client\ClientHelperTrait;
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
use KinetiStack\Sdk\Transport\TransportResponseInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdminClient implements AdminClientInterface
{
    use ClientHelperTrait;

    private TransportInterface $transport;
    private ?AnalyticsClientInterface $analyticsClient = null;
    private ?RateLimitInfoDto $lastRateLimitInfo = null;

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $httpClient
     * @param array<string, mixed> $options Default HTTP options (e.g., timeout, headers)
     */
    public function __construct(
        private readonly string $apiHost,
        #[\SensitiveParameter]
        private string $jwtToken = '',
        private readonly HttpClientInterface|ClientInterface|TransportInterface|null $httpClient = null,
        private readonly array $options = []
    ) {
        $this->transport = $this->createBearerTransport(
            $this->apiHost,
            $this->jwtToken,
            null,
            $this->httpClient,
            $this->options
        );
    }

    /**
     * Return a new immutable instance configured with the specified JWT token.
     */
    public function withToken(#[\SensitiveParameter] string $jwtToken): static
    {
        $clone = clone $this;
        $clone->jwtToken = $jwtToken;
        $clone->analyticsClient = null;
        $clone->lastRateLimitInfo = $this->lastRateLimitInfo;
        $authHeaderValue = $jwtToken !== '' ? 'Bearer ' . $jwtToken : '';

        $clone->transport = $this->transport->withAuthHeaderValue($authHeaderValue);

        return $clone;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function getLastRateLimitInfo(): ?RateLimitInfoDto
    {
        return $this->lastRateLimitInfo ?? $this->transport->getLastRateLimitInfo();
    }

    public function getApiHost(): string
    {
        return $this->apiHost;
    }

    public function getJwtToken(): string
    {
        return $this->jwtToken;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $info = get_object_vars($this);
        if ($this->jwtToken !== '') {
            $info['jwtToken'] = '[REDACTED]';
        }

        return $info;
    }

    /**
     * Authenticate an admin user and retrieve a JWT token.
     *
     * @throws KinetiException
     */
    public function login(string $email, #[\SensitiveParameter] string $password): AuthTokenDto
    {
        $response = $this->request('POST', '/api/v1/admin/login', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ], authenticated: false);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return AuthTokenDto::fromArray($data);
    }

    /**
     * Refresh the admin JWT token using the currently active session.
     *
     * @throws AuthenticationException When the existing JWT token is expired, invalid, or unauthenticated (HTTP 401).
     * @throws KinetiException
     */
    public function refreshToken(): AuthTokenDto
    {
        $response = $this->request('POST', '/api/v1/admin/token/refresh');

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return AuthTokenDto::fromArray($data);
    }

    /**
     * Verify whether the current admin session or JWT token is valid and active.
     *
     * Dispatches an HTTP GET request to /api/v1/admin/ping.
     *
     * @return bool True if session is valid and active.
     *
     * @throws AuthenticationException When the admin session or JWT token is expired, invalid, or unauthenticated (HTTP 401).
     * @throws AuthorizationException When the authenticated user lacks admin privileges (HTTP 403).
     * @throws KinetiException
     */
    public function ping(): bool
    {
        $response = $this->request('GET', '/api/v1/admin/ping');

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return ($data['status'] ?? null) === 'pong';
    }

    /**
     * Register a new organization and initial administrator user.
     *
     * Note: This endpoint is unauthenticated and must be called on an AdminClient
     * initialized without a JWT token (similar to login()).
     *
     * @param RegisterDto|array<string, mixed>|string $orgNameOrData Organization name, RegisterDto, or associative payload array containing 'org_name' and 'email'.
     * @param string|null $email Admin user email (required when $orgNameOrData is a string).
     * @return RegisterResponseDto
     * @throws \InvalidArgumentException When scalar arguments or required array fields (org_name, email) are empty.
     * @throws ConflictException When the email address is already registered (HTTP 409).
     * @throws ValidationException When payload fails validation (HTTP 422).
     * @throws AuthorizationException When registration is closed or not allowed (HTTP 403).
     * @throws KinetiException
     */
    public function register(
        RegisterDto|array|string $orgNameOrData,
        ?string $email = null,
    ): RegisterResponseDto {
        if ($orgNameOrData instanceof RegisterDto) {
            $payload = $orgNameOrData->toArray();
        } elseif (is_array($orgNameOrData)) {
            $rawOrgName = $orgNameOrData['org_name'] ?? null;
            $orgName = is_scalar($rawOrgName) || $rawOrgName instanceof \Stringable ? trim((string) $rawOrgName) : '';
            if ($orgName === '') {
                throw new \InvalidArgumentException('org_name cannot be empty.');
            }

            $rawEmail = $orgNameOrData['email'] ?? null;
            $email = is_scalar($rawEmail) || $rawEmail instanceof \Stringable ? trim((string) $rawEmail) : '';
            if ($email === '') {
                throw new \InvalidArgumentException('email cannot be empty.');
            }

            $payload = $orgNameOrData;
            $payload['org_name'] = $orgName;
            $payload['email'] = $email;
            unset($payload['password']);
        } else {
            $dto = new RegisterDto($orgNameOrData, (string) $email);
            $payload = $dto->toArray();
        }

        $response = $this->request('POST', '/api/v1/admin/register', [
            'json' => $payload,
        ], authenticated: false);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return RegisterResponseDto::fromArray($data);
    }

    /**
     * Get the current registration status (mode) of the backend.
     *
     * @throws KinetiException
     */
    public function getRegistrationStatus(): RegistrationStatusDto
    {
        $response = $this->request('GET', '/api/v1/admin/registration-status', authenticated: false);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return RegistrationStatusDto::fromArray($data);
    }

    /**
     * Resend an email verification message for an unverified user account.
     *
     * @throws ValidationException When email is invalid or unprocessable (HTTP 422).
     * @throws KinetiException
     */
    public function resendVerificationEmail(string $email): void
    {
        $this->request('POST', '/api/v1/admin/resend-verification', [
            'json' => [
                'email' => $email,
            ],
        ], authenticated: false);
    }

    /**
     * Verify a user's email address using a verification token and set their password.
     *
     * @param string $token The verification token received via email.
     * @param string $password The password to set for the account.
     *
     * @return AuthTokenDto The authentication token issued upon successful verification.
     *
     * @throws \InvalidArgumentException When the token or password is empty or whitespace.
     * @throws ValidationException When the token is invalid or expired (HTTP 422).
     * @throws KinetiException
     */
    public function verifyEmail(#[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): AuthTokenDto
    {
        if (trim($token) === '') {
            throw new \InvalidArgumentException('Verification token cannot be empty.');
        }

        if (trim($password) === '') {
            throw new \InvalidArgumentException('Password cannot be empty.');
        }

        $response = $this->request('POST', '/api/v1/admin/verify-email', [
            'json' => [
                'token' => $token,
                'password' => $password,
            ],
        ], authenticated: false);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return AuthTokenDto::fromArray($data);
    }

    /**
     * Get details of a single organization.
     *
     * @throws KinetiException
     */
    public function getOrganization(string $id): OrganizationDto
    {
        $response = $this->request('GET', sprintf('/api/v1/admin/organizations/%s', $this->encodePathSegment($id, 'Organization ID')));

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return OrganizationDto::fromArray($data);
    }

    /**
     * Update an organization (e.g., name or billing tier).
     *
     * @param array<string, mixed> $data
     * @throws KinetiException
     */
    public function updateOrganization(string $id, array $data): OrganizationDto
    {
        $data = $this->normalizePayloadKeys($data, ['monthlyQuotaCap' => 'monthly_quota_cap']);

        $response = $this->request(
            'PATCH',
            sprintf('/api/v1/admin/organizations/%s', $this->encodePathSegment($id, 'Organization ID')),
            [
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => $data,
            ]
        );

        /** @var array<string, mixed> $resData */
        $resData = $response->toArray();

        return OrganizationDto::fromArray($resData);
    }

    /**
     * Soft-delete an organization and deactivate its associated resources.
     *
     * @throws KinetiException
     */
    public function deleteOrganization(string $id): void
    {
        $this->request('DELETE', sprintf('/api/v1/admin/organizations/%s', $this->encodePathSegment($id, 'Organization ID')));
    }

    /**
     * Create a new project within the authenticated organization.
     *
     * @param array<string, mixed>|string $nameOrData
     * @param array<string, mixed>|null $settings
     * @param string|null $defaultDocumentVisibility
     * @throws KinetiException
     */
    public function createProject(
        array|string $nameOrData,
        ?string $domain = null,
        ?string $webhookUrl = null,
        ?array $settings = null,
        ?int $monthlyQuotaCap = null,
        ?string $defaultDocumentVisibility = null,
    ): ProjectDto {
        if (is_array($nameOrData)) {
            $payload = $nameOrData;
            if ($defaultDocumentVisibility !== null && !isset($payload['default_document_visibility']) && !isset($payload['defaultDocumentVisibility'])) {
                $payload['default_document_visibility'] = $defaultDocumentVisibility;
            }
        } else {
            $payload = [
                'name' => $nameOrData,
                'domain' => (string) $domain,
            ];
            if ($webhookUrl !== null) {
                $payload['webhook_url'] = $webhookUrl;
            }
            if ($settings !== null) {
                $payload['settings'] = $settings;
            }
            if ($monthlyQuotaCap !== null) {
                $payload['monthly_quota_cap'] = $monthlyQuotaCap;
            }
            if ($defaultDocumentVisibility !== null) {
                $payload['default_document_visibility'] = $defaultDocumentVisibility;
            }
        }

        $payload = $this->normalizePayloadKeys($payload, [
            'monthlyQuotaCap' => 'monthly_quota_cap',
            'defaultDocumentVisibility' => 'default_document_visibility',
        ]);

        $response = $this->request('POST', '/api/v1/admin/projects', [
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return ProjectDto::fromArray($data);
    }

    /**
     * List projects in the current organization.
     *
     * @param array<string, mixed> $options
     * @return list<ProjectDto>
     * @throws KinetiException
     */
    public function listProjects(array $options = []): array
    {
        $requestOptions = [];
        if (!empty($options)) {
            $requestOptions['query'] = $options;
        }

        $response = $this->request('GET', '/api/v1/admin/projects', $requestOptions);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(static fn (array $item): ProjectDto => ProjectDto::fromArray($item), $items);
    }

    /**
     * Get a single project by ID.
     *
     * @throws KinetiException
     */
    public function getProject(string $id): ProjectDto
    {
        $response = $this->request('GET', sprintf('/api/v1/admin/projects/%s', $this->encodePathSegment($id, 'Project ID')));

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return ProjectDto::fromArray($data);
    }

    /**
     * Update project settings, name, domain, webhook, quota, or document visibility.
     *
     * @param string $id Project ID.
     * @param array<string, mixed> $data Updatable project fields. Recognized keys include 'name' (string),
     *                                   'domain' (string), 'webhook_url'|'webhookUrl' (?string),
     *                                   'settings' (?array), 'monthly_quota_cap'|'monthlyQuotaCap' (?int),
     *                                   and 'default_document_visibility'|'defaultDocumentVisibility' (?string, 'public'|'restricted').
     * @throws KinetiException
     */
    public function updateProject(string $id, array $data): ProjectDto
    {
        $data = $this->normalizePayloadKeys($data, [
            'monthlyQuotaCap' => 'monthly_quota_cap',
            'defaultDocumentVisibility' => 'default_document_visibility',
        ]);

        $response = $this->request(
            'PATCH',
            sprintf('/api/v1/admin/projects/%s', $this->encodePathSegment($id, 'Project ID')),
            [
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => $data,
            ]
        );

        /** @var array<string, mixed> $resData */
        $resData = $response->toArray();

        return ProjectDto::fromArray($resData);
    }

    /**
     * Soft-delete a project and revoke all associated API keys.
     *
     * @throws KinetiException
     */
    public function deleteProject(string $id): void
    {
        $this->request('DELETE', sprintf('/api/v1/admin/projects/%s', $this->encodePathSegment($id, 'Project ID')));
    }

    /**
     * Create an API key for a project. Returns plaintext token in ApiKeyCreatedDto.
     *
     * @param array<string, mixed>|string $projectOrData Project ID string or full data array
     * @param array<string, mixed>|string $nameOrData Key name string or payload array
     * @param string $scope Scope restricting API key permissions. Valid values: 'all', 'search', 'rag', 'ingest', 'vision'.
     *                      RAG endpoints require 'rag' or 'all', while standard search requires 'search' or 'all'.
     * @param int|null $rateLimitPerMinute Optional rate limit override (requests per minute)
     * @param string|null $expiresAt Optional ISO 8601 expiration date string
     * @param int|null $dailyTokenQuotaOverride Optional per-key daily token quota override (must be > 0)
     * @param list<string>|null $allowedAssertedRoles Optional allowed asserted roles for search permission filtering
     * @throws \InvalidArgumentException When project ID is empty.
     * @throws KinetiException
     */
    public function createApiKey(
        array|string $projectOrData,
        array|string $nameOrData = [],
        string $scope = 'all',
        ?int $rateLimitPerMinute = null,
        ?string $expiresAt = null,
        ?int $dailyTokenQuotaOverride = null,
        ?array $allowedAssertedRoles = null,
    ): ApiKeyCreatedDto {
        $projectId = '';
        if (is_array($projectOrData)) {
            $rawProj = $projectOrData['project_id'] ?? $projectOrData['projectId'] ?? '';
            $projectId = is_scalar($rawProj) ? (string) $rawProj : '';
            $payload = $projectOrData;
            unset($payload['project_id'], $payload['projectId']);
            if ($allowedAssertedRoles !== null && !isset($payload['allowed_asserted_roles']) && !isset($payload['allowedAssertedRoles'])) {
                $payload['allowed_asserted_roles'] = $allowedAssertedRoles;
            }
        } elseif (is_array($nameOrData)) {
            $projectId = $projectOrData;
            $payload = $nameOrData;
            if ($allowedAssertedRoles !== null && !isset($payload['allowed_asserted_roles']) && !isset($payload['allowedAssertedRoles'])) {
                $payload['allowed_asserted_roles'] = $allowedAssertedRoles;
            }
        } else {
            $projectId = $projectOrData;
            $payload = [
                'name' => $nameOrData,
                'scope' => $scope,
            ];
            if ($rateLimitPerMinute !== null) {
                $payload['rate_limit_per_minute'] = $rateLimitPerMinute;
            }
            if ($expiresAt !== null) {
                $payload['expires_at'] = $expiresAt;
            }
            if ($dailyTokenQuotaOverride !== null) {
                $payload['daily_token_quota_override'] = $dailyTokenQuotaOverride;
            }
            if ($allowedAssertedRoles !== null) {
                $payload['allowed_asserted_roles'] = $allowedAssertedRoles;
            }
        }

        $payload = $this->normalizePayloadKeys($payload, [
            'dailyTokenQuotaOverride' => 'daily_token_quota_override',
            'allowedAssertedRoles' => 'allowed_asserted_roles',
        ]);

        $path = sprintf('/api/v1/admin/projects/%s/api-keys', $this->encodePathSegment($projectId, 'Project ID'));

        $response = $this->request('POST', $path, [
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return ApiKeyCreatedDto::fromArray($data);
    }

    /**
     * List API keys for a project (without plaintext token).
     *
     * @param string|null $projectId Project ID string. Must not be empty.
     * @param array<string, mixed> $options
     * @return list<ApiKeyDto>
     * @throws \InvalidArgumentException When $projectId is null or empty.
     * @throws KinetiException
     */
    public function listApiKeys(?string $projectId = null, array $options = []): array
    {
        $path = sprintf('/api/v1/admin/projects/%s/api-keys', $this->encodePathSegment($projectId, 'Project ID'));

        $requestOptions = [];
        if (!empty($options)) {
            $requestOptions['query'] = $options;
        }

        $response = $this->request('GET', $path, $requestOptions);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(static fn (array $item): ApiKeyDto => ApiKeyDto::fromArray($item), $items);
    }

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
    public function getApiKey(string $projectId, string $keyId): ApiKeyDto
    {
        $path = sprintf(
            '/api/v1/admin/projects/%s/api-keys/%s',
            $this->encodePathSegment($projectId, 'Project ID'),
            $this->encodePathSegment($keyId, 'API key ID')
        );
        $response = $this->request('GET', $path);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return ApiKeyDto::fromArray($data);
    }

    /**
     * Revoke an API key.
     *
     * @param string $projectId The project identifier.
     * @param string $keyId The API key identifier. Must not be empty.
     * @throws \InvalidArgumentException When $projectId or $keyId is empty.
     * @throws KinetiException
     */
    public function revokeApiKey(string $projectId, string $keyId): void
    {
        $path = sprintf(
            '/api/v1/admin/projects/%s/api-keys/%s',
            $this->encodePathSegment($projectId, 'Project ID'),
            $this->encodePathSegment($keyId, 'API key ID')
        );

        $this->request('DELETE', $path);
    }

    /**
     * Rotate an existing API key, generating a new raw token with a grace period for the old key.
     *
     * @throws \InvalidArgumentException When $projectId or $keyId is empty.
     * @throws KinetiException
     */
    public function rotateApiKey(string $projectId, string $keyId): ApiKeyCreatedDto
    {
        $path = sprintf(
            '/api/v1/admin/projects/%s/api-keys/%s/rotate',
            $this->encodePathSegment($projectId, 'Project ID'),
            $this->encodePathSegment($keyId, 'API key ID')
        );
        $response = $this->request('POST', $path);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return ApiKeyCreatedDto::fromArray($data);
    }

    /**
     * Query usage summary metrics across projects and services.
     *
     * @param array<string, mixed>|string|null $fromOrOptions
     * @return list<UsageSummaryDto>
     * @throws KinetiException
     */
    public function getUsage(
        array|string|null $fromOrOptions = null,
        ?string $to = null,
        ?string $projectId = null,
        ?string $apiKeyId = null,
    ): array {
        $query = [];
        if (is_array($fromOrOptions)) {
            $query = $this->normalizePayloadKeys($fromOrOptions, [
                'apiKeyId' => 'api_key_id',
                'projectId' => 'project_id',
            ]);
            if ($apiKeyId !== null && !isset($query['api_key_id'])) {
                $query['api_key_id'] = $apiKeyId;
            }
            if ($projectId !== null && !isset($query['project_id'])) {
                $query['project_id'] = $projectId;
            }
        } else {
            if ($fromOrOptions !== null) {
                $query['from'] = $fromOrOptions;
            }
            if ($to !== null) {
                $query['to'] = $to;
            }
            if ($projectId !== null) {
                $query['project_id'] = $projectId;
            }
            if ($apiKeyId !== null) {
                $query['api_key_id'] = $apiKeyId;
            }
        }

        $requestOptions = [];
        if (!empty($query)) {
            $requestOptions['query'] = $query;
        }

        $response = $this->request('GET', '/api/v1/admin/usage', $requestOptions);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(static fn (array $item): UsageSummaryDto => UsageSummaryDto::fromArray($item), $items);
    }

    public function analytics(): AnalyticsClientInterface
    {
        return $this->analyticsClient ??= new AnalyticsClient($this->transport);
    }

    /**
     * Query aggregated usage analytics buckets.
     *
     * @throws KinetiException
     */
    public function getAnalytics(
        AnalyticsGrouping $grouping = AnalyticsGrouping::DAY,
        ?string $from = null,
        ?string $to = null,
        ?string $projectId = null
    ): AnalyticsDto {
        return $this->analytics()->getAnalytics($grouping, $from, $to, $projectId);
    }

    /**
     * List jobs for a project.
     *
     * @param array<string, mixed> $options
     * @return list<JobDto>
     * @throws KinetiException
     */
    public function listProjectJobs(string $projectId, int $page = 1, array $options = []): array
    {
        $path = sprintf('/api/v1/admin/projects/%s/jobs', $this->encodePathSegment($projectId, 'Project ID'));
        $query = array_merge(['page' => $page], $options);
        $response = $this->request('GET', $path, ['query' => $query]);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(static fn (array $item): JobDto => JobDto::fromArray($item), $items);
    }

    /**
     * Retry a failed job.
     *
     * @throws KinetiException
     */
    public function retryJob(string $jobId): JobDto
    {
        $path = sprintf('/api/v1/admin/jobs/%s/retry', $this->encodePathSegment($jobId, 'Job ID'));
        $response = $this->request('POST', $path);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return JobDto::fromArray($data);
    }

    /**
     * Get a single job by ID.
     *
     * @throws KinetiException
     */
    public function getJob(string $jobId): JobDto
    {
        $path = sprintf('/api/v1/admin/jobs/%s', $this->encodePathSegment($jobId, 'Job ID'));
        $response = $this->request('GET', $path);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return JobDto::fromArray($data);
    }

    /**
     * List users in the organization.
     *
     * @param array<string, mixed> $options
     * @return list<UserDto>
     * @throws KinetiException
     */
    public function listUsers(array $options = []): array
    {
        $requestOptions = [];
        if (!empty($options)) {
            $requestOptions['query'] = $options;
        }

        $response = $this->request('GET', '/api/v1/admin/users', $requestOptions);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(static fn (array $item): UserDto => UserDto::fromArray($item), $items);
    }

    /**
     * Get a user by ID.
     *
     * @throws KinetiException
     */
    public function getUser(string $id): UserDto
    {
        $response = $this->request('GET', sprintf('/api/v1/admin/users/%s', $this->encodePathSegment($id, 'User ID')));

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return UserDto::fromArray($data);
    }

    /**
     * Create a new user.
     *
     * @param array<string, mixed> $payload
     * @throws ConflictException When email already exists (HTTP 409).
     * @throws ValidationException When payload fails validation (HTTP 422).
     * @throws KinetiException
     */
    public function createUser(#[\SensitiveParameter] array $payload): UserDto
    {
        $response = $this->request('POST', '/api/v1/admin/users', [
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return UserDto::fromArray($data);
    }

    /**
     * Update an existing user.
     *
     * @param array<string, mixed> $payload
     * @throws ValidationException When payload fails validation or violates constraints (HTTP 422).
     * @throws KinetiException
     */
    public function updateUser(string $id, #[\SensitiveParameter] array $payload): UserDto
    {
        $response = $this->request(
            'PATCH',
            sprintf('/api/v1/admin/users/%s', $this->encodePathSegment($id, 'User ID')),
            [
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => $payload,
            ]
        );

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return UserDto::fromArray($data);
    }

    /**
     * Delete a user by ID.
     *
     * @throws KinetiException
     */
    public function deleteUser(string $id): void
    {
        $this->request('DELETE', sprintf('/api/v1/admin/users/%s', $this->encodePathSegment($id, 'User ID')));
    }

    /**
     * List members assigned to a project.
     *
     * @param array<string, mixed> $options
     * @return list<UserProjectAssignmentDto>
     * @throws KinetiException
     */
    public function listProjectMembers(string $projectId, array $options = []): array
    {
        $path = sprintf('/api/v1/admin/projects/%s/members', $this->encodePathSegment($projectId, 'Project ID'));
        $requestOptions = [];
        if (!empty($options)) {
            $requestOptions['query'] = $options;
        }

        $response = $this->request('GET', $path, $requestOptions);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(
            static fn (array $item): UserProjectAssignmentDto => UserProjectAssignmentDto::fromArray($item, $projectId),
            $items
        );
    }

    /**
     * Assign a user to a project.
     *
     * @param array<string, mixed> $payload
     * @throws ConflictException When assignment already exists (HTTP 409).
     * @throws ValidationException When payload fails validation (HTTP 422).
     * @throws KinetiException
     */
    public function assignProjectMember(string $projectId, array $payload): UserProjectAssignmentDto
    {
        $path = sprintf('/api/v1/admin/projects/%s/members', $this->encodePathSegment($projectId, 'Project ID'));

        $payload = $this->normalizePayloadKeys($payload, ['userId' => 'user_id']);

        $response = $this->request('POST', $path, [
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return UserProjectAssignmentDto::fromArray($data, $projectId);
    }

    /**
     * Remove a user from a project.
     *
     * @throws KinetiException
     */
    public function removeProjectMember(string $projectId, string $userId): void
    {
        $path = sprintf(
            '/api/v1/admin/projects/%s/members/%s',
            $this->encodePathSegment($projectId, 'Project ID'),
            $this->encodePathSegment($userId, 'User ID')
        );
        $this->request('DELETE', $path);
    }

    /**
     * Request a password reset or invitation email.
     *
     * @throws ValidationException When email is invalid or unprocessable (HTTP 422).
     * @throws KinetiException
     */
    public function requestPasswordReset(string $email): void
    {
        $this->request('POST', '/api/v1/admin/password-reset-requests', [
            'json' => [
                'email' => $email,
            ],
        ], authenticated: false);
    }

    /**
     * Reset a user's password using a reset or invitation token.
     *
     * @throws ValidationException When token is invalid/expired or password does not satisfy validation rules (HTTP 422).
     * @throws KinetiException
     */
    public function resetPassword(#[\SensitiveParameter] string $token, #[\SensitiveParameter] string $newPassword): void
    {
        $this->request('POST', '/api/v1/admin/password-resets', [
            'json' => [
                'token' => $token,
                'password' => $newPassword,
            ],
        ], authenticated: false);
    }

    /**
     * Get the currently authenticated user's profile.
     *
     * @throws KinetiException
     */
    public function getMe(): UserDto
    {
        $response = $this->request('GET', '/api/v1/admin/me');

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return UserDto::fromArray($data);
    }

    /**
     * Update the currently authenticated user's profile.
     *
     * Note: Updating `email` requires providing `current_password` (or `currentPassword`) in $payload.
     *
     * @param array<string, mixed> $payload
     * @throws ConflictException When email already exists (HTTP 409).
     * @throws ValidationException When payload fails validation (HTTP 422).
     * @throws KinetiException
     */
    public function updateMe(
        #[\SensitiveParameter]
        array $payload
    ): UserDto {
        $payload = $this->normalizePayloadKeys($payload, ['currentPassword' => 'current_password']);

        $response = $this->request(
            'PATCH',
            '/api/v1/admin/me',
            [
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => $payload,
            ]
        );

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return UserDto::fromArray($data);
    }

    /**
     * Update the currently authenticated user's password.
     *
     * @throws ValidationException When current password is incorrect or new password does not satisfy validation rules (HTTP 422).
     * @throws KinetiException
     */
    public function updateMyPassword(
        #[\SensitiveParameter] string $currentPassword,
        #[\SensitiveParameter] string $newPassword
    ): void {
        $this->request(
            'PATCH',
            '/api/v1/admin/me/password',
            [
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'currentPassword' => $currentPassword,
                    'newPassword' => $newPassword,
                ],
            ]
        );
    }

    /**
     * @param array<string, mixed> $options
     * @throws KinetiException
     */
    private function request(
        string $method,
        string $path,
        array $options = [],
        bool $authenticated = true
    ): TransportResponseInterface {
        $transport = $authenticated ? $this->transport : $this->unauthenticatedTransport();

        try {
            $response = $transport->request($method, $path, $options);
            $this->lastRateLimitInfo = $transport->getLastRateLimitInfo() ?? RateLimitInfoDto::fromHeaders($response->getHeaders());

            return $response;
        } catch (\Throwable $e) {
            if ($transport->getLastRateLimitInfo() !== null) {
                $this->lastRateLimitInfo = $transport->getLastRateLimitInfo();
            }
            throw $e;
        }
    }

    private function unauthenticatedTransport(): TransportInterface
    {
        return $this->transport->withAuthHeaderValue('');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $keyMap Map from legacy/camelCase key to snake_case key
     * @return array<string, mixed>
     */
    private function normalizePayloadKeys(array $payload, array $keyMap): array
    {
        foreach ($keyMap as $sourceKey => $targetKey) {
            if (array_key_exists($sourceKey, $payload)) {
                if (!array_key_exists($targetKey, $payload)) {
                    $payload[$targetKey] = $payload[$sourceKey];
                }
                unset($payload[$sourceKey]);
            }
        }

        return $payload;
    }
}
