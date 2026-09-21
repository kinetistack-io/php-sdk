<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Enum;

enum AnalyticsGrouping: string
{
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
}
