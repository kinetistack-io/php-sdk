# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.9] - 2026-10-01

### Added
- Support for `dailyTokenQuotaOverride` in `ApiKeyDto` and `AdminClient`.

## [1.0.8] - 2026-10-01

### Added
- Support for `monthlyQuotaCap` in `ProjectDto`, `OrganizationDto`, and `AdminClient` (`createProject`, `updateProject`, `createOrganization`, `updateOrganization`) (PHPSDK-37).
- Added `resendVerificationEmail()` method to `AdminClient` and `AdminClientInterface` (`POST /api/v1/admin/resend-verification`) (PHPSDK-38).
- Added `verifyEmail()` method to `AdminClient` and `AdminClientInterface` (`POST /api/v1/admin/verify-email`) (PHPSDK-39).
- Added `getApiKey()` method to `AdminClient` and `AdminClientInterface` (`GET /api/v1/admin/projects/{projectId}/api-keys/{id}`) (PHPSDK-40).
- Added `ping()` method to `AdminClient` and `AdminClientInterface` (`GET /api/v1/admin/ping`) (PHPSDK-42).

### Deprecated
- Marked `createOrganization()` and `listOrganizations()` as deprecated in `AdminClient` and `AdminClientInterface`. In KinetiStack's single-tenant organization model, organizations are created during registration (`register()`) and retrieved via `getOrganization($id)`. Calling these methods now throws `\BadMethodCallException` to avoid sending invalid requests to non-existent backend endpoints (PHPSDK-41).

### Changed
- Enforced mandatory non-empty `$projectId` validation across `createApiKey()`, `listApiKeys()`, and `revokeApiKey()`, throwing `\InvalidArgumentException('Project ID cannot be empty.')` before dispatching network requests (PHPSDK-41).
- Removed unsupported fallback routes to `/api/v1/admin/api-keys` in `AdminClient::createApiKey()`, `listApiKeys()`, and `revokeApiKey()` in favor of strict project scoping (`/api/v1/admin/projects/{projectId}/api-keys`) (PHPSDK-41).
- Enforced non-empty `$keyId` validation in `AdminClient::revokeApiKey()` and `rotateApiKey()` throwing `\InvalidArgumentException('API key ID cannot be empty.')`. Note: omitting `$keyId` in `revokeApiKey()` was previously allowed via the fallback route; it now throws an exception as required by backend routing. The first parameter name `$projectIdOrKeyId` is retained for backwards compatibility with PHP 8+ named arguments (PHPSDK-41).

## [1.0.7] - 2026-09-22

### Added
- Streaming response consumption for RAG synthesis via `KinetiClient::searchStream()` yielding typed `RagStreamChunkDto` objects from Server-Sent Events (SSE) (PHPSDK-30).
- `RagStreamChunkDto` representing streamed response chunks with citation and metadata support.
- `SseParser` for robust Server-Sent Events parsing across arbitrary chunk boundaries with error detection.
- Streaming support in `TransportInterface::requestStream()`, `Psr18Transport`, and `SymfonyTransport` (`getStreamIterator()`).
- Added `withStream()` and `isStreaming()` to `SearchQueryDto`.
- Typed `AnalyticsGrouping` backed enum (`DAY = 'day'`, `WEEK = 'week'`, `MONTH = 'month'`) for time-bucketed analytics (PHPSDK-27).
- Dedicated `AnalyticsClient` and `AnalyticsClientInterface` for querying aggregated analytics data.
- `AdminClientInterface::analytics()` accessor returning `AnalyticsClientInterface`.

### Changed
- `AdminClient::getAnalytics()` now strictly accepts `AnalyticsGrouping` parameter defaulting to `AnalyticsGrouping::DAY`.

## [1.0.6] - 2026-09-20

### Changed
- Refactored asynchronous job polling to use generic `JobDto` and `JobTimeoutException` across all AI endpoints and background processing tasks (PHPSDK-33).
- Consolidated polling logic into `KinetiClient::waitForJob()` and `KinetiClient::getJob()`.

### Removed
- Removed deprecated `BatchJobDto` and `BatchJobTimeoutException` in favor of `JobDto` and `JobTimeoutException`.
- Removed deprecated `KinetiClient::waitForBatchJob()` and `KinetiClient::getBatchJobStatus()` aliases.

## [1.0.5] - 2026-09-17

### Added
- `AdminClient::getJob()` method for retrieving individual batch image job details (`GET /api/v1/admin/jobs/{jobId}`)

