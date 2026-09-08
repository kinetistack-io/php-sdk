# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **`AdminClient` (`src/AdminClient.php`)**: Dedicated sibling client for administrative operations against `/api/v1/admin/*` using JWT Bearer authentication (`Authorization: Bearer <jwt>`).
  - `login(string $email, string $password): AuthTokenDto`
  - `withToken(string $jwtToken): static` for immutable token swapping
  - `createOrganization(...)` and `listOrganizations(...)`
  - `getOrganization(...)` and `updateOrganization(...)`
  - `createProject(...)`, `listProjects(...)`, `getProject(...)`, `updateProject(...)`, `deleteProject(...)`
  - `createApiKey(...)` returning `ApiKeyCreatedDto` with plaintext `token`
  - `listApiKeys(...)` returning `ApiKeyDto[]` without `token`
  - `revokeApiKey(...)` and `rotateApiKey(...)`
  - `getUsage(...)` returning `UsageSummaryDto[]`
  - `getAnalytics(...)` returning `AnalyticsDto`
- **Admin DTOs (`src/Dto/`)**:
  - `AuthTokenDto`: Admin login response token
  - `OrganizationDto`: Organization entity data
  - `ProjectDto`: Project entity data
  - `ApiKeyDto`: API key metadata without plaintext token
  - `ApiKeyCreatedDto`: API key metadata including plaintext token
  - `UsageSummaryDto`: Token and request usage aggregations
  - `AnalyticsDto`: Multi-service time-bucketed metrics
- **Transport Generalization (`src/Transport/`)**:
  - Generalize `SymfonyTransport`, `Psr18Transport`, and `HttpTransport` to support arbitrary authentication header names (`$authHeaderName`) and values (`$authHeaderValue`) with backward compatibility for legacy signatures.
