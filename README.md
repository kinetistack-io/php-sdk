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

$client = new KinetiClient(
    'https://api.kinetistack.io', // Your API host
    'sk_test_12345'               // Your project API key
);
```

### Synchronous Image Analysis

```php
// From a URL
$response = $client->analyzeImage('https://example.com/image.jpg', [
    'page_title' => 'Renewable Energy'
]);
echo $response->altText;

// From a local file
$response = $client->analyzeImageBinary('/path/to/image.jpg');

// From binary string content
$response = $client->analyzeImageContent($binaryData, 'image.jpg');
```

### Batch Processing

```php
use KinetiStack\Sdk\Dto\ImageInputDto;

// Submit a batch of images (URL or Base64 / Data URI)
$batch = $client->submitBatchJob([
    new ImageInputDto('media:1', 'https://example.com/1.jpg'),
    new ImageInputDto('media:2', imageBase64: 'data:image/jpeg;base64,...'),
]);

echo $batch->jobId; // E.g., '123e4567-e89b-12d3-a456-426614174000'

// Poll for completion (Wait up to 60s, polling every 2s)
try {
    $completedBatch = $client->waitForBatchJob($batch->jobId, 60, 2);
    
    foreach ($completedBatch->results as $result) {
        echo $result->externalId . ': ' . $result->altText . "\n";
    }
} catch (\KinetiStack\Sdk\Exception\BatchJobTimeoutException $e) {
    echo "Job timed out. Last known status: " . $e->latestJob->status;
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
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ValidationException;

try {
    $client->analyzeImage('invalid-url');
} catch (ValidationException $e) {
    print_r($e->violations);
} catch (RateLimitException $e) {
    echo "Rate limited. Retry after: " . $e->retryAfter . " seconds.";
} catch (\KinetiStack\Sdk\Exception\KinetiException $e) {
    echo "API Error: " . $e->getMessage();
}
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

# Open container shell
make shell
```