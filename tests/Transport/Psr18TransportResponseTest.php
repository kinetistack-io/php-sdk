<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use GuzzleHttp\Psr7\Response;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\Transport\Psr18TransportResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

#[CoversClass(Psr18TransportResponse::class)]
#[UsesClass(TransportException::class)]
#[UsesClass(KinetiException::class)]
final class Psr18TransportResponseTest extends TestCase
{
    public function testGetStatusCodeSuccess(): void
    {
        $psrResponse = new Response(200);
        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame(200, $response->getStatusCode());

        $psrResponse404 = new Response(404);
        $response404 = new Psr18TransportResponse($psrResponse404);

        $this->assertSame(404, $response404->getStatusCode());
    }

    public function testGetHeadersSuccess(): void
    {
        $headers = [
            'Content-Type' => ['application/json'],
            'X-Custom-Header' => ['value1', 'value2'],
        ];
        $psrResponse = new Response(200, $headers);
        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame($headers, $response->getHeaders());
    }

    public function testGetContentSuccessWithSeekableStream(): void
    {
        $body = '{"message":"hello world"}';
        $psrResponse = new Response(200, [], $body);
        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame($body, $response->getContent());
    }

    public function testGetContentSuccessWithNonSeekableStream(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->expects($this->never())->method('rewind');
        $stream->expects($this->once())->method('getContents')->willReturn('non-seekable body');

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getBody')->willReturn($stream);

        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame('non-seekable body', $response->getContent());
    }

    public function testGetContentCachingBehavior(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->expects($this->once())->method('rewind');
        $stream->expects($this->once())->method('getContents')->willReturn('cached content body');

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getBody')->willReturn($stream);

        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame('cached content body', $response->getContent());
        $this->assertSame('cached content body', $response->getContent());
        $this->assertSame('cached content body', $response->getContent());
    }

    public function testGetContentThrowsTransportExceptionWhenStreamThrows(): void
    {
        $streamException = new \RuntimeException('Disk I/O error');

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('getContents')->willThrowException($streamException);

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getBody')->willReturn($stream);

        $response = new Psr18TransportResponse($psrResponse);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to read response body: Disk I/O error');
        $this->expectExceptionCode(0);

        try {
            $response->getContent();
        } catch (TransportException $e) {
            $this->assertSame($streamException, $e->getPrevious());
            throw $e;
        }
    }

    public function testToArraySuccessWithJsonObject(): void
    {
        $body = '{"id":"abc-123","count":10,"active":true}';
        $psrResponse = new Response(200, [], $body);
        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame([
            'id' => 'abc-123',
            'count' => 10,
            'active' => true,
        ], $response->toArray());
    }

    public function testToArraySuccessWithJsonList(): void
    {
        $body = '[{"name":"item1"},{"name":"item2"}]';
        $psrResponse = new Response(200, [], $body);
        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame([
            ['name' => 'item1'],
            ['name' => 'item2'],
        ], $response->toArray());
    }

    public function testToArrayReturnsEmptyArrayWhenBodyIsEmpty(): void
    {
        $psrResponse = new Response(200, [], '');
        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame([], $response->toArray());
    }

    public function testToArrayReturnsEmptyArrayWhenBodyIsWhitespaceOnly(): void
    {
        $psrResponse = new Response(200, [], "   \n\t  ");
        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame([], $response->toArray());
    }

