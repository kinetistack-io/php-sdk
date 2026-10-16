<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Client;

use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Enum\AnalyticsGrouping;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\TransportInterface;

/**
 * Base interface defining usage analytics and metrics retrieval operations.
 *
 * Downstream consumers should typically type-hint against the public facade interface
 * {@see \KinetiStack\Sdk\AnalyticsClientInterface}.
 */
interface AnalyticsClientInterface
{
    public function withToken(#[\SensitiveParameter] string $jwtToken): static;

    public function getTransport(): TransportInterface;

    /**
     * Query aggregated usage analytics buckets.
     *
     * @throws KinetiException
     */
    public function getAnalytics(
        AnalyticsGrouping $grouping = AnalyticsGrouping::DAY,
        ?string $from = null,
        ?string $to = null,
        ?string $projectId = null
    ): AnalyticsDto;
}
