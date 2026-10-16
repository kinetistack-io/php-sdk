<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Client;

use KinetiStack\Sdk\Dto\ModuleDto;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\TransportInterface;

/**
 * Base interface defining the contract for module catalog operations.
 *
 * Downstream consumers should typically type-hint against the public facade interface
 * {@see \KinetiStack\Sdk\ModuleClientInterface}.
 */
interface ModuleClientInterface
{
    public function getTransport(): TransportInterface;

    /**
     * Fetch all available modules with their accessibility status.
     *
     * @return list<ModuleDto>
     * @throws KinetiException
     */
    public function list(bool $forceRefresh = false): array;

    /**
     * Clear the cached modules.
     */
    public function clearCache(): void;

    /**
     * Alias for list() to fetch all available modules.
     *
     * @return list<ModuleDto>
     * @throws KinetiException
     */
    public function getAll(bool $forceRefresh = false): array;

    /**
     * Get a specific module by its identifier.
     *
     * @throws KinetiException
     */
    public function get(string $moduleIdentifier, bool $forceRefresh = false): ?ModuleDto;

    /**
     * Check if a specific module is enabled and accessible to the current organization.
     *
     * @throws KinetiException
     */
    public function isEnabled(string $moduleIdentifier, bool $forceRefresh = false): bool;

    /**
     * Check if a specific module is accessible to the authenticated organization.
     *
     * @throws KinetiException
     */
    public function isAccessible(string $moduleIdentifier, bool $forceRefresh = false): bool;
}
