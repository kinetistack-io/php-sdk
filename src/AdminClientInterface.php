<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Client\AdminClientInterface as BaseAdminClientInterface;

/**
 * Public facade interface for AdminClient.
 *
 * Extends the base client contract {@see BaseAdminClientInterface}. Consumers should type-hint
 * against this facade in admin applications and test suites.
 */
interface AdminClientInterface extends BaseAdminClientInterface
{
}
