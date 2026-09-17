<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

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
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\ConflictException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\Transport\HttpTransport;
use KinetiStack\Sdk\Transport\TransportInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdminClient implements AdminClientInterface
{
    private TransportInterface $transport;

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $httpClient
     * @param array<string, mixed> $options Default HTTP options (e.g., timeout, headers)
     */
    public function __construct(
        private readonly string $apiHost,
        private string $jwtToken = '',
        private readonly HttpClientInterface|ClientInterface|TransportInterface|null $httpClient = null,
        private readonly array $options = []
    ) {
        $this->transport = $this->createTransport(
            $this->apiHost,
            $this->jwtToken,
            $this->httpClient,
            $this->options
        );
    }

    /**
     * Return a new immutable instance configured with the specified JWT token.
     */
    public function withToken(string $jwtToken): static
    {
        $clone = clone $this;
        $clone->jwtToken = $jwtToken;
        $authHeaderValue = $jwtToken !== '' ? 'Bearer ' . $jwtToken : '';

        if ($this->httpClient instanceof TransportInterface) {
            $clone->transport = $this->httpClient->withAuthHeaderValue($authHeaderValue);
        } else {
            $clone->transport = $this->createTransport(
                $this->apiHost,
                $jwtToken,
                $this->httpClient,
                $this->options
            );
        }

        return $clone;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
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
     * Authenticate an admin user and retrieve a JWT token.
     *
     * @throws KinetiException
     */
    public function login(string $email, string $password): AuthTokenDto
    {
        $response = $this->transport->request('POST', '/api/v1/admin/login', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

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
        $response = $this->transport->request('POST', '/api/v1/admin/token/refresh');

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return AuthTokenDto::fromArray($data);
    }

    /**
     * Register a new organization and initial administrator user.
     *
     * Note: This endpoint is unauthenticated and must be called on an AdminClient
     * initialized without a JWT token (similar to login()).
     *
     * @param RegisterDto|array<string, mixed>|string $orgNameOrData Organization name, RegisterDto, or associative payload array containing 'org_name', 'email', and 'password'.
     * @param string|null $email Admin user email (required when $orgNameOrData is a string).
     * @param string|null $password Admin user password (required when $orgNameOrData is a string).
     *                              @sensitive $password
     * @return RegisterResponseDto
     * @throws \InvalidArgumentException When scalar arguments are empty.
     * @throws ConflictException When the email address is already registered (HTTP 409).
     * @throws ValidationException When payload fails validation (HTTP 422).
     * @throws AuthorizationException When registration is closed or not allowed (HTTP 403).
     * @throws KinetiException
     */
    public function register(
        RegisterDto|array|string $orgNameOrData,
        ?string $email = null,
        ?string $password = null,
    ): RegisterResponseDto {
        if ($orgNameOrData instanceof RegisterDto) {
            $payload = $orgNameOrData->toArray();
        } elseif (is_array($orgNameOrData)) {
            $payload = $orgNameOrData;
        } else {
            $dto = new RegisterDto($orgNameOrData, (string) $email, (string) $password);
            $payload = $dto->toArray();
        }

        $response = $this->transport->request('POST', '/api/v1/admin/register', [
            'json' => $payload,
        ]);

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
        $response = $this->transport->request('GET', '/api/v1/admin/registration-status');

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return RegistrationStatusDto::fromArray($data);
    }

    /**
     * Create a new organization.
     *
     * @param array<string, mixed>|string $name
     * @throws KinetiException
     */
    public function createOrganization(array|string $name, ?string $billingTier = null): OrganizationDto
    {
        if (is_array($name)) {
            $payload = $name;
        } else {
            $payload = ['name' => $name];
            if ($billingTier !== null) {
                $payload['billing_tier'] = $billingTier;
            }
        }

        $response = $this->transport->request('POST', '/api/v1/admin/organizations', [
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return OrganizationDto::fromArray($data);
    }

    /**
     * List organizations.
     *
     * @param array<string, mixed> $options
     * @return list<OrganizationDto>
     * @throws KinetiException
     */
    public function listOrganizations(array $options = []): array
    {
        $requestOptions = [];
        if (!empty($options)) {
            $requestOptions['query'] = $options;
        }

        $response = $this->transport->request('GET', '/api/v1/admin/organizations', $requestOptions);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(static fn (array $item): OrganizationDto => OrganizationDto::fromArray($item), $items);
    }

    /**
     * Get details of a single organization.
     *
     * @throws KinetiException
     */
    public function getOrganization(string $id): OrganizationDto
    {
        $response = $this->transport->request('GET', sprintf('/api/v1/admin/organizations/%s', urlencode($id)));

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
        $response = $this->transport->request(
            'PATCH',
            sprintf('/api/v1/admin/organizations/%s', urlencode($id)),
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
     * Create a new project within the authenticated organization.
     *
     * @param array<string, mixed>|string $nameOrData
     * @param array<string, mixed>|null $settings
     * @throws KinetiException
     */
    public function createProject(
        array|string $nameOrData,
        ?string $domain = null,
        ?string $webhookUrl = null,
        ?array $settings = null
    ): ProjectDto {
        if (is_array($nameOrData)) {
            $payload = $nameOrData;
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
        }

        $response = $this->transport->request('POST', '/api/v1/admin/projects', [
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

        $response = $this->transport->request('GET', '/api/v1/admin/projects', $requestOptions);

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
        $response = $this->transport->request('GET', sprintf('/api/v1/admin/projects/%s', urlencode($id)));

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return ProjectDto::fromArray($data);
    }

    /**
     * Update project settings, name, domain, or webhook.
     *
     * @param array<string, mixed> $data
     * @throws KinetiException
     */
    public function updateProject(string $id, array $data): ProjectDto
    {
        $response = $this->transport->request(
            'PATCH',
            sprintf('/api/v1/admin/projects/%s', urlencode($id)),
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
        $this->transport->request('DELETE', sprintf('/api/v1/admin/projects/%s', urlencode($id)));
    }

    /**
     * Create an API key for a project. Returns plaintext token in ApiKeyCreatedDto.
     *
     * @param array<string, mixed>|string $projectOrData Project ID string or full data array
     * @param array<string, mixed>|string $nameOrData
     * @throws KinetiException
     */
    public function createApiKey(
        array|string $projectOrData,
        array|string $nameOrData = [],
        string $scope = 'all',
        ?int $rateLimitPerMinute = null,
        ?string $expiresAt = null
    ): ApiKeyCreatedDto {
        $projectId = '';
        if (is_array($projectOrData)) {
            $projectId = (string) ($projectOrData['project_id'] ?? $projectOrData['projectId'] ?? '');
            $payload = $projectOrData;
            unset($payload['project_id'], $payload['projectId']);
        } elseif (is_array($nameOrData)) {
            $projectId = $projectOrData;
            $payload = $nameOrData;
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
        }

        $path = $projectId !== ''
            ? sprintf('/api/v1/admin/projects/%s/api-keys', urlencode($projectId))
            : '/api/v1/admin/api-keys';

        $response = $this->transport->request('POST', $path, [
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return ApiKeyCreatedDto::fromArray($data);
    }

    /**
     * List API keys for a project (without plaintext token).
     *
     * @param array<string, mixed> $options
     * @return list<ApiKeyDto>
     * @throws KinetiException
     */
    public function listApiKeys(?string $projectId = null, array $options = []): array
    {
        $path = $projectId !== null && $projectId !== ''
            ? sprintf('/api/v1/admin/projects/%s/api-keys', urlencode($projectId))
            : '/api/v1/admin/api-keys';

        $requestOptions = [];
        if (!empty($options)) {
            $requestOptions['query'] = $options;
        }

        $response = $this->transport->request('GET', $path, $requestOptions);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(static fn (array $item): ApiKeyDto => ApiKeyDto::fromArray($item), $items);
    }

    /**
     * Revoke an API key.
     *
     * @throws KinetiException
     */
    public function revokeApiKey(string $projectIdOrKeyId, ?string $keyId = null): void
    {
        if ($keyId !== null) {
            $path = sprintf('/api/v1/admin/projects/%s/api-keys/%s', urlencode($projectIdOrKeyId), urlencode($keyId));
        } else {
            $path = sprintf('/api/v1/admin/api-keys/%s', urlencode($projectIdOrKeyId));
        }

        $this->transport->request('DELETE', $path);
    }

    /**
     * Rotate an existing API key, generating a new raw token with a grace period for the old key.
     *
     * @throws KinetiException
     */
    public function rotateApiKey(string $projectId, string $keyId): ApiKeyCreatedDto
    {
        $path = sprintf('/api/v1/admin/projects/%s/api-keys/%s/rotate', urlencode($projectId), urlencode($keyId));
        $response = $this->transport->request('POST', $path);

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
        ?string $projectId = null
    ): array {
        $query = [];
        if (is_array($fromOrOptions)) {
            $query = $fromOrOptions;
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
        }

        $requestOptions = [];
        if (!empty($query)) {
            $requestOptions['query'] = $query;
        }

        $response = $this->transport->request('GET', '/api/v1/admin/usage', $requestOptions);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(static fn (array $item): UsageSummaryDto => UsageSummaryDto::fromArray($item), $items);
    }

    /**
     * Query aggregated usage analytics buckets.
     *
     * @param array<string, mixed>|string|null $groupByOrOptions
     * @throws KinetiException
     */
    public function getAnalytics(
        array|string|null $groupByOrOptions = 'day',
        ?string $from = null,
        ?string $to = null,
        ?string $projectId = null
    ): AnalyticsDto {
        $query = [];
        if (is_array($groupByOrOptions)) {
            $query = $groupByOrOptions;
        } else {
            if ($groupByOrOptions !== null) {
                $query['group_by'] = $groupByOrOptions;
            }
            if ($from !== null) {
                $query['from'] = $from;
            }
            if ($to !== null) {
                $query['to'] = $to;
            }
            if ($projectId !== null) {
                $query['project_id'] = $projectId;
            }
        }

        $requestOptions = [];
        if (!empty($query)) {
            $requestOptions['query'] = $query;
        }

        $response = $this->transport->request('GET', '/api/v1/admin/analytics', $requestOptions);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return AnalyticsDto::fromArray($data);
    }

    /**
     * List batch image jobs for a project.
     *
     * @param array<string, mixed> $options
     * @return list<BatchJobDto>
     * @throws KinetiException
     */
    public function listProjectJobs(string $projectId, int $page = 1, array $options = []): array
    {
        $path = sprintf('/api/v1/admin/projects/%s/jobs', urlencode($projectId));
        $query = array_merge(['page' => $page], $options);
        $response = $this->transport->request('GET', $path, ['query' => $query]);

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $items = $this->extractCollection($data);

        return array_map(static fn (array $item): BatchJobDto => BatchJobDto::fromArray($item), $items);
    }

    /**
     * Retry a failed batch image job.
     *
     * @throws KinetiException
     */
    public function retryJob(string $jobId): BatchJobDto
    {
        $path = sprintf('/api/v1/admin/jobs/%s/retry', urlencode($jobId));
        $response = $this->transport->request('POST', $path);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return BatchJobDto::fromArray($data);
    }

    /**
     * Get a single batch image job by ID.
     *
     * @throws KinetiException
     */
    public function getJob(string $jobId): BatchJobDto
    {
        $path = sprintf('/api/v1/admin/jobs/%s', urlencode($jobId));
        $response = $this->transport->request('GET', $path);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return BatchJobDto::fromArray($data);
    }

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $httpClient
     * @param array<string, mixed> $options
     */
    private function createTransport(
        string $apiHost,
        string $jwtToken,
        HttpClientInterface|ClientInterface|TransportInterface|null $httpClient,
        array $options
    ): TransportInterface {
        $authHeaderValue = $jwtToken !== '' ? 'Bearer ' . $jwtToken : '';

        if ($httpClient instanceof TransportInterface) {
            return $jwtToken !== '' ? $httpClient->withAuthHeaderValue($authHeaderValue) : $httpClient;
        }

        return new HttpTransport($apiHost, 'Authorization', $authHeaderValue, $httpClient, $options);
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $data
     * @return list<array<string, mixed>>
     */
    private function extractCollection(array $data): array
    {
        if (isset($data['hydra:member']) && is_array($data['hydra:member'])) {
            /** @var list<array<string, mixed>> $items */
            $items = array_values($data['hydra:member']);
            return $items;
        }

        if (isset($data['data']) && is_array($data['data'])) {
            /** @var list<array<string, mixed>> $items */
            $items = array_values($data['data']);
            return $items;
        }

        /** @var list<array<string, mixed>> $items */
        $items = array_values($data);
        return $items;
    }
}