## [1.0.4] - 2026-09-16

### Added
- `AdminClient::listProjectJobs()` method for fetching paginated batch jobs for a project (`GET /api/v1/admin/projects/{projectId}/jobs`)
- `AdminClient::retryJob()` method for resetting and re-dispatching failed batch jobs (`POST /api/v1/admin/jobs/{jobId}/retry`)
- `AdminClientInterface` (`KinetiStack\Sdk\AdminClientInterface` extending `KinetiStack\Sdk\Client\AdminClientInterface`) defining the contract for all `AdminClient` methods to support dependency injection and test mocking

## [1.0.3] - 2026-09-15

### Added
- `AdminClient::refreshToken()` method for renewing administrative JWT session tokens (`POST /api/v1/admin/token/refresh`)

## [1.0.2] - 2026-09-14

### Added
- `AdminClient::register()` method for self-service agency onboarding (`POST /api/v1/admin/register`)
- `RegisterDto` request DTO and `RegisterResponseDto` response DTO
- `ConflictException` for HTTP 409 responses

## [1.0.1] - 2026-09-13

### Added
- Read available modules from the backend

### Updated
- Registration service changed

## [1.0.0] - 2026-09-08

### Added
- **Core Client (`src/KinetiClient.php`)**: Framework-agnostic client for KinetiStack inference, document management, and semantic search.
  - Vision analysis: `analyzeImage()`, `analyzeImageStream()`, `analyzeImageBinary()`, and `analyzeImageContent()` supporting local file paths, streams, raw binary buffers, and Base64 data URIs.
  - Stream-based multipart uploads for efficient memory usage with large media payloads.
  - Connection health and readiness probes: `healthz()` and `readyz()`.
  - Batch job lifecycle management: `submitBatchJob()`, `getBatchJob()`, and `waitForBatchJob()` with exponential backoff and jitter.
  - Document ingestion and management: `ingestDocument()`, `getDocument()`, `listDocuments()`, and `deleteDocument()`.
  - Semantic vector search and RAG querying: `searchDocuments()` and `ragQuery()`.
- **Administrative Client (`src/AdminClient.php`)**: Dedicated sibling client for administrative operations against `/api/v1/admin/*` using JWT Bearer authentication (`Authorization: Bearer <jwt>`).
  - `login(string $email, string $password): AuthTokenDto`
  - `withToken(string $jwtToken): static` for immutable token swapping
  - Organization management: `createOrganization()`, `listOrganizations()`, `getOrganization()`, `updateOrganization()`
  - Project management: `createProject()`, `listProjects()`, `getProject()`, `updateProject()`, `deleteProject()`
  - API Key lifecycle: `createApiKey()` (returning `ApiKeyCreatedDto` with plaintext token), `listApiKeys()`, `revokeApiKey()`, and `rotateApiKey()`
  - Usage and analytics: `getUsage()` returning `UsageSummaryDto[]` and `getAnalytics()` returning `AnalyticsDto`
- **Transport Architecture (`src/Transport/`)**:
  - `SymfonyTransport`: Default production HTTP client using Symfony HttpClient contracts with streaming support.
  - `Psr18Transport`: Full PSR-18 HTTP Client support via `php-http/discovery` auto-discovery (Guzzle, Buzz, etc.).
  - Generalized HTTP transport supporting custom authentication header names and values.
  - Transient error retry policy with exponential backoff for HTTP 429 and 503 responses.
- **DTOs & Enums (`src/Dto/`, `src/Enum/`)**:
  - `ImageInputDto`, `BatchJobDto`, `VisionResponseDto`, `DocumentDto`, `DocumentListDto`, `SearchQueryDto`, `SearchResultDto`, `RagQueryDto`, `RagResponseDto`
  - Admin DTOs: `AuthTokenDto`, `OrganizationDto`, `ProjectDto`, `ApiKeyDto`, `ApiKeyCreatedDto`, `UsageSummaryDto`, `AnalyticsDto`
  - Enums: `JobStatus`, `WebhookStatus`
- **Webhook Security (`src/WebhookVerifier.php`)**:
  - HMAC-SHA256 signature verification with tolerance against clock drift and timing attack prevention.
- **Release & CI Tooling**:
  - Local release automation via `make release VERSION=x.y.z`.
  - Automated CI matrix pipeline testing against PHP 8.1, 8.2, 8.3, and 8.4 with PHPStan Level 8 and PHP CS Fixer.
  - Tag release workflow (`release.yml`) for GitHub Releases and Packagist synchronization.
