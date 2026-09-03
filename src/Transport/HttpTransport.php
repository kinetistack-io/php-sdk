<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use Http\Discovery\Psr18ClientDiscovery;
use KinetiStack\Sdk\Exception\TransportException;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpTransport implements TransportInterface
{
    private readonly TransportInterface $delegate;

    /**
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        string $apiHost,
        string $apiKey,
        HttpClientInterface|ClientInterface|TransportInterface|null $client = null,
        array $defaultOptions = []
    ) {
        if ($client instanceof TransportInterface) {
            $this->delegate = $client;
        } elseif ($client instanceof HttpClientInterface) {
            $this->delegate = new SymfonyTransport($apiHost, $apiKey, $client, $defaultOptions);
        } elseif ($client instanceof ClientInterface) {
            $this->delegate = new Psr18Transport($apiHost, $apiKey, $client, $defaultOptions);
        } else {
            $this->delegate = self::createDiscoveredTransport($apiHost, $apiKey, $defaultOptions);
        }
    }

    public function request(string $method, string $path, array $options = []): TransportResponseInterface
    {
        return $this->delegate->request($method, $path, $options);
    }

    public function getDelegate(): TransportInterface
    {
        return $this->delegate;
    }

    /**
     * @param array<string, mixed> $defaultOptions
     */
    private static function createDiscoveredTransport(
        string $apiHost,
        string $apiKey,
        array $defaultOptions = []
    ): TransportInterface {
        try {
            $psr18Client = Psr18ClientDiscovery::find();
            return new Psr18Transport($apiHost, $apiKey, $psr18Client, $defaultOptions);
        } catch (\Throwable) {
            if (class_exists(HttpClient::class)) {
                return new SymfonyTransport($apiHost, $apiKey, null, $defaultOptions);
            }

            throw new TransportException(
                'No HTTP client found. Please install a PSR-18 HTTP client (e.g. guzzlehttp/guzzle) or symfony/http-client.'
            );
        }
    }
}
