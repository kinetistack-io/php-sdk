<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Dto\ModuleDto;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\TransportInterface;

class ModuleClient
{
    /**
     * @var list<ModuleDto>|null
     */
    private ?array $cachedModules = null;

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * Fetch all available modules with their accessibility status.
     *
     * Results are cached for the lifetime of this ModuleClient instance. Pass $forceRefresh = true
     * to bypass the cache and fetch fresh data from the server.
     *
     * @return list<ModuleDto>
     * @throws KinetiException
     */
    public function list(bool $forceRefresh = false): array
    {
        if ($this->cachedModules !== null && !$forceRefresh) {
            return $this->cachedModules;
        }

        $response = $this->transport->request('GET', '/api/v1/modules');

        /** @var array<string, mixed>|list<array<string, mixed>> $data */
        $data = $response->toArray();
        $rawModules = [];

        if (isset($data['modules']) && is_array($data['modules'])) {
            $rawModules = $data['modules'];
        } elseif (isset($data['data']) && is_array($data['data'])) {
            $rawModules = $data['data'];
        } elseif (array_is_list($data)) {
            $rawModules = $data;
        }

        $modules = [];
        foreach ($rawModules as $item) {
            if (is_array($item)) {
                $modules[] = ModuleDto::fromArray($item);
            }
        }

        $this->cachedModules = $modules;

        return $modules;
    }

    /**
     * Clear the cached modules.
     */
    public function clearCache(): void
    {
        $this->cachedModules = null;
    }

    /**
     * Alias for list() to fetch all available modules.
     *
     * @return list<ModuleDto>
     * @throws KinetiException
     */
    public function getAll(bool $forceRefresh = false): array
    {
        return $this->list($forceRefresh);
    }

    /**
     * Get a specific module by its identifier.
     *
     * @throws KinetiException
     */
    public function get(string $moduleIdentifier, bool $forceRefresh = false): ?ModuleDto
    {
        foreach ($this->list($forceRefresh) as $module) {
            if ($module->identifier === $moduleIdentifier) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Check if a specific module is enabled and accessible to the current organization.
     *
     * @throws KinetiException
     */
    public function isEnabled(string $moduleIdentifier, bool $forceRefresh = false): bool
    {
        $module = $this->get($moduleIdentifier, $forceRefresh);

        return $module !== null && $module->isEnabled();
    }

    /**
     * Check if a specific module is accessible to the authenticated organization.
     *
     * @throws KinetiException
     */
    public function isAccessible(string $moduleIdentifier, bool $forceRefresh = false): bool
    {
        return $this->isEnabled($moduleIdentifier, $forceRefresh);
    }
}
