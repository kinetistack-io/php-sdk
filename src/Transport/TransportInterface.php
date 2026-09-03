<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\KinetiException;

interface TransportInterface
{
    /**
     * Send an HTTP request and return a unified transport response.
     *
     * @param array<string, mixed> $options Request options (e.g., headers, body, json, query)
     *
     * @throws KinetiException On transport or API errors (status >= 400)
     */
    public function request(string $method, string $path, array $options = []): TransportResponseInterface;
}
