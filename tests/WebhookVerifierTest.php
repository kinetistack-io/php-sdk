<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\WebhookVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
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

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET));
    }

    public function testVerifyValidSignatureWithoutPrefix(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $signature, self::SECRET));
    }

    public function testVerifyValidSignatureUppercaseHex(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = 'sha256=' . strtoupper($signature);

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET));
        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, strtoupper($signature), self::SECRET));
    }

    public function testVerifyValidSignatureWithWhitespace(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = "  sha256=" . $signature . " \n";

        $this->assertTrue(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET));
    }

    public function testVerifyInvalidPayloadFails(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = 'sha256=' . $signature;
        $tamperedPayload = self::PAYLOAD . ' ';

        $this->assertFalse(WebhookVerifier::verify($tamperedPayload, $header, self::SECRET));
    }

    public function testVerifyIncorrectSecretFails(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, self::SECRET);
        $header = 'sha256=' . $signature;
        $wrongSecret = 'wrong_secret_key';

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, $wrongSecret));
    }

    public function testVerifyIncorrectSignatureFails(): void
    {
        $header = 'sha256=abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, self::SECRET));
    }

    public function testVerifyEmptySecretFails(): void
    {
        $signature = hash_hmac('sha256', self::PAYLOAD, '');
        $header = 'sha256=' . $signature;

        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, $header, ''));
    }

    public function testVerifyEmptySignatureHeaderFails(): void
    {
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, '', self::SECRET));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, 'sha256=', self::SECRET));
        $this->assertFalse(WebhookVerifier::verify(self::PAYLOAD, '   ', self::SECRET));
    }

    public function testVerifyEmptyPayloadWithValidSignature(): void
    {
        $emptyPayload = '';
        $signature = hash_hmac('sha256', $emptyPayload, self::SECRET);
        $header = 'sha256=' . $signature;

        $this->assertTrue(WebhookVerifier::verify($emptyPayload, $header, self::SECRET));
    }
}
