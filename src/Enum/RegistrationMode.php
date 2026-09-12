<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Enum;

enum RegistrationMode: string
{
    case Open = 'open';
    case Whitelist = 'whitelist';
    case Closed = 'closed';
}
