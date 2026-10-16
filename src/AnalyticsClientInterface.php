<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Client\AnalyticsClientInterface as BaseAnalyticsClientInterface;

/**
 * Public facade interface for AnalyticsClient.
 *
 * Extends the base client contract {@see BaseAnalyticsClientInterface}. Consumers should type-hint
 * against this facade in analytics dashboards and monitoring tools.
 */
interface AnalyticsClientInterface extends BaseAnalyticsClientInterface
{
}
