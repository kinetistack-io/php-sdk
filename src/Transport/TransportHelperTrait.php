<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

trait TransportHelperTrait
{
    private function validateApiHost(string $apiHost): void
    {
        $parsed = parse_url($apiHost);
        $scheme = is_array($parsed) && isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
        $host = is_array($parsed) && isset($parsed['host']) ? trim($parsed['host']) : '';

        if ($parsed === false || !in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \InvalidArgumentException('Invalid API host URL. Must be an absolute HTTP or HTTPS URL.');
        }
    }

    private function validatePath(string $path): void
    {
        $rawPath = parse_url($path, PHP_URL_PATH);
        $pathOnly = is_string($rawPath) && $rawPath !== '' ? $rawPath : $path;
        $decodedPath = rawurldecode($pathOnly);

        if (
            !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || !str_starts_with($decodedPath, '/')
            || str_starts_with($decodedPath, '//')
            || str_contains($decodedPath, '/../')
            || str_ends_with($decodedPath, '/..')
        ) {
            throw new \InvalidArgumentException('Invalid request path. Must be a relative path starting with a single forward slash and cannot contain traversal segments.');
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function redactDebugOptions(array $options): array
    {
        if (isset($options['headers']) && is_array($options['headers'])) {
            $redactedHeaders = [];
            foreach ($options['headers'] as $headerName => $headerValue) {
                if (is_string($headerName)) {
                    $headerLower = strtolower($headerName);
                    if (in_array($headerLower, ['authorization', 'x-kineti-key', 'proxy-authorization'], true)) {
                        $redactedHeaders[$headerName] = '[REDACTED]';
                        continue;
                    }
                } elseif (is_string($headerValue) && str_contains($headerValue, ':')) {
                    [$prefix] = explode(':', $headerValue, 2);
                    if (in_array(strtolower(trim($prefix)), ['authorization', 'x-kineti-key', 'proxy-authorization'], true)) {
                        $redactedHeaders[$headerName] = $prefix . ': [REDACTED]';
                        continue;
                    }
                }
                $redactedHeaders[$headerName] = $headerValue;
            }
            $options['headers'] = $redactedHeaders;
        }

        return $options;
    }
}
