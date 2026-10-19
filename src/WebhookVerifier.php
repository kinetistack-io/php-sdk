<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

final class WebhookVerifier
{
    public const HEADER_SIGNATURE = 'Kineti-Signature';
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    private const PREFIX_V1 = 'v1=';

    private function __construct()
    {
    }

    /**
     * Verifies the HMAC-SHA256 signature of an incoming webhook payload.
     *
     * Supports modern timestamped signatures (Kineti-Signature: v1={timestamp}.{hmac})
     * with replay attack protection.
     *
     * @param string $payload The raw request body payload.
     * @param string $signatureHeader The signature string or header value (e.g. 'v1={timestamp}.{hmac}').
     * @param string $secret The shared webhook signing secret.
     * @param int $toleranceSeconds Maximum allowed clock skew / replay window in seconds (0 to disable).
     *     Negative values are rejected.
     * @param int|null $currentTime Optional UNIX timestamp for clock isolation / testing (defaults to time()).
     *
     * @return bool True if the signature is valid and within tolerance, false otherwise.
     */
    public static function verify(
        string $payload,
        string $signatureHeader,
        #[\SensitiveParameter] string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $currentTime = null,
    ): bool {
        $trimmed = trim($signatureHeader);

        if ($toleranceSeconds < 0 || $trimmed === '' || $secret === '') {
            return false;
        }

        if (!str_starts_with($trimmed, self::PREFIX_V1)) {
            return false;
        }

        $parts = explode('.', substr($trimmed, \strlen(self::PREFIX_V1)));
        if (\count($parts) !== 2) {
            return false;
        }

        [$timestampStr, $signature] = $parts;

        if (!ctype_digit($timestampStr) || !ctype_xdigit($signature) || \strlen($signature) !== 64) {
            return false;
        }

        $now = $currentTime ?? time();
        if ($toleranceSeconds > 0 && abs($now - (int) $timestampStr) > $toleranceSeconds) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestampStr . '.' . $payload, $secret);

        return hash_equals($expectedSignature, strtolower($signature));
    }
}
