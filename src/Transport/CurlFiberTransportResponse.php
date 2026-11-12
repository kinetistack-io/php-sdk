<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Exception\TransportException;

class CurlFiberTransportResponse implements TransportResponseInterface
{
    /** @var array<string, array<array-key, string>> */
    private array $headers = [];
    private int $statusCode = 0;
    /** @var \Fiber<mixed, mixed, mixed, mixed> */
    private \Fiber $fiber;
    private ?string $firstChunk = null;
    private ?string $cachedContent = null;
    private bool $streamConsumed = false;

    /**
     * @param \CurlHandle|int $handleOrStatusCode
     * @param array<string, array<array-key, string>> $headers
     * @param (\Fiber<mixed, mixed, mixed, mixed>)|null $fiber
     * @param string|null $firstChunk
     */
    public function __construct(
        \CurlHandle|int $handleOrStatusCode,
        array $headers = [],
        ?\Fiber $fiber = null,
        ?string $firstChunk = null
    ) {
        if ($handleOrStatusCode instanceof \CurlHandle) {
            $ch = $handleOrStatusCode;
            $headersComplete = false;

            $this->fiber = new \Fiber(function () use ($ch, &$headersComplete): void {
                try {
                    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function (\CurlHandle $_ch, string $header) use (&$headersComplete): int {
                        $trimmed = trim($header);
                        if (str_starts_with($trimmed, 'HTTP/')) {
                            $parts = explode(' ', $trimmed, 3);
                            if (isset($parts[1])) {
                                $this->statusCode = (int) $parts[1];
                            }
                            $this->headers = [];
                            return strlen($header);
                        }

                        $parts = explode(':', $header, 2);
                        if (count($parts) === 2) {
                            $name = strtolower(trim($parts[0]));
                            $this->headers[$name][] = trim($parts[1]);
                        }

                        if (!$headersComplete && $trimmed === '' && $this->statusCode >= 200) {
                            $headersComplete = true;
                            \Fiber::suspend();
                        }

                        return strlen($header);
                    });

                    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function (\CurlHandle $_ch, string $data): int {
                        \Fiber::suspend($data);
                        return strlen($data);
                    });

                    $success = curl_exec($ch);
                    if (!$success) {
                        $error = curl_error($ch);
                        $errno = curl_errno($ch);
                        throw new TransportException(sprintf('cURL error (%d): %s', $errno, $error));
                    }
                } finally {
                    if (\PHP_VERSION_ID < 80500) {
                        curl_close($ch);
                    }
                }
            });

            $this->fiber->start();
        } else {
            $this->statusCode = $handleOrStatusCode;
            $this->headers = $headers;
            $this->fiber = $fiber ?? new \Fiber(static function (): void {
            });
            if (is_string($firstChunk) && $firstChunk !== '') {
                $this->firstChunk = $firstChunk;
            }
        }
    }

    public static function fromCurlHandle(\CurlHandle $ch): self
    {
        return new self($ch);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
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

    /**
     * @return array<string, array<array-key, string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getContent(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        if ($this->streamConsumed) {
            throw new TransportException('Response stream has already been consumed.');
        }

        $all = '';
        try {
            while (true) {
                $chunk = $this->nextChunk();
                if ($chunk === null) {
                    if ($this->fiber->isTerminated()) {
                        break;
                    }
                    continue;
                }
                $all .= $chunk;
            }
        } finally {
            $this->streamConsumed = true;
        }

        $this->cachedContent = $all;

        return $this->cachedContent;
    }

    /**
     * @return iterable<string>
     */
    public function getStreamIterator(): iterable
    {
        if ($this->cachedContent !== null) {
            if ($this->cachedContent !== '') {
                yield $this->cachedContent;
            }
            return;
        }

        if ($this->streamConsumed) {
            throw new TransportException('Response stream has already been consumed.');
        }

        try {
            $hasYielded = false;
            while (true) {
                $chunk = $this->nextChunk();
                if ($chunk === null) {
                    if ($this->fiber->isTerminated()) {
                        break;
                    }
                    continue;
                }
                $hasYielded = true;
                yield $chunk;
            }
            if (!$hasYielded) {
                $this->cachedContent = '';
            }
        } finally {
            $this->streamConsumed = true;
        }
    }

    private function nextChunk(): ?string
    {
        if ($this->firstChunk !== null) {
            $chunk = $this->firstChunk;
            $this->firstChunk = null;
            return $chunk;
        }

        if ($this->fiber->isTerminated()) {
            return null;
        }

        try {
            if (!$this->fiber->isStarted()) {
                $chunk = $this->fiber->start();
            } elseif ($this->fiber->isSuspended()) {
                $chunk = $this->fiber->resume();
            } else {
                return null;
            }
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException('Error reading response stream: ' . $e->getMessage(), 0, $e);
        }

        return is_string($chunk) && $chunk !== '' ? $chunk : null;
    }
}
