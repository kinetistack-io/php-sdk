<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\TransportException;
use Psr\Http\Message\ResponseInterface;

class Psr18TransportResponse implements TransportResponseInterface
{
    private ?string $cachedContent = null;

    public function __construct(
        private readonly ResponseInterface $response
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function toArray(): array
    {
        $content = $this->getContent();
        if (trim($content) === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new TransportException('Failed to decode JSON response: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new TransportException('JSON response is not an array.');
        }

        return $decoded;
    }

    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    public function getContent(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        try {
            $stream = $this->response->getBody();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $this->cachedContent = $stream->getContents();
            return $this->cachedContent;
        } catch (\Throwable $e) {
            throw new TransportException('Failed to read response body: ' . $e->getMessage(), 0, $e);
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
        if ($this->cachedContent !== null) {
            yield $this->cachedContent;
            return;
        }

        try {
            $stream = $this->response->getBody();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            while (!$stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException('Failed to read response stream: ' . $e->getMessage(), 0, $e);
        }
    }
}
