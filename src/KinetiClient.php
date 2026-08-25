<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Dto\BatchJobDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Dto\VisionResponseDto;
use KinetiStack\Sdk\Exception\BatchJobTimeoutException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\HttpTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class KinetiClient
{
    private HttpTransport $transport;

    /**
     * @param array<string, mixed> $options Default HTTP options (e.g., timeout, headers)
     */
    public function __construct(
        string $apiHost,
        string $apiKey,
        ?HttpClientInterface $httpClient = null,
        array $options = []
    ) {
        $this->transport = new HttpTransport($apiHost, $apiKey, $httpClient, $options);
    }

    /**
     * Check if the API is alive.
     *
     * @return array{status: string, checks?: array<string, string>}
     * @throws KinetiException
     */
    public function healthz(): array
    {
        $response = $this->transport->request('GET', '/healthz');

        /** @var array{status: string, checks?: array<string, string>} $data */
        $data = $response->toArray();

        return $data;
    }

    /**
     * Check if the API and its dependencies are ready.
     *
     * @return array{status: string, checks?: array<string, string>}
     * @throws KinetiException
     */
    public function readyz(): array
    {
        $response = $this->transport->request('GET', '/readyz');

        /** @var array{status: string, checks?: array<string, string>} $data */
        $data = $response->toArray();

        return $data;
    }

    /**
     * @param array<string, mixed> $contextHints
     * @param array<string, mixed> $options
     */
    public function analyzeImage(string $imageUrl, array $contextHints = [], array $options = []): VisionResponseDto
    {
        $payload = [
            'image_url' => $imageUrl,
        ];
        if (!empty($contextHints)) {
            $payload['context_hints'] = $contextHints;
        }
        if (!empty($options)) {
            $payload['options'] = $options;
        }

        $response = $this->transport->request('POST', '/v1/images/analyze', [
            'json' => $payload,
        ]);

        return VisionResponseDto::fromArray($response->toArray()['data'] ?? []);
    }

    /**
     * @param array<string, mixed> $contextHints
     * @param array<string, mixed> $options
     */
    public function analyzeImageBinary(string $filePath, array $contextHints = [], array $options = []): VisionResponseDto
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('File does not exist: %s', $filePath));
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Failed to read file: %s', $filePath));
        }

        return $this->analyzeImageContent($content, basename($filePath), $contextHints, $options);
    }

    /**
     * @param array<string, mixed> $contextHints
     * @param array<string, mixed> $options
     */
    public function analyzeImageContent(string $binaryData, string $filename = 'image.jpg', array $contextHints = [], array $options = []): VisionResponseDto
    {
        $fields = [
            'file' => [
                'content' => $binaryData,
                'filename' => $filename,
            ],
        ];

        if (!empty($contextHints)) {
            $fields['context_hints'] = json_encode($contextHints, JSON_THROW_ON_ERROR);
        }
        if (!empty($options)) {
            $fields['options'] = json_encode($options, JSON_THROW_ON_ERROR);
        }

        [$contentType, $body] = $this->createMultipartPayload($fields);

        $response = $this->transport->request('POST', '/v1/images/analyze', [
            'headers' => [
                'Content-Type' => $contentType,
            ],
            'body' => $body,
        ]);

        return VisionResponseDto::fromArray($response->toArray()['data'] ?? []);
    }

    /**
     * @param array<int, ImageInputDto|array<string, mixed>> $images
     * @param array<string, mixed> $options
     */
    public function submitBatchJob(array $images, array $options = []): BatchJobDto
    {
        $formattedImages = array_map(function ($image) {
            if ($image instanceof ImageInputDto) {
                return $image->toArray();
            }
            return $image;
        }, $images);

        $payload = [
            'images' => $formattedImages,
        ];
        if (!empty($options)) {
            $payload['options'] = $options;
        }

        $response = $this->transport->request('POST', '/v1/jobs/batch-images', [
            'json' => $payload,
        ]);

        return BatchJobDto::fromArray($response->toArray());
    }

    public function getBatchJobStatus(string $jobId): BatchJobDto
    {
        $response = $this->transport->request('GET', sprintf('/v1/jobs/%s', urlencode($jobId)));

        return BatchJobDto::fromArray($response->toArray());
    }

    /**
     * @throws BatchJobTimeoutException
     * @throws KinetiException
     */
    public function waitForBatchJob(string $jobId, int $timeoutSeconds = 60, int $pollIntervalSeconds = 2, ?callable $onProgress = null): BatchJobDto
    {
        $startTime = time();
        $lastDto = null;
        $pollIntervalSeconds = max(1, $pollIntervalSeconds);

        if ($timeoutSeconds <= 0) {
            $lastDto = $this->getBatchJobStatus($jobId);
        }

        while ((time() - $startTime) < $timeoutSeconds) {
            $lastDto = $this->getBatchJobStatus($jobId);

            if ($onProgress) {
                $onProgress($lastDto);
            }

            if ($lastDto->isCompleted()) {
                return $lastDto;
            }

            sleep($pollIntervalSeconds);
        }

        throw new BatchJobTimeoutException(
            sprintf('Batch job %s did not complete within %d seconds.', $jobId, $timeoutSeconds),
            $lastDto ?? $this->getBatchJobStatus($jobId)
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{0: string, 1: string}
     */
    private function createMultipartPayload(array $fields): array
    {
        $boundary = bin2hex(random_bytes(16));
        $body = '';

        foreach ($fields as $name => $content) {
            $body .= "--{$boundary}\r\n";
            if (is_array($content) && isset($content['content'], $content['filename'])) {
                $body .= sprintf("Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n", $name, $content['filename']);
                $body .= "Content-Type: application/octet-stream\r\n\r\n";
                $body .= $content['content'] . "\r\n";
            } else {
                $body .= sprintf("Content-Disposition: form-data; name=\"%s\"\r\n\r\n", $name);
                $body .= $content . "\r\n";
            }
        }
        $body .= "--{$boundary}--\r\n";

        return [
            'multipart/form-data; boundary=' . $boundary,
            $body
        ];
    }
}
