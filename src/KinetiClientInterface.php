<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Client\KinetiClientInterface as BaseKinetiClientInterface;

/**
 * Public facade interface for KinetiClient.
 *
 * Extends the base client contract {@see BaseKinetiClientInterface}. Consumers should type-hint
 * against this facade in applications and unit test suites.
 */
interface KinetiClientInterface extends BaseKinetiClientInterface
{
    /**
     * Return a new immutable instance configured with the specified API key.
     */
    public function withApiKey(#[\SensitiveParameter] string $apiKey): static;

    /**
     * Access module-specific client operations.
     */
    public function modules(): ModuleClientInterface;
}
