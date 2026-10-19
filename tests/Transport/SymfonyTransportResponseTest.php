<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\Transport\SymfonyTransportResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(SymfonyTransportResponse::class)]
#[UsesClass(TransportException::class)]
#[UsesClass(KinetiException::class)]
final class SymfonyTransportResponseTest extends TestCase
{
    public function testGetStatusCodeSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->assertSame(200, $transportResponse->getStatusCode());
    }

    public function testGetStatusCodeThrowsTransportExceptionOnTransportExceptionInterface(): void
    {
        $exception = new class ('Network connection failed') extends \RuntimeException implements TransportExceptionInterface {
        };

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willThrowException($exception);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Network connection failed');
        $this->expectExceptionCode(0);

        try {
            $transportResponse->getStatusCode();
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    public function testGetHeadersSuccess(): void
    {
        $headers = [
            'content-type' => ['application/json'],
            'x-request-id' => ['req-123'],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getHeaders')
            ->with(false)
            ->willReturn($headers);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->assertSame($headers, $transportResponse->getHeaders());
    }

    public function testGetHeadersThrowsTransportExceptionOnTransportExceptionInterface(): void
    {
        $exception = new class ('Headers timeout') extends \RuntimeException implements TransportExceptionInterface {
        };

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getHeaders')
            ->with(false)
            ->willThrowException($exception);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Headers timeout');
        $this->expectExceptionCode(0);

        try {
            $transportResponse->getHeaders();
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    public function testGetHeadersThrowsTransportExceptionOnGenericThrowable(): void
    {
        $exception = new \RuntimeException('Unexpected headers failure');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getHeaders')
            ->with(false)
            ->willThrowException($exception);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unexpected headers failure');
        $this->expectExceptionCode(0);

        try {
            $transportResponse->getHeaders();
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    public function testGetContentSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('{"hello":"world"}');

        $transportResponse = new SymfonyTransportResponse($response);

        $this->assertSame('{"hello":"world"}', $transportResponse->getContent());
    }

    public function testGetContentThrowsTransportExceptionOnTransportExceptionInterface(): void
    {
        $exception = new class ('Content stream error') extends \RuntimeException implements TransportExceptionInterface {
        };

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willThrowException($exception);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Content stream error');
        $this->expectExceptionCode(0);

        try {
            $transportResponse->getContent();
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    public function testGetContentThrowsTransportExceptionOnGenericThrowable(): void
    {
        $exception = new \RuntimeException('Broken stream read');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willThrowException($exception);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Broken stream read');
        $this->expectExceptionCode(0);

        try {
            $transportResponse->getContent();
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    public function testToArraySuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('{"key":"value","items":[1,2]}');
        $response->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['key' => 'value', 'items' => [1, 2]]);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->assertSame(['key' => 'value', 'items' => [1, 2]], $transportResponse->toArray());
    }

    public function testToArrayReturnsEmptyArrayWhenContentIsEmpty(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('');
        $response->expects($this->never())
            ->method('toArray');

        $transportResponse = new SymfonyTransportResponse($response);

        $this->assertSame([], $transportResponse->toArray());
    }

    public function testToArrayReturnsEmptyArrayWhenContentIsWhitespaceOnly(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn("   \n\t  ");
        $response->expects($this->never())
            ->method('toArray');

        $transportResponse = new SymfonyTransportResponse($response);

        $this->assertSame([], $transportResponse->toArray());
    }

    public function testToArrayThrowsTransportExceptionWhenDecodingFails(): void
    {
        $decodingException = new class ('Failed to decode JSON: Syntax error') extends \RuntimeException implements DecodingExceptionInterface {
        };

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('{invalid json');
        $response->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willThrowException($decodingException);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to decode JSON: Syntax error');
        $this->expectExceptionCode(0);

        try {
            $transportResponse->toArray();
        } catch (TransportException $e) {
            $this->assertSame($decodingException, $e->getPrevious());
            throw $e;
        }
    }

    public function testToArrayThrowsTransportExceptionWhenJsonIsNotAnArray(): void
    {
        $notArrayException = new class ('JSON is not an array') extends \RuntimeException implements DecodingExceptionInterface {
        };

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('123');
        $response->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willThrowException($notArrayException);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('JSON is not an array');
        $this->expectExceptionCode(0);

        try {
            $transportResponse->toArray();
        } catch (TransportException $e) {
            $this->assertSame($notArrayException, $e->getPrevious());
            throw $e;
        }
    }

    public function testToArrayThrowsTransportExceptionWhenGetContentThrowsTransportExceptionInterface(): void
    {
        $exception = new class ('Underlying connection failed') extends \RuntimeException implements TransportExceptionInterface {
        };

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willThrowException($exception);

        $transportResponse = new SymfonyTransportResponse($response);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Underlying connection failed');

        $transportResponse->toArray();
    }

    public function testGetInnerResponseReturnsWrappedResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $transportResponse = new SymfonyTransportResponse($response);

        $this->assertSame($response, $transportResponse->getInnerResponse());
    }

    public function testGetStreamIteratorWithoutClientFallbackWithContent(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('fallback content body');

        $transportResponse = new SymfonyTransportResponse($response, client: null);

        $chunks = iterator_to_array($transportResponse->getStreamIterator());

        $this->assertSame(['fallback content body'], $chunks);
    }

    public function testGetStreamIteratorWithoutClientFallbackWithEmptyContent(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('');

        $transportResponse = new SymfonyTransportResponse($response, client: null);

        $chunks = iterator_to_array($transportResponse->getStreamIterator());

        $this->assertSame([], $chunks);
    }

    public function testGetStreamIteratorWithoutClientThrowsTransportExceptionWhenGetContentFails(): void
    {
        $exception = new class ('Stream read failed') extends \RuntimeException implements TransportExceptionInterface {
        };

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willThrowException($exception);

        $transportResponse = new SymfonyTransportResponse($response, client: null);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Stream read failed');

        iterator_to_array($transportResponse->getStreamIterator());
    }

    public function testGetStreamIteratorWithClientHappyPath(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(HttpClientInterface::class);

        $timeoutChunk = $this->createChunk('', isTimeout: true);
        $chunk1 = $this->createChunk('data: chunk1' . "\n\n", isTimeout: false);
        $emptyChunk = $this->createChunk('', isTimeout: false);
        $chunk2 = $this->createChunk('data: chunk2' . "\n\n", isTimeout: false);

        $generator = (function () use ($response, $timeoutChunk, $chunk1, $emptyChunk, $chunk2): \Generator {
            yield $response => $timeoutChunk;
            yield $response => $chunk1;
            yield $response => $emptyChunk;
            yield $response => $chunk2;
        })();

        $stream = new ResponseStream($generator);

        $client->expects($this->once())
            ->method('stream')
            ->with($response)
            ->willReturn($stream);

        $transportResponse = new SymfonyTransportResponse($response, $client);

        $chunks = iterator_to_array($transportResponse->getStreamIterator());

        $this->assertSame([
            'data: chunk1' . "\n\n",
            'data: chunk2' . "\n\n",
        ], $chunks);
    }

    public function testGetStreamIteratorThrowsTransportExceptionWhenClientStreamThrowsTransportExceptionInterface(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(HttpClientInterface::class);

        $exception = new class ('Client stream timeout') extends \RuntimeException implements TransportExceptionInterface {
        };

        $client->expects($this->once())
            ->method('stream')
            ->with($response)
            ->willThrowException($exception);

        $transportResponse = new SymfonyTransportResponse($response, $client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to read stream: Client stream timeout');
        $this->expectExceptionCode(0);

        try {
            iterator_to_array($transportResponse->getStreamIterator());
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    public function testGetStreamIteratorThrowsTransportExceptionWhenChunkThrowsTransportExceptionInterface(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(HttpClientInterface::class);

        $exception = new class ('Chunk read timeout') extends \RuntimeException implements TransportExceptionInterface {
        };

        $failingChunk = $this->createMock(ChunkInterface::class);
        $failingChunk->method('isTimeout')->willReturn(false);
        $failingChunk->method('getContent')->willThrowException($exception);

        $generator = (function () use ($response, $failingChunk): \Generator {
            yield $response => $failingChunk;
        })();

        $client->expects($this->once())
            ->method('stream')
            ->with($response)
            ->willReturn(new ResponseStream($generator));

        $transportResponse = new SymfonyTransportResponse($response, $client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to read stream: Chunk read timeout');
        $this->expectExceptionCode(0);

        try {
            iterator_to_array($transportResponse->getStreamIterator());
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    public function testGetStreamIteratorThrowsTransportExceptionWhenChunkThrowsGenericThrowable(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(HttpClientInterface::class);

        $exception = new \RuntimeException('Generic stream breakage');

        $failingChunk = $this->createMock(ChunkInterface::class);
        $failingChunk->method('isTimeout')->willReturn(false);
        $failingChunk->method('getContent')->willThrowException($exception);

        $generator = (function () use ($response, $failingChunk): \Generator {
            yield $response => $failingChunk;
        })();

        $client->expects($this->once())
            ->method('stream')
            ->with($response)
            ->willReturn(new ResponseStream($generator));

        $transportResponse = new SymfonyTransportResponse($response, $client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to read stream: Generic stream breakage');
        $this->expectExceptionCode(0);

        try {
            iterator_to_array($transportResponse->getStreamIterator());
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    public function testGetStreamIteratorRethrowsExistingTransportExceptionDirectly(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(HttpClientInterface::class);

        $transportException = new TransportException('Direct transport error', 42);

        $failingChunk = $this->createMock(ChunkInterface::class);
        $failingChunk->method('isTimeout')->willReturn(false);
        $failingChunk->method('getContent')->willThrowException($transportException);

        $generator = (function () use ($response, $failingChunk): \Generator {
            yield $response => $failingChunk;
        })();

        $client->expects($this->once())
            ->method('stream')
            ->with($response)
            ->willReturn(new ResponseStream($generator));

        $transportResponse = new SymfonyTransportResponse($response, $client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Direct transport error');
        $this->expectExceptionCode(42);

        iterator_to_array($transportResponse->getStreamIterator());
    }

    private function createChunk(string $content, bool $isTimeout): ChunkInterface
    {
        $chunk = $this->createMock(ChunkInterface::class);
        $chunk->method('isTimeout')->willReturn($isTimeout);
        $chunk->method('getContent')->willReturn($content);

        return $chunk;
    }
}
