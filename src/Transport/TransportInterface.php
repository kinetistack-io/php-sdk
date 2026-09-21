<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\KinetiException;

interface TransportInterface
{
    /**
     * Return an instance with the specified auth header value.
     */
    public function withAuthHeaderValue(string $authHeaderValue): TransportInterface;

    /**
     * Send an HTTP request and return a unified transport response.
     *
     * @param array<string, mixed> $options Request options (e.g., headers, body, json, query)
     *
     * @throws KinetiException On transport or API errors (status >= 400)
     */
    public function request(string $method, string $path, array $options = []): TransportResponseInterface;

    /**
     * Send an HTTP request optimized for streaming and return a unified transport response.
     *
     * @param array<string, mixed> $options Request options (e.g., headers, body, json, query)
     *
     * @throws KinetiException On transport or API errors (status >= 400)
     */
    public function requestStream(string $method, string $path, array $options = []): TransportResponseInterface;
}
