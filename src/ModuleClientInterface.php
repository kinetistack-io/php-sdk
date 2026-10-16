<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Client\ModuleClientInterface as BaseModuleClientInterface;

/**
 * Public facade interface for ModuleClient.
 *
 * Extends the base client contract {@see BaseModuleClientInterface}. Consumers should type-hint
 * against this facade in client applications and unit tests.
 */
interface ModuleClientInterface extends BaseModuleClientInterface
{
}
