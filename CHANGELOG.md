# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
