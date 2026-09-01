<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Enum;

use KinetiStack\Sdk\Enum\WebhookStatus;
use PHPUnit\Framework\TestCase;

class WebhookStatusTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('pending', WebhookStatus::Pending->value);
        $this->assertSame('delivered', WebhookStatus::Delivered->value);
        $this->assertSame('failed', WebhookStatus::Failed->value);
    }

    public function testTryFromValidValues(): void
    {
        $this->assertSame(WebhookStatus::Pending, WebhookStatus::tryFrom('pending'));
        $this->assertSame(WebhookStatus::Delivered, WebhookStatus::tryFrom('delivered'));
        $this->assertSame(WebhookStatus::Failed, WebhookStatus::tryFrom('failed'));
    }

    public function testTryFromInvalidValuesReturnsNull(): void
    {
        /** @var string $unknown */
        $unknown = 'unknown';
        /** @var string $sent */
        $sent = 'sent';
        /** @var string $empty */
        $empty = '';

        $this->assertNull(WebhookStatus::tryFrom($unknown));
        $this->assertNull(WebhookStatus::tryFrom($sent));
        $this->assertNull(WebhookStatus::tryFrom($empty));
    }
}
