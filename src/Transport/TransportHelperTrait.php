<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

trait TransportHelperTrait
{
    protected function validateApiHost(string $apiHost): void
    {
        if (preg_match('/[\x00-\x20\x7F]/', $apiHost)) {
            throw new \InvalidArgumentException('Invalid API host URL: control characters and whitespace are not allowed.');
        }

        $parsed = parse_url($apiHost);
        $scheme = is_array($parsed) && isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
        $host = is_array($parsed) && isset($parsed['host']) ? trim($parsed['host']) : '';

        if ($parsed === false || !in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \InvalidArgumentException('Invalid API host URL. Must be an absolute HTTP or HTTPS URL.');
        }

        if (isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['query']) || isset($parsed['fragment'])) {
            throw new \InvalidArgumentException('Invalid API host URL: user, password, query, and fragment components are not allowed.');
        }

        if (isset($parsed['path']) && $parsed['path'] !== '' && $parsed['path'] !== '/') {
            throw new \InvalidArgumentException('Invalid API host URL: path component must be empty or "/".');
        }
    }

    protected function validatePath(string $path): void
    {
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $path)) {
            throw new \InvalidArgumentException('Invalid request path. Must be a relative path starting with a single forward slash and cannot contain traversal segments.');
        }

        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new \InvalidArgumentException('Invalid request path. Must be a relative path starting with a single forward slash and cannot contain traversal segments.');
        }

        $rawPath = parse_url($path, PHP_URL_PATH);
        $pathOnly = is_string($rawPath) && $rawPath !== '' ? $rawPath : $path;

        $decodedPath = $pathOnly;
        for ($i = 0; $i < 3; ++$i) {
            $next = rawurldecode($decodedPath);
            if ($next === $decodedPath) {
                break;
            }
            $decodedPath = $next;
        }

        if (rawurldecode($decodedPath) !== $decodedPath) {
            throw new \InvalidArgumentException('Invalid request path. Must be a relative path starting with a single forward slash and cannot contain traversal segments.');
        }

        if (
            preg_match('/[\x00-\x1F\x7F\\\\]/', $decodedPath)
            || !str_starts_with($decodedPath, '/')
            || str_starts_with($decodedPath, '//')
        ) {
            throw new \InvalidArgumentException('Invalid request path. Must be a relative path starting with a single forward slash and cannot contain traversal segments.');
        }

        $segments = explode('/', $decodedPath);
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Invalid request path. Must be a relative path starting with a single forward slash and cannot contain traversal segments.');
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function redactDebugOptions(array $options): array
    {
        foreach (['auth', 'auth_bearer', 'auth_basic'] as $authKey) {
            if (array_key_exists($authKey, $options)) {
                $options[$authKey] = '[REDACTED]';
            }
        }

        if (isset($options['proxy'])) {
            $options['proxy'] = $this->redactProxyOption($options['proxy']);
        }

        if (isset($options['headers']) && is_array($options['headers'])) {
            $sensitiveHeaders = [
                'authorization',
                'x-kineti-key',
                'proxy-authorization',
                'cookie',
                'set-cookie',
                'x-api-key',
                'api-key',
                'token',
            ];
            $redactedHeaders = [];
            foreach ($options['headers'] as $headerName => $headerValue) {
                if (is_string($headerName)) {
                    $headerLower = strtolower($headerName);
                    if (in_array($headerLower, $sensitiveHeaders, true)) {
                        $redactedHeaders[$headerName] = '[REDACTED]';
                        continue;
                    }
                } elseif (is_string($headerValue) && str_contains($headerValue, ':')) {
                    [$prefix] = explode(':', $headerValue, 2);
                    if (in_array(strtolower(trim($prefix)), $sensitiveHeaders, true)) {
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

    private function redactProxyOption(mixed $proxy): mixed
    {
        if (is_string($proxy)) {
            return $this->redactUrlCredentials($proxy);
        }

        if (is_array($proxy)) {
            $sensitiveKeys = ['user', 'username', 'pass', 'password', 'auth', 'auth_basic', 'auth_bearer', 'credentials', 'secret'];
            $redacted = [];
            foreach ($proxy as $key => $value) {
                if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                    $redacted[$key] = '[REDACTED]';
                } elseif (is_string($value)) {
                    $redacted[$key] = $this->redactUrlCredentials($value);
                } elseif (is_array($value)) {
                    $redacted[$key] = $this->redactProxyOption($value);
                } else {
                    $redacted[$key] = $value;
                }
            }

            return $redacted;
        }

        return $proxy;
    }

    private function redactUrlCredentials(string $url): string
    {
        if (!str_contains($url, '@')) {
            return $url;
        }

        $pattern = '#^((?:[a-zA-Z][a-zA-Z0-9+.-]*://)?)([^/?\#@]+)@(.*)$#';
        if (preg_match($pattern, $url, $matches)) {
            $userInfo = $matches[2];
            if (str_contains($userInfo, ':')) {
                [$u, $p] = explode(':', $userInfo, 2);
                $redactedUser = $u !== '' ? '[REDACTED]' : '';
                $redactedPass = $p !== '' ? ':[REDACTED]' : ':';
                $redactedUserInfo = ($redactedUser !== '' || $p !== '') ? $redactedUser . $redactedPass : $userInfo;
            } else {
                $redactedUserInfo = '[REDACTED]';
            }

            return $matches[1] . $redactedUserInfo . '@' . $matches[3];
        }

        return $url;
    }
}
