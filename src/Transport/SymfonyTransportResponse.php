<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyTransportResponse implements TransportResponseInterface
{
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly ?HttpClientInterface $client = null
    ) {
    }

    public function getStatusCode(): int
    {
        try {
            return $this->response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function toArray(): array
    {
        try {
            return $this->response->toArray(false);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function getHeaders(): array
    {
        try {
            return $this->response->getHeaders(false);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function getContent(): string
    {
        try {
            return $this->response->getContent(false);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function getInnerResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * @return iterable<string>
     */
    public function getStreamIterator(): iterable
    {
        if ($this->client !== null) {
            try {
                foreach ($this->client->stream($this->response) as $chunk) {
                    if ($chunk->isTimeout()) {
                        continue;
                    }
                    $content = $chunk->getContent();
                    if ($content !== '') {
                        yield $content;
                    }
                }
            } catch (TransportException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new TransportException('Failed to read stream: ' . $e->getMessage(), 0, $e);
            }
            return;
        }

        $content = $this->getContent();
        if ($content !== '') {
            yield $content;
        }
    }
}
