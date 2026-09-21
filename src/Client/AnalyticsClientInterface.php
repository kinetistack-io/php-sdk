<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Client;

use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Enum\AnalyticsGrouping;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\TransportInterface;

interface AnalyticsClientInterface
{
    public function withToken(string $jwtToken): static;

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
