# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
- Prevented webhook replay protection downgrade in `WebhookVerifier::verify()` by requiring `allowLegacy: true` to accept non-`v1=` legacy signatures (`sha256={hmac}` or raw hex), and enforced strict 64-character hexadecimal digest validation across both modern and legacy verification paths (PHPSDK-56).
- Implemented `__debugInfo(): array` across secret-bearing classes (`AdminClient`, `AnalyticsClient`, `HttpTransport`, `Psr18Transport`, `SymfonyTransport`, `AuthTokenDto`, `ApiKeyCreatedDto`) to redact sensitive credentials (`jwtToken`, `authHeaderValue`, `token`, `refreshToken`) from `print_r()`, `var_dump()`, and debug output (PHPSDK-56).

### Added
- Added `rotateProjectWebhookSecret(string $projectId): WebhookSecretDto` to `AdminClient` and `AdminClientInterface` (`POST /api/v1/admin/projects/{id}/webhook-secret/rotate`) with URL path parameter encoding and RFC 9457 error handling (PHPSDK-66).
- Added `WebhookSecretDto` (`src/Dto/WebhookSecretDto.php`) with `#[\SensitiveParameter]` protection and redacted `__debugInfo()` masking (PHPSDK-66).
- Added `WebhookVerifier::HEADER_DELIVERY_ID = 'Kineti-Delivery-Id'` constant for webhook delivery deduplication (PHPSDK-66).
- Added dual/multi-secret verification support (`string|array $secret`) to `WebhookVerifier::verify()` for zero-downtime secret rotation rollouts (PHPSDK-66).
- Added `AdminClient::logout(): void` and `AdminClientInterface::logout(): void` (`POST /api/v1/admin/logout`) for backend session revocation (PHPSDK-69).
- Added `allowedAssertedRoles` property (`list<string>|null`) to `ApiKeyDto` and `ApiKeyCreatedDto`, with support in constructor, `fromArray()`, and `toArray()` (PHPSDK-68).
- Added `defaultDocumentVisibility` property (`?string`) and `getDefaultDocumentVisibility(): ?string` getter to `ProjectDto`, with support in constructor, `fromArray()`, and `toArray()` (PHPSDK-68).
- Added optional `allowedAssertedRoles` parameter to `AdminClient::createApiKey()` and `AdminClientInterface::createApiKey()`, supporting key normalization (`allowedAssertedRoles` to `allowed_asserted_roles`) and payload inclusion when specified (PHPSDK-68).
- Added optional `defaultDocumentVisibility` parameter to `AdminClient::createProject()` and `AdminClientInterface::createProject()`, and supported payload normalization for `defaultDocumentVisibility` across `createProject()` and `updateProject()` (PHPSDK-68).
- Documented role assertion semantics and trust model in `SearchQueryDto` and `SearchFilterDto` docblocks (PHPSDK-68).
- Added `make coverage` target and `composer test:coverage` script generating text code coverage via `pcov` (PHPSDK-60).
- Added `KinetiClient::withApiKey(#[\SensitiveParameter] string $apiKey): static` and method signature to `KinetiClientInterface` (PHPSDK-59).
- Added `KinetiClient::MAX_SEARCH_OFFSET = 1000` constant and replaced magic numbers in `searchAll()` (PHPSDK-59).
- Added named static constructors `withApiKey()` and `withHeader()` to `HttpTransport`, `Psr18Transport`, and `SymfonyTransport` (PHPSDK-59).
- Added `forceReanalysis` property and `withForceReanalysis()` method to `VisionOptionsDto` (PHPSDK-59).
- Added `rerank` property and `withRerank()` method to `SearchQueryDto` (PHPSDK-59).
- Added `JobDto::isTerminal(): bool` and `JobDto::isSucceeded(): bool` status helper methods (PHPSDK-58).
- Added `SseParser` buffer cap with `DEFAULT_MAX_BUFFER_BYTES` (1 MB) and configurable constructor parameter, throwing `TransportException` when exceeded (PHPSDK-57).
- Added `Psr18Transport::DEFAULT_MAX_RETRY_DELAY_SECONDS` (60.0s) and support for `max_retry_delay` option (PHPSDK-57).
- Added `JobType` enum (`vision_single`, `vision_batch`, `document_ingest`) with domain helpers `isVision()`, `isSingleVision()`, `isDocument()`, and `isBatch()` (PHPSDK-55).
- Added `JobDto::getJobType(): ?JobType` helper method and supported `JobType` instances in constructor and `fromArray()` (PHPSDK-55).
- Added `deleteOrganization(string $id): void` to `AdminClient` and `AdminClientInterface` (`DELETE /api/v1/admin/organizations/{id}`) for parity with the backend organization soft-deletion endpoint (PHPSDK-52).

