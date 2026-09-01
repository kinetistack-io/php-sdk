<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Enum;

enum WebhookStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
