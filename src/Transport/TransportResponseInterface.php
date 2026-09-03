<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\TransportException;

interface TransportResponseInterface
{
    /**
     * Get the HTTP response status code.
     */
    public function getStatusCode(): int;

    /**
     * Get the JSON-decoded response body as an array.
     *
     * @throws TransportException If the body cannot be decoded as JSON
     * @return array<array-key, mixed>
     */
    public function toArray(): array;

    /**
     * Get response headers.
     *
     * @return array<string, array<array-key, string>>
     */
    public function getHeaders(): array;

    /**
     * Get the raw response content as a string.
     *
     * @throws TransportException
     */
    public function getContent(): string;
}
