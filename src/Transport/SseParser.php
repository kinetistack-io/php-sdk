<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Transport;

use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\Exception\TransportException;

class SseParser
{
    public const DEFAULT_MAX_BUFFER_BYTES = 1048576;

    public function __construct(
        private readonly int $maxBufferBytes = self::DEFAULT_MAX_BUFFER_BYTES
    ) {
        if ($this->maxBufferBytes <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'maxBufferBytes must be a positive integer, got %d.',
                $this->maxBufferBytes
            ));
        }
    }

    /**
     * Parse raw string chunks from an SSE stream and yield typed RagStreamChunkDto items.
     *
     * @param iterable<string> $stream
     * @return \Generator<int, RagStreamChunkDto>
     *
     * @throws ServerException If the stream yields an error event or error chunk
     * @throws TransportException If the stream buffer exceeds maximum limit
     */
    public function parse(iterable $stream): \Generator
    {
        $buffer = '';

        foreach ($stream as $chunk) {
            $buffer .= $chunk;

            while (true) {
                $posLf = strpos($buffer, "\n\n");
                $posCrlf = strpos($buffer, "\r\n\r\n");

                if ($posLf === false && $posCrlf === false) {
                    if (strlen($buffer) > $this->maxBufferBytes) {
                        throw new TransportException(sprintf('SSE stream buffer exceeded maximum limit of %d bytes.', $this->maxBufferBytes));
                    }
                    break;
                }

                if ($posLf !== false && $posCrlf !== false) {
                    if ($posLf < $posCrlf) {
                        $pos = $posLf;
                        $delimLen = 2;
                    } else {
                        $pos = $posCrlf;
                        $delimLen = 4;
                    }
                } elseif ($posLf !== false) {
                    $pos = $posLf;
                    $delimLen = 2;
                } else {
                    $pos = $posCrlf;
                    $delimLen = 4;
                }

                if ($pos > $this->maxBufferBytes) {
                    throw new TransportException(sprintf('SSE stream buffer exceeded maximum limit of %d bytes.', $this->maxBufferBytes));
                }

                $eventBlock = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + $delimLen);

                $result = $this->processEventBlock($eventBlock);
                if ($result === true) {
                    // Stream completed
                    return;
                }

                if ($result instanceof RagStreamChunkDto) {
                    yield $result;
                }
            }
        }

        // Process any remaining buffered text when the stream closes
        if (trim($buffer) !== '') {
            $result = $this->processEventBlock($buffer);
            if ($result instanceof RagStreamChunkDto) {
                yield $result;
            }
        }
    }

    /**
     * Process a single SSE event block.
     *
     * @return RagStreamChunkDto|bool|null Returns RagStreamChunkDto to yield, true to terminate stream, null to skip
     */
    private function processEventBlock(string $eventBlock): RagStreamChunkDto|bool|null
    {
        /** @var list<string> $lines */
        $lines = preg_split("/\r\n|\r|\n/", $eventBlock) ?: [];
        $event = null;
        $dataLines = [];

        foreach ($lines as $line) {
            $line = trim($line, "\r");
            if ($line === '' || str_starts_with($line, ':')) {
                // Empty line or SSE comment
                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data: ')) {
                $dataLines[] = substr($line, 6);
            } elseif (str_starts_with($line, 'data:')) {
                $dataLines[] = substr($line, 5);
            }
        }

        if ($event === 'done') {
            return true;
        }

        $dataStr = implode("\n", $dataLines);
        if (trim($dataStr) === '[DONE]') {
            return true;
        }

        if ($event === 'error') {
            throw new ServerException(trim($dataStr) !== '' ? $dataStr : 'Server error received during stream.');
        }

        if ($dataLines === []) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = null;
        try {
            $decoded = json_decode($dataStr, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = null;
        }

        if (is_array($decoded)) {
            if (isset($decoded['error'])) {
                $rawMsg = null;
                if (is_string($decoded['error'])) {
                    $rawMsg = $decoded['error'];
                } elseif (is_array($decoded['error'])) {
                    $nestedMsg = $decoded['error']['message'] ?? $decoded['error']['detail'] ?? null;
                    if (is_scalar($nestedMsg)) {
                        $rawMsg = (string) $nestedMsg;
                    }
                }

                if ($rawMsg === null) {
                    $rawMsg = $decoded['message'] ?? 'Server error during stream';
                }

                $errorMsg = is_scalar($rawMsg) ? (string) $rawMsg : 'Server error during stream';
                throw new ServerException($errorMsg);
            }

            /** @var array<string, mixed> $decoded */
            return RagStreamChunkDto::fromArray($decoded);
        }

        if (str_starts_with(trim($dataStr), '[Error:')) {
            throw new ServerException(trim($dataStr));
        }

        return new RagStreamChunkDto(text: $dataStr);
    }
}