### Changed
- Removed outdated claim of automatic fallback to legacy `X-Kineti-Signature` in `README.md`, documented multi-secret webhook verification and `Kineti-Delivery-Id` deduplication (PHPSDK-66).
- Documented RAG scope requirement (`rag` or `all` scope required, rejecting `search`-only keys with HTTP 403) on `KinetiClientInterface::searchStream()` and `KinetiClient::searchStream()`, documented valid `$scope` values on `AdminClientInterface::createApiKey()` and `AdminClient::createApiKey()`, and verified HTTP 403 `AuthorizationException` handling during stream iteration (PHPSDK-67).
- Removed hardcoded version from `composer.json` and aligned `make release` with Packagist VCS tag-based release practices without mutating repository files (PHPSDK-60).
- Upgraded CI `quality-checks` in `ci.yml` and `release.yml` to PHP 8.4, updated Composer cache hashing to `composer.lock`, added `composer check-platform-reqs`, and enforced `composer validate --strict` (PHPSDK-60).
- Updated `README.md` and `CONTRIBUTING.md` to reflect PHPStan Level 9 enforcement and clarified PSR-18 client timeout configuration (PHPSDK-60).
- Preserved existing authentication headers on injected `TransportInterface` instances when `$apiKey` or `$jwtToken` is empty across `KinetiClient`, `AdminClient`, and `AnalyticsClient` constructors (PHPSDK-59).
- Removed unused private property `$apiHost` from `AnalyticsClient` (PHPSDK-59).
- Added randomized jitter (0–250ms) to `Psr18Transport` exponential backoff calculations and capped retry delay by `max_retry_delay` (PHPSDK-57).
- Standardized URI path parameter encoding across `AdminClient` and `KinetiClient` to use RFC 3986 `rawurlencode()` (spaces encoded as `%20` instead of `+`) and enforce fast-fail validation rejecting null, empty, or whitespace-only IDs with `\InvalidArgumentException` across all 19 resource methods (PHPSDK-52).

### Fixed
- Fixed boolean string hydration in `SearchQueryDto::fromArray()` and `VisionOptionsDto::fromArray()` where `'false'` and `'0'` strings were coerced to `true` (PHPSDK-59).
- Fixed array validation in `AdminClient::register()` to ensure non-empty `org_name` and `email` throw `\InvalidArgumentException` matching scalar and DTO input validations (PHPSDK-59).
- Fixed ambiguous constructor overload resolution in `HttpTransport`, `Psr18Transport`, and `SymfonyTransport` when standard header names (`Authorization` or `X-Kineti-Key`) are passed as the second argument with null value (PHPSDK-59).
- Resolved status trap where `JobDto::isCompleted()` incorrectly returned `true` for failed jobs; `isCompleted()` now strictly checks `JobStatus::Completed` while `isTerminal()` checks for terminal state (`Completed` or `Failed`) (PHPSDK-58).
- Refactored `KinetiClient::waitForJob()` polling logic to poll immediately, return immediately on terminal jobs even with zero timeout, clamp sleep intervals to remaining timeout budget, and advance simulated elapsed microseconds when custom `$sleeper` callbacks are provided without spinning on wall-clock time (PHPSDK-58).
- Fixed `SseParser` false-positives by allowing valid JSON chunks with text starting with `[Error:` (e.g. LLM tokens citing logs) to be yielded as `RagStreamChunkDto` (PHPSDK-57).
- Unified `SymfonyTransportResponse::toArray()` to return `[]` on empty or whitespace-only response bodies matching `Psr18TransportResponse` (PHPSDK-57).
- Fixed potential temporary stream resource leak in `Psr18Transport` by ensuring temporary streams created from `\Traversable` bodies are safely closed upon write or iteration exceptions (PHPSDK-57).
- Guarded `JobDto::toVisionResponse()` and `JobDto::toDocumentResponse()` against accidental cross-hydration between mismatched job types and un-typed payloads (PHPSDK-55).
- Fixed bearer token clearing in `AdminClient::withToken('')` and `AnalyticsClient::withToken('')` by delegating directly to `$this->transport->withAuthHeaderValue('')`, ensuring the `Authorization` header is omitted on subsequent requests across all transport types without triggering redundant client discovery or transport recreation (PHPSDK-52).
- Fixed `createTransport()` in `AdminClient` and `AnalyticsClient` when passing a custom `TransportInterface` with an empty token so the auth header is properly cleared rather than returning the existing transport instance untouched (PHPSDK-52).

