<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

final class WebhookVerifier
{
    private const SIGNATURE_PREFIX = 'sha256=';

    private function __construct()
    {
    }

    /**
     * Verifies the HMAC-SHA256 signature of an incoming webhook payload.
     *
     * @param string $payload The raw request body payload.
     * @param string $signatureHeader The signature string or header value (with or without 'sha256=' prefix).
     * @param string $secret The shared webhook signing secret.
     *
     * @return bool True if the signature is valid, false otherwise.
     */
    public static function verify(string $payload, string $signatureHeader, string $secret): bool
    {
        $signature = trim($signatureHeader);

        if (str_starts_with($signature, self::SIGNATURE_PREFIX)) {
            $signature = substr($signature, \strlen(self::SIGNATURE_PREFIX));
        }

        if ($signature === '' || $secret === '') {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, strtolower($signature));
    }
}
