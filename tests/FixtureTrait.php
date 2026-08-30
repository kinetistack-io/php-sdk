<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

trait FixtureTrait
{
    /**
     * Load raw JSON fixture content.
     */
    protected function loadFixture(string $relativePath): string
    {
        $path = __DIR__ . '/Fixtures/' . ltrim($relativePath, '/');
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('Fixture file not found: %s', $path));
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Failed to read fixture file: %s', $path));
        }

        return $content;
    }

    /**
     * Load JSON fixture and decode to associative array.
     *
     * @return array<string, mixed>|list<mixed>
     */
    protected function loadFixtureArray(string $relativePath): array
    {
        /** @var array<string, mixed>|list<mixed> $decoded */
        $decoded = json_decode($this->loadFixture($relativePath), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