## [1.0.11] - 2026-10-16

### Security
- Sanitized multipart header filenames and part names against CRLF injection, null bytes, and quote breakouts in `KinetiClient::createMultipartStreamPayload()` (PHPSDK-50).
- Added `#[\SensitiveParameter]` attribute to all credential, token, secret, and password parameters across `KinetiClient`, `AdminClient`, `AnalyticsClient`, client interfaces, DTOs, and transport implementations to prevent sensitive values from leaking into stack traces (PHPSDK-50).

### Added
- Added `hasMore` (`bool`) and `nextOffset` (`?int`) properties and serialization/deserialization support to `SearchResponseDto` along with `hasMore()`, `getNextOffset()`, and `isHasMorePresent()` helper methods (PHPSDK-46).

### Changed
- Refactored `KinetiClient::searchAll()` to paginate via `offset` instead of `page` following backend top-K semantics, omitting `page` from outgoing requests, enforcing strictly forward offset progression to prevent infinite loops, and halting at the backend boundary `offset <= 1000` (PHPSDK-46).
- Removed `$password` parameter from `AdminClient::register()` and `AdminClientInterface::register()`. Registration requests now send `{org_name, email}` without password to support backend pre-account takeover protection (PHPSDK-47).
- Updated `AdminClient::verifyEmail()` and `AdminClientInterface::verifyEmail()` signature to `verifyEmail(string $token, string $password): AuthTokenDto`. Now sends `{token, password}` to `POST /api/v1/admin/verify-email` and returns the issued JWT authentication token as `AuthTokenDto` (PHPSDK-47).
- Adapted `RegisterResponseDto` to reflect uniform `202 Accepted` response with `message` and `email` properties (PHPSDK-47).

### Deprecated
- Marked `SearchQueryDto::$page`, `withPage()`, `setPage()`, and `getPage()` as deprecated in favor of `withOffset()`, `setOffset()`, and `getOffset()` (PHPSDK-46).

### Removed
- Removed `password` parameter and property from `RegisterDto` (PHPSDK-47).
- Removed `organization` and `user` properties and getters (`getOrganizationId()`, `getOrganizationName()`, `getBillingTier()`, `getUserId()`, `getUserEmail()`, `getUserRoles()`) from `RegisterResponseDto` as the backend no longer returns entity identifiers on registration (PHPSDK-47).

## [1.0.10] - 2026-10-03

### Added
- Added optional `$apiKeyId` parameter to `AdminClient::getUsage()` and `AdminClientInterface::getUsage()` to support API key filtering, with query normalization for positional args, named args, and options array (`api_key_id` / `apiKeyId`) (PHPSDK-43).
- Added optional `tokensConsumedToday` and `requestsToday` properties with non-negative constraints to `ApiKeyDto` and `ApiKeyCreatedDto` (PHPSDK-43).
- Added `ExtractsTokenUsageTrait` providing standardized token usage extraction from `tokens_consumed`, `tokensConsumed`, or nested/scalar `usage` payloads across response DTOs (PHPSDK-43, PHPSDK-44).
- Added optional `tokensConsumed` property and serialization support to `SearchResponseDto` and `RagSynthesisDto` (PHPSDK-43).
- Added optional `tokensConsumed` property and serialization support to `DocumentResponseDto` (PHPSDK-44).
- Added optional `tokensConsumed` property and serialization support to `JobDto`, extracting from top-level job payload or nested `results` array (PHPSDK-44).
- Added optional `tokensConsumed` property and serialization support to `VisionResponseDto` (PHPSDK-44).
- Added optional `tokensConsumed` property to `RagStreamChunkDto`, extracting from chunk payload or nested `metadata`, and updated `create()` factory method (PHPSDK-44).

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
