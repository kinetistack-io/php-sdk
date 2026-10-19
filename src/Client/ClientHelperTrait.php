<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Client;

use KinetiStack\Sdk\Transport\HttpTransport;
use KinetiStack\Sdk\Transport\TransportInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Shared helper methods for client implementations.
 */
trait ClientHelperTrait
{
    /**
     * URL-encode a dynamic URI path segment, ensuring it is non-empty.
     *
     * @throws \InvalidArgumentException When segment is null or empty.
     */
    protected function encodePathSegment(?string $segment, string $paramName = 'ID'): string
    {
        if ($segment === null || trim($segment) === '') {
            throw new \InvalidArgumentException(sprintf('%s cannot be empty.', $paramName));
        }

        return rawurlencode($segment);
    }

    /**
     * Extract a collection list from various API envelope formats ('member', 'hydra:member', 'items', 'data').
     *
     * @param array<string, mixed>|list<mixed> $data
     * @return list<array<string, mixed>>
     */
    protected function extractCollection(array $data): array
    {
        foreach (['member', 'hydra:member', 'items', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                /** @var list<array<string, mixed>> $items */
                $items = array_values($data[$key]);
                return $items;
            }
        }

        /** @var list<array<string, mixed>> $items */
        $items = array_values($data);
        return $items;
    }

    /**
     * Create or configure a Bearer-authenticated transport.
     *
     * @param TransportInterface|null $transport Pre-existing transport instance (if provided)
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $httpClient HTTP client or transport instance
     * @param array<string, mixed> $options Default HTTP options
     */
    protected function createBearerTransport(
        string $apiHost,
        #[\SensitiveParameter]
        string $jwtToken,
        ?TransportInterface $transport = null,
        HttpClientInterface|ClientInterface|TransportInterface|null $httpClient = null,
        array $options = []
    ): TransportInterface {
        if ($transport !== null) {
            return $jwtToken !== '' ? $transport->withAuthHeaderValue('Bearer ' . $jwtToken) : $transport;
        }

        if ($httpClient instanceof TransportInterface) {
            return $jwtToken !== '' ? $httpClient->withAuthHeaderValue('Bearer ' . $jwtToken) : $httpClient;
        }

        $authHeaderValue = $jwtToken !== '' ? 'Bearer ' . $jwtToken : '';

        return new HttpTransport($apiHost, 'Authorization', $authHeaderValue, $httpClient, $options);
    }
}
