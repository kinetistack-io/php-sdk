<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\WebhookVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookVerifier::class)]
final class WebhookVerifierTest extends TestCase
{
    private const SECRET = 'test_webhook_secret_key_12345';
    private const PAYLOAD = '{"event":"batch_images.completed","job_id":"f327cd33-b38d-416e-8094-fb05ca44699c","status":"completed","results":[{"external_id":"media:1","alt_text":"A scenic mountain view"}]}';

    public function testVerifyValidSignatureWithPrefix(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = 'sha256=' . $signature;

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET, allowLegacy: true));
    }

    public function testVerifyValidSignatureWithoutPrefix(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $signature, self::SECRET, allowLegacy: true));
    }

    public function testVerifyValidSignatureUppercaseHex(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = 'sha256=' . strtoupper($signature);

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET, allowLegacy: true));
        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, strtoupper($signature), self::SECRET, allowLegacy: true));
    }

    public function testVerifyValidSignatureWithWhitespace(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = "  sha256=" . $signature . " \n";

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET, allowLegacy: true));
    }

    public function testVerifyInvalidPayloadFails(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = 'sha256=' . $signature;
        $tamperedPayload = self::PAYLOAD . ' ';

        $this->assertFalse(WebhookVerifier::verify($tamperedPayload, $header, self::SECRET, allowLegacy: true));
    }

    public function testVerifyIncorrectSecretFails(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = 'sha256=' . $signature;
        $wrongSecret = 'wrong_secret_key';

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, $wrongSecret, allowLegacy: true));
    }

    public function testVerifyIncorrectSignatureFails(): void
    {
        $header = 'sha256=abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET, allowLegacy: true));
    }

    public function testVerifyEmptySecretFails(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, '');
        $header = 'sha256=' . $signature;

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, '', allowLegacy: true));
    }

    public function testVerifyEmptySignatureHeaderFails(): void
    {
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, '', self::SECRET, allowLegacy: true));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, 'sha256=', self::SECRET, allowLegacy: true));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, '   ', self::SECRET, allowLegacy: true));
    }

    public function testVerifyEmptyPayloadWithValidSignature(): void
    {
        $emptyPayload = '';
        $signature = hash_hmac('sha256', $emptyPayload, self::SECRET);
        $header = 'sha256=' . $signature;

        $this->assertTrue(WebhookVerifier::verify($emptyPayload, $header, self::SECRET, allowLegacy: true));
    }

    public function testLegacySignaturesRejectedByDefault(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = 'sha256=' . $signature;

        // Legacy with sha256= prefix rejected by default
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET, allowLegacy: false));

        // Legacy without prefix (raw hex) rejected by default
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $signature, self::SECRET));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $signature, self::SECRET, allowLegacy: false));
    }

    public function testLegacySignaturesInvalidHexOrLengthRejected(): void
    {
        // Shorter than 64 hex characters (63 chars)
        $short = str_repeat('a', 63);
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $short, self::SECRET, allowLegacy: true));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, 'sha256=' . $short, self::SECRET, allowLegacy: true));

        // Longer than 64 hex characters (65 chars)
        $long = str_repeat('a', 65);
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $long, self::SECRET, allowLegacy: true));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, 'sha256=' . $long, self::SECRET, allowLegacy: true));

        // 64 characters but containing non-hex characters
        $nonHex = str_repeat('g', 64);
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $nonHex, self::SECRET, allowLegacy: true));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, 'sha256=' . $nonHex, self::SECRET, allowLegacy: true));
    }

    public function testConstants(): void
    {
        $this->assertSame('Kineti-Signature', WebhookVerifier::HEADER_SIGNATURE);
        $this->assertSame('X-Kineti-Signature', WebhookVerifier::HEADER_LEGACY_SIGNATURE);
        $this->assertSame(300, WebhookVerifier::DEFAULT_TOLERANCE_SECONDS);
    }

    public function testVerifyValidV1Signature(): void
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', $timestamp . '.' . self::PAYLOAD, self::SECRET);
        $header = "v1={$timestamp}.{$hash}";

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET));
    }

    public function testVerifyValidV1SignatureUppercaseHex(): void
    {
        $timestamp = time();
        $hash = strtoupper(hash_hmac('sha256', $timestamp . '.' . self::PAYLOAD, self::SECRET));
        $header = "v1={$timestamp}.{$hash}";

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET));
    }

    public function testVerifyValidV1SignatureWithWhitespace(): void
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', $timestamp . '.' . self::PAYLOAD, self::SECRET);
        $header = "  v1={$timestamp}.{$hash} \n";

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET));
    }

    public function testVerifyV1ToleranceBoundary(): void
    {
        $now = 1700000000;
        $tolerance = 300;

        // Exact lower boundary: $now - 300
        $tsPast = $now - $tolerance;
        $hashPast = hash_hmac('sha256', $tsPast . '.' . self::PAYLOAD, self::SECRET);
        $headerPast = "v1={$tsPast}.{$hashPast}";
        $this->assertTrue(WebhookVerifier::verify(
            self::PAYLOAD,
            $headerPast,
            self::SECRET,
            $tolerance,
            $now
        ));

        // Exact upper boundary: $now + 300
        $tsFuture = $now + $tolerance;
        $hashFuture = hash_hmac('sha256', $tsFuture . '.' . self::PAYLOAD, self::SECRET);
        $headerFuture = "v1={$tsFuture}.{$hashFuture}";
        $this->assertTrue(WebhookVerifier::verify(
            self::PAYLOAD,
            $headerFuture,
            self::SECRET,
            $tolerance,
            $now
        ));
    }

    public function testVerifyV1ExpiredSignatureFails(): void
    {
        $now = 1700000000;
        $tolerance = 300;

        $tsExpired = $now - $tolerance - 1;
        $hashExpired = hash_hmac('sha256', $tsExpired . '.' . self::PAYLOAD, self::SECRET);
        $headerExpired = "v1={$tsExpired}.{$hashExpired}";

        $this->assertFalse(WebhookVerifier::verify(
            self::PAYLOAD,
            $headerExpired,
            self::SECRET,
            $tolerance,
            $now
        ));
    }

    public function testVerifyV1FutureSignatureBeyondToleranceFails(): void
    {
        $now = 1700000000;
        $tolerance = 300;

        $tsFuture = $now + $tolerance + 1;
        $hashFuture = hash_hmac('sha256', $tsFuture . '.' . self::PAYLOAD, self::SECRET);
        $headerFuture = "v1={$tsFuture}.{$hashFuture}";

        $this->assertFalse(WebhookVerifier::verify(
            self::PAYLOAD,
            $headerFuture,
            self::SECRET,
            $tolerance,
            $now
        ));
    }

    public function testVerifyV1DynamicClockWithBuffer(): void
    {
        $tsValid = time() - 280;
        $hashValid = hash_hmac('sha256', $tsValid . '.' . self::PAYLOAD, self::SECRET);
        $headerValid = "v1={$tsValid}.{$hashValid}";
        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $headerValid, self::SECRET, 300));

        $tsExpired = time() - 320;
        $hashExpired = hash_hmac('sha256', $tsExpired . '.' . self::PAYLOAD, self::SECRET);
        $headerExpired = "v1={$tsExpired}.{$hashExpired}";
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $headerExpired, self::SECRET, 300));
    }

    public function testVerifyV1NegativeToleranceFails(): void
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', $timestamp . '.' . self::PAYLOAD, self::SECRET);
        $header = "v1={$timestamp}.{$hash}";

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET, -1));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET, -300));
    }

    public function testVerifyV1ToleranceDisabled(): void
    {
        $tsOld = time() - 86400;
        $hashOld = hash_hmac('sha256', $tsOld . '.' . self::PAYLOAD, self::SECRET);
        $headerOld = "v1={$tsOld}.{$hashOld}";
        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $headerOld, self::SECRET, 0));

        $tsFuture = time() + 86400;
        $hashFuture = hash_hmac('sha256', $tsFuture . '.' . self::PAYLOAD, self::SECRET);
        $headerFuture = "v1={$tsFuture}.{$hashFuture}";
        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $headerFuture, self::SECRET, 0));
    }

    public function testVerifyV1TamperedPayloadFails(): void
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', $timestamp . '.' . self::PAYLOAD, self::SECRET);
        $header = "v1={$timestamp}.{$hash}";

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD . ' ', $header, self::SECRET));
    }

    public function testVerifyV1WrongSecretFails(): void
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', $timestamp . '.' . self::PAYLOAD, self::SECRET);
        $header = "v1={$timestamp}.{$hash}";

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, 'wrong_secret'));
    }

    public function testVerifyV1EmptySecretFails(): void
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', $timestamp . '.' . self::PAYLOAD, '');
        $header = "v1={$timestamp}.{$hash}";

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, ''));
    }

    public function testVerifyV1EmptyPayloadSuccess(): void
    {
        $emptyPayload = '';
        $timestamp = time();
        $hash = hash_hmac('sha256', $timestamp . '.' . $emptyPayload, self::SECRET);
        $header = "v1={$timestamp}.{$hash}";

        $this->assertTrue(WebhookVerifier::verify($emptyPayload, $header, self::SECRET));
    }

    public function testVerifyV1InvalidHexOrLengthRejected(): void
    {
        $timestamp = time();

        // 63 chars (too short)
        $short = "v1={$timestamp}." . str_repeat('a', 63);
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $short, self::SECRET));

        // 65 chars (too long)
        $long = "v1={$timestamp}." . str_repeat('a', 65);
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $long, self::SECRET));

        // 64 chars with non-hex characters
        $nonHex = "v1={$timestamp}." . str_repeat('z', 64);
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $nonHex, self::SECRET));
    }

    #[DataProvider('provideMalformedV1Headers')]
    public function testVerifyV1MalformedHeaders(string $malformedHeader): void
    {
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $malformedHeader, self::SECRET));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provideMalformedV1Headers(): array
    {
        $dummyHex = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';

        return [
            'empty after prefix' => ['v1='],
            'missing signature' => ['v1=12345'],
            'missing signature with dot' => ['v1=12345.'],
            'missing timestamp with dot' => ['v1=.' . $dummyHex],
            'dot only' => ['v1=.'],
            'multiple dots' => ['v1=12345.abc.def'],
            'non-numeric timestamp' => ['v1=abc.def'],
            'negative timestamp' => ['v1=-12345.' . $dummyHex],
            'non-hex signature' => ['v1=12345.not_a_valid_hex_signature_value_here!'],
            'non-hex 64-char signature' => ['v1=12345.' . str_repeat('z', 64)],
            'invalid signature length short' => ['v1=12345.' . substr($dummyHex, 0, 63)],
            'invalid signature length long' => ['v1=12345.' . $dummyHex . 'a'],
            'spaces around separator' => ['v1= 12345 . abcdef '],
        ];
    }
}
