# KinetiStack PHP SDK

[![Latest Stable Version](https://img.shields.io/packagist/v/kinetistack-io/php-sdk.svg?style=flat-square)](https://packagist.org/packages/kinetistack-io/php-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/kinetistack-io/php-sdk.svg?style=flat-square)](https://packagist.org/packages/kinetistack-io/php-sdk)
[![PHP Version](https://img.shields.io/packagist/dependency-v/kinetistack-io/php-sdk/php.svg?style=flat-square)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![CI Status](https://github.com/kinetistack-io/php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/kinetistack-io/php-sdk/actions/workflows/ci.yml)

Framework-Agnostic PHP Client Library for KinetiStack API.


## Installation

```bash
composer require kinetistack-io/php-sdk
```

## Usage

### Initialization

```php
use KinetiStack\Sdk\KinetiClient;

// Basic initialization
$client = new KinetiClient(
    'https://api.kinetistack.io', // Your API host
    'sk_test_12345'               // Your project API key
);

// Advanced initialization with custom options and automatic retries
$client = new KinetiClient(
    apiHost: 'https://api.kinetistack.io',
    apiKey: 'sk_test_12345',
    httpClient: null,             // Optional: PSR-18 ClientInterface (e.g. Guzzle), Symfony HttpClientInterface, or auto-discovered
    options: [
        'timeout' => 30.0,        // Request timeout in seconds
        'max_retries' => 3,       // Automatically retry transient HTTP 429 and 503 errors
    ]
);
```

#### HTTP Client Flexibility (PSR-18 & Symfony)

The SDK is strictly framework-agnostic. It does not force a concrete HTTP client implementation:
- **Auto-Discovery**: If `httpClient` is omitted or `null`, `php-http/discovery` automatically discovers any installed PSR-18 client (such as Guzzle) or Symfony's HTTP Client.
- **PSR-18 (Guzzle, etc.)**: Inject any `Psr\Http\Client\ClientInterface` instance directly (e.g. `new KinetiClient($host, $key, $guzzleClient)`).
- **Symfony HTTP Client**: Existing Symfony projects can inject `Symfony\Contracts\HttpClient\HttpClientInterface` for full backward compatibility.

#### Transient Error Retries & Backoff

When `max_retries` is configured in `$options`, the SDK automatically retries transient HTTP errors:
- **HTTP 429 (Too Many Requests)**: Automatically delays subsequent retries by respecting the server's `Retry-After` response header (or falls back to exponential backoff).
- **HTTP 503 (Service Unavailable)**: Automatically retries using exponential backoff.
- **Non-transient errors** (such as HTTP 400, 401, 403, 404, 422, or 500) fail immediately without retrying.

If all retry attempts are exhausted, the corresponding typed exception (e.g., `RateLimitException` or `ServiceUnavailableException`) is thrown.

### Synchronous Image Analysis

```php
// From a URL
$response = $client->analyzeImage('https://example.com/image.jpg', [
    'page_title' => 'Renewable Energy'
]);
echo $response->altText;

// From a stream resource (memory-efficient for large files & remote stream wrappers)
$stream = fopen('/path/to/image.jpg', 'rb');
$response = $client->analyzeImageStream($stream, 'image.jpg');
fclose($stream);

// From a local file (uses streams internally)
$response = $client->analyzeImageBinary('/path/to/image.jpg');

// From binary string content
$response = $client->analyzeImageContent($binaryData, 'image.jpg');
```

### Batch Processing

```php
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Exception\JobTimeoutException;
use KinetiStack\Sdk\KinetiClient;

// Submit a batch of images (URL or Base64 / Data URI)
$batch = $client->submitBatchJob([
    new ImageInputDto('media:1', 'https://example.com/1.jpg'),
    new ImageInputDto('media:2', imageBase64: 'data:image/jpeg;base64,...'),
]);

echo $batch->jobId; // E.g., '123e4567-e89b-12d3-a456-426614174000'

// Poll for completion with exponential backoff & jitter
try {
    $completedBatch = $client->waitForJob(
        jobId: $batch->jobId,
        timeoutSeconds: KinetiClient::DEFAULT_TIMEOUT_SECONDS,                  // Default: 60s
        pollIntervalSeconds: KinetiClient::DEFAULT_POLL_INTERVAL_SECONDS,         // Default: 2s (initial interval)
        onProgress: function (JobDto $job): void {
            echo "Current status: {$job->status->value}\n";
        },
        maxPollIntervalSeconds: KinetiClient::DEFAULT_MAX_POLL_INTERVAL_SECONDS // Default: 10s (capped interval)
    );
    
    foreach ($completedBatch->results as $result) {
        echo $result->externalId . ': ' . $result->altText . "\n";
    }
} catch (JobTimeoutException $e) {
    echo "Job timed out. Last known status: " . $e->latestJob->status->value;
}
```

#### Exponential Backoff & Polling Constants

`waitForJob()` implements an adaptive polling strategy with jitter:
- **Exponential progression**: Polling begins at `$pollIntervalSeconds` (default: `2s`) and doubles on each subsequent check (e.g., `2s -> 4s -> 8s -> 10s`).
- **Interval cap**: The sleep interval between checks never exceeds `$maxPollIntervalSeconds` (default: `10s`).
- **Randomized jitter**: A randomized jitter between 0 and 500ms is added to every sleep duration to prevent synchronized polling spikes across distributed workers.
- **Progress monitoring**: The optional `$onProgress` callback receives the updated `JobDto` on each poll check.

Default constants exposed on `KinetiClient`:
- `KinetiClient::DEFAULT_POLL_INTERVAL_SECONDS` = `2`
- `KinetiClient::DEFAULT_MAX_POLL_INTERVAL_SECONDS` = `10`
- `KinetiClient::DEFAULT_TIMEOUT_SECONDS` = `60`

### Semantic Search & RAG Streaming

Execute semantic vector searches or consume progressive Server-Sent Events (SSE) streaming responses from the RAG engine:

```php
use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;

// 1. Standard search with optional synthesized answer
$query = SearchQueryDto::create('How does solar net metering work?')
    ->withLimit(5)
    ->withSynthesis(true);

$response = $client->search($query);
echo "Found {$response->total} results.\n";
if ($response->hasSynthesis()) {
    echo "Synthesized Answer: " . $response->synthesis->answer . "\n";
}

// 2. Streamed RAG synthesis via PHP generator (reduces perceived latency)
$streamQuery = SearchQueryDto::create('Explain commercial solar financing options')
    ->withLimit(5)
    ->withStream(true);

/** @var \Generator<int, RagStreamChunkDto> $stream */
$stream = $client->searchStream($streamQuery);

foreach ($stream as $chunk) {
    echo $chunk->text; // Streams partial tokens/words in real-time
    flush();
}
```

### Webhook Verification

Verify incoming HMAC-SHA256 signatures for webhook events (e.g. from the `X-Kineti-Signature` header):

```php
use KinetiStack\Sdk\WebhookVerifier;

$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_KINETI_SIGNATURE'] ?? '';
$webhookSecret = 'whsec_your_secret_key';

$isValid = WebhookVerifier::verify($payload, $signatureHeader, $webhookSecret);

if (!$isValid) {
    http_response_code(401);
    exit('Invalid signature');
}
```

### Error Handling

The SDK maps HTTP error responses to typed exceptions (RFC 9457 compliant):

```php
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\NotFoundException;
use KinetiStack\Sdk\Exception\PayloadTooLargeException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\ValidationException;

try {
    $client->analyzeImage('invalid-url');
} catch (ValidationException $e) {
    print_r($e->violations);
} catch (RateLimitException $e) {
    // Thrown if max_retries is not configured or all retry attempts are exhausted
    echo "Rate limited. Retry after: " . $e->retryAfter . " seconds.";
} catch (ServiceUnavailableException $e) {
    // Thrown if max_retries is not configured or all retry attempts are exhausted
    echo "Service temporarily unavailable (503): " . $e->getMessage();
} catch (KinetiException $e) {
    echo "API Error: " . $e->getMessage();
}
```

> **Note on Transient Errors**: When `max_retries` is specified in client options (e.g., `['max_retries' => 3]`), transient HTTP errors (`429 Too Many Requests` respecting `Retry-After` header, and `503 Service Unavailable`) are automatically retried with exponential backoff before throwing `RateLimitException` or `ServiceUnavailableException`.

### Admin API (`AdminClient`)

The SDK provides `AdminClient` as a dedicated sibling client for administrative operations under `/api/v1/admin/*`, authenticated via LexikJWT Bearer tokens (`Authorization: Bearer <jwt>`).

#### Initialization & Authentication

```php
use KinetiStack\Sdk\AdminClient;

// 1. Initial login without token
$admin = new AdminClient('https://api.kinetistack.io');
$auth = $admin->login('admin@agency.com', 'SuperSecret123!');

// 2. Obtain an immutable client instance with the JWT token
$authenticatedAdmin = $admin->withToken($auth->token);

// Or initialize directly if you already hold a valid token
$admin = new AdminClient('https://api.kinetistack.io', $jwtToken);
```

#### Organizations & Projects

```php
// Organizations
$org = $admin->createOrganization('Acme Agency', 'standard');
$orgList = $admin->listOrganizations();
$currentOrg = $admin->getOrganization($org->id);
$updatedOrg = $admin->updateOrganization($org->id, ['name' => 'Acme Global Agency']);

// Projects
$project = $admin->createProject('Client Portal', 'portal.example.com', 'https://webhook.example.com/events');
$projects = $admin->listProjects();
$projectDetails = $admin->getProject($project->id);
$admin->updateProject($project->id, ['settings' => ['theme' => 'dark']]);
$admin->deleteProject($project->id); // Soft-delete and revokes associated API keys
```

#### API Key Management

```php
// Create API key (returns ApiKeyCreatedDto with plaintext token)
$newKey = $admin->createApiKey($project->id, 'Production Drupal Key', 'all', rateLimitPerMinute: 120);
echo $newKey->token; // Secret plaintext token — save this now!
echo $newKey->tokenSuffix; // e.g. '1234'

// List API keys (returns ApiKeyDto[] without plaintext token)
$keys = $admin->listApiKeys($project->id);

// Rotate API key (old key continues working during grace period)
$rotated = $admin->rotateApiKey($project->id, $newKey->id);
echo $rotated->token; // New secret token

// Revoke API key
$admin->revokeApiKey($project->id, $newKey->id);
```

#### Usage & Analytics

```php
use KinetiStack\Sdk\AnalyticsClient;
use KinetiStack\Sdk\Enum\AnalyticsGrouping;

// Summary report across services
$usage = $admin->getUsage(from: '2026-09-01', to: '2026-09-05', projectId: $project->id);
foreach ($usage as $summary) {
    echo "{$summary->projectName} - {$summary->service}: {$summary->totalTokens} tokens ({$summary->requestCount} requests)\n";
}

// Time-bucketed analytics with typed grouping (DAY, WEEK, MONTH) via AdminClient
$dailyAnalytics = $admin->getAnalytics(grouping: AnalyticsGrouping::DAY, from: '2026-09-01', to: '2026-09-07');
$weeklyAnalytics = $admin->getAnalytics(grouping: AnalyticsGrouping::WEEK, from: '2026-09-01', to: '2026-09-30');
$monthlyAnalytics = $admin->getAnalytics(grouping: AnalyticsGrouping::MONTH);
print_r($weeklyAnalytics->data);

// Or via dedicated AnalyticsClient
$analyticsClient = $admin->analytics();
// Or standalone: new AnalyticsClient('https://api.kinetistack.io', $jwtToken)
$trends = $analyticsClient->getAnalytics(AnalyticsGrouping::WEEK);
```

## Development

No local PHP or Composer installation is required. Everything runs in one-off Docker containers via `docker compose` and `make`.

### Prerequisites

- Docker & Docker Compose
- `make`

### Common Commands

```bash
# Install dependencies
make install

# Run PHPUnit tests
make test

# Run tests across PHP matrix (8.1, 8.2, 8.3, 8.4)
make test-all

# Static analysis (PHPStan)
make phpstan

# Code style fixer (PHP CS Fixer)
make cs-fix
make cs-check

# Run all checks (cs-check, phpstan, test)
make check

# Run arbitrary composer command
make composer cmd="require symfony/yaml"

# Prepare and publish a release (bumps composer.json, runs checks, commits, tags, pushes)
make release VERSION=1.2.4

# Open container shell
make shell
```

## CI/CD & Release Workflow

The SDK uses automated CI/CD workflows for testing, static analysis, and releases:

- **CI Pipeline (`.github/workflows/ci.yml`)**: Runs on pushes and pull requests targeting `main`. Executes test matrices across PHP 8.1, 8.2, 8.3, and 8.4, followed by PHP CS Fixer and PHPStan static analysis.
- **Release Pipeline (`.github/workflows/release.yml`)**: Triggered automatically when a version tag matching `v*.*.*` (e.g., `v1.0.0`) is pushed to the repository.
  1. **Validation (`validate`)**: Checks platform requirements via `composer check-platform-reqs` and runs PHPUnit across PHP 8.1, 8.2, 8.3, and 8.4.
  2. **Quality Checks (`quality-checks`)**: Ensures code conforms to PSR-12 and passes PHPStan Level 8 static analysis.
  3. **Release (`release`)**: Requires both `validate` and `quality-checks` to pass. Publishes a GitHub Release (with auto-generated release notes and automatic pre-release detection) and triggers a synchronization webhook to the Packagist API via authenticated cURL.

### Required Repository Secrets

The release workflow requires the following repository secrets to be configured in GitHub:

| Secret Name | Description | Required By |
|:---|:---|:---|
| `PACKAGIST_USER` | Packagist account username authorized to manage `kinetistack-io/php-sdk` | Packagist API update hook |
| `PACKAGIST_TOKEN` | Packagist API token with package update permissions | Packagist API update hook |

The workflow also requires GitHub Actions default `GITHUB_TOKEN` with `contents: write` permissions (configured automatically in `release.yml`) to publish releases. Fork runs automatically skip the release job if secrets are not configured.

## Contributing

Contributions are welcome! Please refer to **[CONTRIBUTING.md](CONTRIBUTING.md)** for detailed local development, testing, and release guidelines.

Before opening a pull request, ensure that:

1. Code adheres to **PSR-12** standards (`make cs-check` / `make cs-fix`).
2. Static analysis passes at **PHPStan Level 8** (`make phpstan`).
3. Unit tests pass across all supported PHP versions (`make test-all`).
4. All checks pass locally via `make check` before opening a pull request.

## Security

If you discover a security vulnerability within this SDK, please send an email to [security@kinetistack.io](mailto:security@kinetistack.io) or use GitHub Private Vulnerability Reporting. All security vulnerabilities will be promptly addressed.

## License

The KinetiStack PHP SDK is open-sourced software licensed under the [MIT license](LICENSE).