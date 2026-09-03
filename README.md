# KinetiStack PHP SDK

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
use KinetiStack\Sdk\Dto\BatchJobDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Exception\BatchJobTimeoutException;
use KinetiStack\Sdk\KinetiClient;

// Submit a batch of images (URL or Base64 / Data URI)
$batch = $client->submitBatchJob([
    new ImageInputDto('media:1', 'https://example.com/1.jpg'),
    new ImageInputDto('media:2', imageBase64: 'data:image/jpeg;base64,...'),
]);

echo $batch->jobId; // E.g., '123e4567-e89b-12d3-a456-426614174000'

// Poll for completion with exponential backoff & jitter
try {
    $completedBatch = $client->waitForBatchJob(
        jobId: $batch->jobId,
        timeoutSeconds: KinetiClient::DEFAULT_TIMEOUT_SECONDS,                  // Default: 60s
        pollIntervalSeconds: KinetiClient::DEFAULT_POLL_INTERVAL_SECONDS,         // Default: 2s (initial interval)
        onProgress: function (BatchJobDto $job): void {
            echo "Current status: {$job->status->value}\n";
        },
        maxPollIntervalSeconds: KinetiClient::DEFAULT_MAX_POLL_INTERVAL_SECONDS // Default: 10s (capped interval)
    );
    
    foreach ($completedBatch->results as $result) {
        echo $result->externalId . ': ' . $result->altText . "\n";
    }
} catch (BatchJobTimeoutException $e) {
    echo "Job timed out. Last known status: " . $e->latestJob->status->value;
}
```

#### Exponential Backoff & Polling Constants

`waitForBatchJob()` implements an adaptive polling strategy with jitter:
- **Exponential progression**: Polling begins at `$pollIntervalSeconds` (default: `2s`) and doubles on each subsequent check (e.g., `2s -> 4s -> 8s -> 10s`).
- **Interval cap**: The sleep interval between checks never exceeds `$maxPollIntervalSeconds` (default: `10s`).
- **Randomized jitter**: A randomized jitter between 0 and 500ms is added to every sleep duration to prevent synchronized polling spikes across distributed workers.
- **Progress monitoring**: The optional `$onProgress` callback receives the updated `BatchJobDto` on each poll check.

Default constants exposed on `KinetiClient`:
- `KinetiClient::DEFAULT_POLL_INTERVAL_SECONDS` = `2`
- `KinetiClient::DEFAULT_MAX_POLL_INTERVAL_SECONDS` = `10`
- `KinetiClient::DEFAULT_TIMEOUT_SECONDS` = `60`

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

# Open container shell
make shell
```