    #[DataProvider('provideJsonScalars')]
    public function testToArrayThrowsTransportExceptionWhenBodyIsJsonScalar(string $scalarJson): void
    {
        $psrResponse = new Response(200, [], $scalarJson);
        $response = new Psr18TransportResponse($psrResponse);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('JSON response is not an array.');
        $this->expectExceptionCode(0);

        $response->toArray();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provideJsonScalars(): array
    {
        return [
            'integer' => ['123'],
            'float' => ['45.67'],
            'boolean true' => ['true'],
            'boolean false' => ['false'],
            'null' => ['null'],
            'string' => ['"just a string"'],
        ];
    }

    public function testToArrayThrowsTransportExceptionWhenBodyIsInvalidJson(): void
    {
        $invalidJson = '{ broken json: true';
        $psrResponse = new Response(200, [], $invalidJson);
        $response = new Psr18TransportResponse($psrResponse);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to decode JSON response: Syntax error');
        $this->expectExceptionCode(0);

        try {
            $response->toArray();
        } catch (TransportException $e) {
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
            throw $e;
        }
    }

    public function testToArrayPropagatesTransportExceptionWhenGetContentFails(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('getContents')->willThrowException(new \RuntimeException('Network socket reset'));

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getBody')->willReturn($stream);

        $response = new Psr18TransportResponse($psrResponse);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to read response body: Network socket reset');

        $response->toArray();
    }

    public function testGetInnerResponseReturnsWrappedResponse(): void
    {
        $psrResponse = new Response(200);
        $response = new Psr18TransportResponse($psrResponse);

        $this->assertSame($psrResponse, $response->getInnerResponse());
    }

    public function testGetStreamIteratorChunkIterationWithSeekableStream(): void
    {
        $data = str_repeat('A', 8192) . str_repeat('B', 8192) . str_repeat('C', 100);
        $psrResponse = new Response(200, [], $data);
        $response = new Psr18TransportResponse($psrResponse);

        $chunks = iterator_to_array($response->getStreamIterator());

        $this->assertCount(3, $chunks);
        $this->assertSame(str_repeat('A', 8192), $chunks[0]);
        $this->assertSame(str_repeat('B', 8192), $chunks[1]);
        $this->assertSame(str_repeat('C', 100), $chunks[2]);
        $this->assertSame($data, implode('', $chunks));
    }

    public function testGetStreamIteratorChunkIterationWithNonSeekableStreamAndEmptyChunks(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->expects($this->never())->method('rewind');

        $stream->expects($this->exactly(4))
            ->method('eof')
            ->willReturnOnConsecutiveCalls(false, false, false, true);

        $stream->expects($this->exactly(3))
            ->method('read')
            ->with(8192)
            ->willReturnOnConsecutiveCalls('chunk-alpha', '', 'chunk-beta');

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getBody')->willReturn($stream);

        $response = new Psr18TransportResponse($psrResponse);

        $chunks = iterator_to_array($response->getStreamIterator());

        $this->assertSame(['chunk-alpha', 'chunk-beta'], $chunks);
    }

    public function testGetStreamIteratorWhenCachedContentNotNullYieldsFromCachedContent(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->expects($this->once())->method('getContents')->willReturn('previously cached stream body');
        $stream->expects($this->never())->method('read');

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getBody')->willReturn($stream);

        $response = new Psr18TransportResponse($psrResponse);

        // Warm the cache first
        $this->assertSame('previously cached stream body', $response->getContent());

        // Now stream iterator should yield from $cachedContent without invoking $stream->read()
        $chunks = iterator_to_array($response->getStreamIterator());

        $this->assertSame(['previously cached stream body'], $chunks);
    }

    public function testGetStreamIteratorThrowsTransportExceptionWhenStreamReadThrowsGenericThrowable(): void
    {
        $exception = new \RuntimeException('Read stream failure');

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->willThrowException($exception);

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getBody')->willReturn($stream);

        $response = new Psr18TransportResponse($psrResponse);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to read response stream: Read stream failure');
        $this->expectExceptionCode(0);

        try {
            iterator_to_array($response->getStreamIterator());
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    public function testGetStreamIteratorRethrowsExistingTransportExceptionDirectly(): void
    {
        $transportException = new TransportException('Direct stream error', 99);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->willThrowException($transportException);

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getBody')->willReturn($stream);

        $response = new Psr18TransportResponse($psrResponse);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Direct stream error');
        $this->expectExceptionCode(99);

        iterator_to_array($response->getStreamIterator());
    }

    public function testGetStreamIteratorWhenCachedContentIsEmptyStringYieldsEmptyString(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('');

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getBody')->willReturn($stream);

        $response = new Psr18TransportResponse($psrResponse);
        $this->assertSame('', $response->getContent());

        $chunks = iterator_to_array($response->getStreamIterator());
        $this->assertSame([''], $chunks);
    }
}
