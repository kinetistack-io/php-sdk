<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Enum\AnalyticsGrouping;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\TransportInterface;

/**
 * Interface defining usage analytics and metrics retrieval operations.
 *
 * Consumers should type-hint against this interface in analytics dashboards and monitoring tools.
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
