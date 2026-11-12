<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\RateLimitInfoDto;
use PHPUnit\Framework\TestCase;

class RateLimitInfoDtoTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $dto = new RateLimitInfoDto(100, 95, 1726950000, 30);

        $this->assertSame(100, $dto->limit);
        $this->assertSame(100, $dto->getLimit());
        $this->assertSame(95, $dto->remaining);
        $this->assertSame(95, $dto->getRemaining());
        $this->assertSame(1726950000, $dto->reset);
        $this->assertSame(1726950000, $dto->getReset());
        $this->assertSame(30, $dto->retryAfter);
        $this->assertSame(30, $dto->getRetryAfter());
    }

    public function testDefaults(): void
    {
        $dto = new RateLimitInfoDto();

        $this->assertNull($dto->limit);
        $this->assertNull($dto->getLimit());
        $this->assertNull($dto->remaining);
        $this->assertNull($dto->getRemaining());
        $this->assertNull($dto->reset);
        $this->assertNull($dto->getReset());
        $this->assertNull($dto->retryAfter);
        $this->assertNull($dto->getRetryAfter());
    }

    public function testToArray(): void
    {
        $dto = new RateLimitInfoDto(60, 59, 1700000000, 10);

        $this->assertSame([
            'limit' => 60,
            'remaining' => 59,
            'reset' => 1700000000,
            'retry_after' => 10,
        ], $dto->toArray());
    }

    public function testFromArray(): void
    {
        $dto = RateLimitInfoDto::fromArray([
            'limit' => 120,
            'remaining' => '119',
            'reset' => 1720000000,
            'retry_after' => 45,
        ]);

        $this->assertSame(120, $dto->limit);
        $this->assertSame(119, $dto->remaining);
        $this->assertSame(1720000000, $dto->reset);
        $this->assertSame(45, $dto->retryAfter);

        // Test with camelCase retryAfter
        $dtoCamel = RateLimitInfoDto::fromArray([
            'retryAfter' => 20,
        ]);
        $this->assertSame(20, $dtoCamel->retryAfter);
    }

    public function testFromHeadersStandard(): void
    {
        $headers = [
            'X-RateLimit-Limit' => ['1000'],
            'X-RateLimit-Remaining' => ['999'],
            'X-RateLimit-Reset' => ['1726950000'],
            'Retry-After' => ['60'],
        ];

        $dto = RateLimitInfoDto::fromHeaders($headers);

        $this->assertNotNull($dto);
        $this->assertSame(1000, $dto->limit);
        $this->assertSame(999, $dto->remaining);
        $this->assertSame(1726950000, $dto->reset);
        $this->assertSame(60, $dto->retryAfter);
    }

    public function testFromHeadersCaseInsensitive(): void
    {
        $headers = [
            'x-ratelimit-limit' => ['500'],
            'x-ratelimit-remaining' => ['450'],
            'x-ratelimit-reset' => ['1726960000'],
            'retry-after' => ['15'],
        ];

        $dto = RateLimitInfoDto::fromHeaders($headers);

        $this->assertNotNull($dto);
        $this->assertSame(500, $dto->limit);
        $this->assertSame(450, $dto->remaining);
        $this->assertSame(1726960000, $dto->reset);
        $this->assertSame(15, $dto->retryAfter);
    }

    public function testFromHeadersRfcDraftFormat(): void
    {
        $headers = [
            'RateLimit-Limit' => ['200'],
            'RateLimit-Remaining' => ['150'],
            'RateLimit-Reset' => ['1726970000'],
        ];

        $dto = RateLimitInfoDto::fromHeaders($headers);

        $this->assertNotNull($dto);
        $this->assertSame(200, $dto->limit);
        $this->assertSame(150, $dto->remaining);
        $this->assertSame(1726970000, $dto->reset);
        $this->assertNull($dto->retryAfter);
    }

    public function testFromHeadersWithDateFormats(): void
    {
        $futureTime = time() + 120;
        $httpDate = gmdate('D, d M Y H:i:s \G\M\T', $futureTime);

        $headers = [
            'X-RateLimit-Reset' => [$httpDate],
            'Retry-After' => [$httpDate],
        ];

        $dto = RateLimitInfoDto::fromHeaders($headers);

        $this->assertNotNull($dto);
        $this->assertNull($dto->limit);
        $this->assertNull($dto->remaining);
        $this->assertSame($futureTime, $dto->reset);
        $this->assertGreaterThanOrEqual(118, $dto->retryAfter);
        $this->assertLessThanOrEqual(122, $dto->retryAfter);
    }

    public function testFromHeadersReturnsNullWhenNoRateLimitHeaders(): void
    {
        $headers = [
            'Content-Type' => ['application/json'],
            'Date' => ['Mon, 21 Sep 2026 20:00:00 GMT'],
        ];

        $dto = RateLimitInfoDto::fromHeaders($headers);

        $this->assertNull($dto);
    }

    public function testFromHeadersSingleStringValues(): void
    {
        $headers = [
            'X-RateLimit-Limit' => '100',
            'X-RateLimit-Remaining' => '50',
        ];

        $dto = RateLimitInfoDto::fromHeaders($headers);

        $this->assertNotNull($dto);
        $this->assertSame(100, $dto->limit);
        $this->assertSame(50, $dto->remaining);
        $this->assertNull($dto->reset);
        $this->assertNull($dto->retryAfter);
    }

    public function testFromHeadersWithIntegerKeysAndEmptyValues(): void
    {
        $headers = [
            0 => ['irrelevant'],
            'Retry-After' => ['45'],
            1 => ['another'],
            'X-RateLimit-Limit' => [''],
            'X-RateLimit-Remaining' => [],
        ];

        $dto = RateLimitInfoDto::fromHeaders($headers);

        $this->assertNotNull($dto);
        $this->assertSame(45, $dto->retryAfter);
        $this->assertNull($dto->limit);
        $this->assertNull($dto->remaining);
    }

    public function testFromHeadersSubSecondRetryAfterRoundsUp(): void
    {
        $dto1 = RateLimitInfoDto::fromHeaders(['Retry-After' => ['0.5']]);
        $this->assertNotNull($dto1);
        $this->assertSame(1, $dto1->retryAfter);

        $dto2 = RateLimitInfoDto::fromHeaders(['Retry-After' => ['0.0']]);
        $this->assertNotNull($dto2);
        $this->assertSame(0, $dto2->retryAfter);

        $dto3 = RateLimitInfoDto::fromHeaders(['Retry-After' => ['0']]);
        $this->assertNotNull($dto3);
        $this->assertSame(0, $dto3->retryAfter);

        $dto4 = RateLimitInfoDto::fromHeaders(['Retry-After' => ['1.2']]);
        $this->assertNotNull($dto4);
        $this->assertSame(2, $dto4->retryAfter);

        $dto5 = RateLimitInfoDto::fromHeaders(['Retry-After' => ['5.1']]);
        $this->assertNotNull($dto5);
        $this->assertSame(6, $dto5->retryAfter);
    }

    public function testFromHeadersRejectsNegativeNumbers(): void
    {
        $this->assertNull(RateLimitInfoDto::fromHeaders(['X-RateLimit-Limit' => ['-1']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['X-RateLimit-Remaining' => ['-10']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['X-RateLimit-Reset' => ['-100']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['Retry-After' => ['-1']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['Retry-After' => ['-0.5']]));

        $dto = RateLimitInfoDto::fromHeaders([
            'X-RateLimit-Limit' => ['-1'],
            'X-RateLimit-Remaining' => ['-5'],
            'X-RateLimit-Reset' => ['-10'],
            'Retry-After' => ['10'],
        ]);
        $this->assertNotNull($dto);
        $this->assertNull($dto->limit);
        $this->assertNull($dto->remaining);
        $this->assertNull($dto->reset);
        $this->assertSame(10, $dto->retryAfter);
    }

    public function testFromHeadersRejectsNonIntegerStringsForLimitAndRemaining(): void
    {
        $this->assertNull(RateLimitInfoDto::fromHeaders(['X-RateLimit-Limit' => ['12.5']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['X-RateLimit-Remaining' => ['12.5']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['X-RateLimit-Limit' => ['abc']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['X-RateLimit-Remaining' => ['xyz']]));

        $dto = RateLimitInfoDto::fromHeaders([
            'X-RateLimit-Limit' => ['100'],
            'X-RateLimit-Remaining' => ['12.5'],
        ]);
        $this->assertNotNull($dto);
        $this->assertSame(100, $dto->limit);
        $this->assertNull($dto->remaining);
    }

    public function testFromHeadersRejectsFloatStringsForReset(): void
    {
        $this->assertNull(RateLimitInfoDto::fromHeaders(['X-RateLimit-Reset' => ['12.5']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['X-RateLimit-Reset' => ['-0.5']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['ratelimit-reset' => ['12.5']]));
        $this->assertNull(RateLimitInfoDto::fromHeaders(['ratelimit-reset' => ['-0.5']]));

        $dto = RateLimitInfoDto::fromHeaders([
            'X-RateLimit-Limit' => ['100'],
            'X-RateLimit-Reset' => ['12.5'],
        ]);
        $this->assertNotNull($dto);
        $this->assertSame(100, $dto->limit);
        $this->assertNull($dto->reset);
    }
}
