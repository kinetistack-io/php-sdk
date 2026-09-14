<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\ConflictException;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\NotFoundException;
use KinetiStack\Sdk\Exception\PayloadTooLargeException;
use KinetiStack\Sdk\Exception\RateLimitException;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\Exception\ServiceModuleDisabledException;
use KinetiStack\Sdk\Exception\ServiceUnavailableException;
use KinetiStack\Sdk\Exception\ValidationException;

final class ResponseErrorHandler
{
    /**
     * @param array<array-key, array<array-key, string>> $headers
     * @throws KinetiException
     */
    public static function handleError(int $statusCode, array $headers, string $body): never
    {
        $content = [];
        if (trim($body) !== '') {
            try {
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $content = $decoded;
                }
            } catch (\Throwable) {
                $content = ['detail' => $body];
            }
        }

        $message = $content['detail'] ?? $content['title'] ?? ($body !== '' ? $body : 'Unknown error occurred');

        $rawViolations = is_array($content['violations'] ?? null) ? $content['violations'] : [];
        /** @var array<int, array{propertyPath: string, message: string}> $violations */
        $violations = [];
        foreach ($rawViolations as $violation) {
            if (is_array($violation)) {
                $violations[] = [
                    'propertyPath' => (string) ($violation['propertyPath'] ?? ''),
                    'message' => (string) ($violation['message'] ?? ''),
                ];
            }
        }

        $retryAfter = self::parseRetryAfter($headers);

        $module = isset($content['module']) && is_string($content['module']) && trim($content['module']) !== ''
            ? trim($content['module'])
            : null;

        throw match ($statusCode) {
            401 => new AuthenticationException($message),
            403 => $module !== null
                ? new ServiceModuleDisabledException($message, $module)
                : new AuthorizationException($message),
            404 => new NotFoundException($message),
            409 => new ConflictException($message),
            413 => new PayloadTooLargeException($message),
            422 => new ValidationException($message, $violations),
            429 => new RateLimitException($message, $retryAfter),
            500 => new ServerException($message),
            503 => new ServiceUnavailableException($message),
            default => new KinetiException(sprintf('API Error %d: %s', $statusCode, $message)),
        };
    }

    /**
     * @param array<array-key, array<array-key, string>> $headers
     */
    public static function parseRetryAfter(array $headers): ?int
    {
        foreach ($headers as $name => $values) {
            if (strtolower((string) $name) === 'retry-after') {
                $first = reset($values);
                if ($first !== false) {
                    $raw = trim((string) $first);
                    if (is_numeric($raw)) {
                        return max(0, (int) $raw);
                    }

                    $timestamp = strtotime($raw);
                    if ($timestamp !== false) {
                        return max(0, $timestamp - time());
                    }
                }
            }
        }

        return null;
    }
}
