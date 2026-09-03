<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyTransportResponse implements TransportResponseInterface
{
    public function __construct(
        private readonly ResponseInterface $response
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
}
