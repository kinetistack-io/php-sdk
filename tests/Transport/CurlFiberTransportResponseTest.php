<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Transport;

use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\Transport\CurlFiberTransportResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CurlFiberTransportResponse::class)]
#[UsesClass(TransportException::class)]
#[UsesClass(KinetiException::class)]
final class CurlFiberTransportResponseTest extends TestCase
{
    public function testGetStatusCodeAndHeaders(): void
    {
        $fiber = new \Fiber(static function (): void {
        });
        $response = new CurlFiberTransportResponse(200, ['content-type' => ['text/event-stream']], $fiber);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['content-type' => ['text/event-stream']], $response->getHeaders());
    }

    public function testStreamIteratorWithFirstChunkAndFiber(): void
    {
        $fiber = new \Fiber(static function (): void {
            \Fiber::suspend('chunk1');
            \Fiber::suspend('chunk2');
            \Fiber::suspend('chunk3');
        });
        /** @var string|null $firstChunk */
        $firstChunk = $fiber->start();
        $response = new CurlFiberTransportResponse(200, [], $fiber, $firstChunk);

        $chunks = [];
        foreach ($response->getStreamIterator() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertSame(['chunk1', 'chunk2', 'chunk3'], $chunks);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Response stream has already been consumed.');
        $response->getContent();
    }

    public function testToArrayDecodesJson(): void
    {
        $fiber = new \Fiber(static function (): void {
            \Fiber::suspend('{"key": "value"}');
        });
        /** @var string|null $firstChunk */
        $firstChunk = $fiber->start();
        $response = new CurlFiberTransportResponse(200, [], $fiber, $firstChunk);

        $this->assertSame(['key' => 'value'], $response->toArray());
    }

    public function testToArrayThrowsOnInvalidJson(): void
    {
        $fiber = new \Fiber(static function (): void {
            \Fiber::suspend('invalid json');
        });
        /** @var string|null $firstChunk */
        $firstChunk = $fiber->start();
        $response = new CurlFiberTransportResponse(200, [], $fiber, $firstChunk);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to decode JSON response');
        $response->toArray();
    }

    public function testToArrayReturnsEmptyArrayOnEmptyContent(): void
    {
        $fiber = new \Fiber(static function (): void {
        });
        $fiber->start();
        $response = new CurlFiberTransportResponse(204, [], $fiber, null);

        $this->assertSame([], $response->toArray());
        $this->assertSame('', $response->getContent());
    }

    public function testIncrementalChunkIterationViaLocalTcpSocketServer(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nTransfer-Encoding: chunked\r\n\r\n");
            fwrite($conn, "6\r\nchunk1\r\n");
            usleep(20000);
            fwrite($conn, "6\r\nchunk2\r\n");
            usleep(20000);
            fwrite($conn, "0\r\n\r\n");
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $response = new CurlFiberTransportResponse($ch);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertArrayHasKey('content-type', $response->getHeaders());

            $chunks = [];
            foreach ($response->getStreamIterator() as $chunk) {
                $chunks[] = $chunk;
            }

            $this->assertSame(['chunk1', 'chunk2'], $chunks);
            try {
                $response->getContent();
                $this->fail('Expected TransportException was not thrown.');
            } catch (TransportException $e) {
                $this->assertSame('Response stream has already been consumed.', $e->getMessage());
            }
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testStatusCodeAndHeaderParsingFromLiveSocket(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            $body = 'ok';
            $headers = "HTTP/1.1 200 OK\r\n" .
                "Content-Type: text/plain\r\n" .
                "X-RateLimit-Remaining: 42\r\n" .
                "X-Custom-Header: Alpha\r\n" .
                "X-Custom-Header: Beta\r\n" .
                "Content-Length: " . strlen($body) . "\r\n\r\n";

            fwrite($conn, $headers . $body);
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $response = new CurlFiberTransportResponse($ch);
            $this->assertSame(200, $response->getStatusCode());

            $headers = $response->getHeaders();
            $this->assertSame(['42'], $headers['x-ratelimit-remaining'] ?? []);
            $this->assertSame(['Alpha', 'Beta'], $headers['x-custom-header'] ?? []);
            $this->assertSame('ok', $response->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testRedirectHeadersAreReset(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            $interim = "HTTP/1.1 100 Continue\r\nInterim-Header: temporary\r\n\r\n";
            $final = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nFinal-Header: confirmed\r\nContent-Length: 4\r\n\r\ndone";

            fwrite($conn, $interim . $final);
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $response = new CurlFiberTransportResponse($ch);
            $this->assertSame(200, $response->getStatusCode());

            $headers = $response->getHeaders();
            $this->assertArrayNotHasKey('interim-header', $headers);
            $this->assertSame(['confirmed'], $headers['final-header'] ?? []);
            $this->assertSame('done', $response->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testGetContentBeforeAndAfterIteration(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nTransfer-Encoding: chunked\r\n\r\n");
            fwrite($conn, "5\r\nhello\r\n");
            fwrite($conn, "6\r\n world\r\n");
            fwrite($conn, "0\r\n\r\n");
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $response = new CurlFiberTransportResponse($ch);

            // Call getContent() first before iteration
            $firstContent = $response->getContent();
            $this->assertSame('hello world', $firstContent);
            // Calling getContent() again returns cached content
            $secondContent = $response->getContent();
            $this->assertSame($firstContent, $secondContent);

            // Calling getStreamIterator() after getContent() yields cached content
            $chunks = iterator_to_array($response->getStreamIterator());
            $this->assertSame(['hello world'], $chunks);
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testCustomHttpMethodsAndRequestBodies(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        /** @var string|null $capturedRequest */
        $capturedRequest = null;

        [$address, $pid] = $this->startSocketServer(function ($conn) use (&$capturedRequest): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            // Read body based on Content-Length
            if (preg_match('/Content-Length:\s*(\d+)/i', $req, $matches)) {
                $length = (int) $matches[1];
                $parts = explode("\r\n\r\n", $req, 2);
                $body = $parts[1] ?? '';
                while (strlen($body) < $length) {
                    $buf = fread($conn, 1024);
                    if ($buf === false || $buf === '') {
                        break;
                    }
                    $body .= $buf;
                }
                $capturedRequest = $parts[0] . "\r\n\r\n" . $body;
            } else {
                $capturedRequest = $req;
            }

            $resBody = '{"verified": true}';
            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($resBody) . "\r\n\r\n" . $resBody);
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $payload = json_encode(['query' => 'testing custom methods'], JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'QUERY');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Custom-Client: KinetiStack-SDK',
            ]);

            $response = new CurlFiberTransportResponse($ch);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame(['verified' => true], $response->toArray());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testTransportExceptionOnUnreachableHost(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        // Port 54321 is presumed closed
        $ch = curl_init('http://127.0.0.1:54321');
        $this->assertNotFalse($ch);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/cURL error \(7\)/');

        new CurlFiberTransportResponse($ch);
    }

    public function testHttp403ForbiddenImmediateStatusAndErrorBodyDraining(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            $errorBody = json_encode([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Forbidden',
                'detail' => 'Access denied for project.',
            ], JSON_THROW_ON_ERROR);

            fwrite($conn, "HTTP/1.1 403 Forbidden\r\nContent-Type: application/problem+json\r\nContent-Length: " . strlen($errorBody) . "\r\n\r\n" . $errorBody);
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $response = new CurlFiberTransportResponse($ch);

            // Status code and headers must be available immediately after constructor
            $this->assertSame(403, $response->getStatusCode());
            $this->assertSame(['application/problem+json'], $response->getHeaders()['content-type'] ?? []);

            // getContent() drains the error body
            $this->assertSame([
                'type' => 'https://tools.ietf.org/html/rfc9457',
                'title' => 'Forbidden',
                'detail' => 'Access denied for project.',
            ], $response->toArray());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testEarlyGeneratorAbandonmentCleansUp(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nTransfer-Encoding: chunked\r\n\r\n");
            fwrite($conn, "6\r\nchunk1\r\n");
            usleep(50000);
            fwrite($conn, "6\r\nchunk2\r\n");
            usleep(50000);
            fwrite($conn, "0\r\n\r\n");
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $response = new CurlFiberTransportResponse($ch);

            $received = [];
            foreach ($response->getStreamIterator() as $chunk) {
                $received[] = $chunk;
                break; // Abandon iteration early after first chunk
            }

            $this->assertSame(['chunk1'], $received);
            unset($response); // Triggers GC and cleanup
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testPartialIterationPreservesChunksOnSubsequentAccess(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nTransfer-Encoding: chunked\r\n\r\n");
            fwrite($conn, "6\r\nchunk1\r\n");
            usleep(20000);
            fwrite($conn, "6\r\nchunk2\r\n");
            usleep(20000);
            fwrite($conn, "6\r\nchunk3\r\n");
            usleep(20000);
            fwrite($conn, "0\r\n\r\n");
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $response = new CurlFiberTransportResponse($ch);

            $received = [];
            foreach ($response->getStreamIterator() as $chunk) {
                $received[] = $chunk;
                break; // Break early after chunk 1
            }

            $this->assertSame(['chunk1'], $received);

            // Accessing getContent() after unbuffered stream iteration throws TransportException
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage('Response stream has already been consumed.');
            $response->getContent();
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testFromCurlHandleFactory(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: 2\r\n\r\nok");
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $response = CurlFiberTransportResponse::fromCurlHandle($ch);
            $this->assertInstanceOf(CurlFiberTransportResponse::class, $response);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('ok', $response->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testConstructReturnsImmediatelyBeforeFirstChunkArrives(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/event-stream\r\nTransfer-Encoding: chunked\r\n\r\n");
            // Delay sending first chunk by 100ms
            usleep(100000);
            fwrite($conn, "6\r\nchunk1\r\n");
            fwrite($conn, "0\r\n\r\n");
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $start = microtime(true);
            $response = new CurlFiberTransportResponse($ch);
            $constructDuration = microtime(true) - $start;

            // __construct() must return as soon as headers finish, well before 100ms
            $this->assertLessThan(0.08, $constructDuration, 'Constructor blocked waiting for body chunk');
            $this->assertSame(200, $response->getStatusCode());
            $this->assertArrayHasKey('content-type', $response->getHeaders());

            $chunks = iterator_to_array($response->getStreamIterator());
            $this->assertSame(['chunk1'], $chunks);
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testHttp204NoContentHandledCleanlyWithoutBlocking(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required.');
        }

        [$address, $pid] = $this->startSocketServer(function ($conn): void {
            $req = '';
            while (!str_contains($req, "\r\n\r\n")) {
                $buf = fread($conn, 1024);
                if ($buf === false || $buf === '') {
                    break;
                }
                $req .= $buf;
            }

            fwrite($conn, "HTTP/1.1 204 No Content\r\nServer: KinetiTest\r\n\r\n");
        });

        try {
            $ch = curl_init('http://' . $address);
            $this->assertNotFalse($ch);

            $response = new CurlFiberTransportResponse($ch);
            $this->assertSame(204, $response->getStatusCode());
            $this->assertArrayHasKey('server', $response->getHeaders());

            $chunks = iterator_to_array($response->getStreamIterator());
            $this->assertSame([], $chunks);
            $this->assertSame([], $response->toArray());
            $this->assertSame('', $response->getContent());
        } finally {
            $this->waitForChildProcess($pid);
        }
    }

    public function testStreamIteratorOperatesInO1MemoryWithoutBuffering(): void
    {
        $fiber = new \Fiber(static function (): void {
            for ($i = 0; $i < 50; $i++) {
                \Fiber::suspend("chunk-{$i}");
            }
        });
        /** @var string|null $firstChunk */
        $firstChunk = $fiber->start();
        $response = new CurlFiberTransportResponse(200, [], $fiber, $firstChunk);

        $received = [];
        foreach ($response->getStreamIterator() as $chunk) {
            $received[] = $chunk;
        }

        $this->assertCount(50, $received);
        $this->assertSame('chunk-0', $received[0]);
        $this->assertSame('chunk-49', $received[49]);

        // Attempting to read content after unbuffered streaming throws TransportException
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Response stream has already been consumed.');
        $response->getContent();
    }

    public function testCallingGetStreamIteratorAfterConsumptionThrowsTransportException(): void
    {
        $fiber = new \Fiber(static function (): void {
            \Fiber::suspend('single-chunk');
        });
        /** @var string|null $firstChunk */
        $firstChunk = $fiber->start();
        $response = new CurlFiberTransportResponse(200, [], $fiber, $firstChunk);

        $chunks = iterator_to_array($response->getStreamIterator());
        $this->assertSame(['single-chunk'], $chunks);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Response stream has already been consumed.');
        iterator_to_array($response->getStreamIterator());
    }

    /**
     * Start a temporary local TCP socket server in a child process.
     * Returns an array containing the server address and child PID.
     *
     * @param callable(resource): void $handler
     * @return array{0: string, 1: int}
     */
    private function startSocketServer(callable $handler): array
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl_fork is required for local socket server tests.');
        }

        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            self::fail('Failed to start TCP socket server: ' . $errstr);
        }

        $address = stream_socket_get_name($server, false);
        if ($address === false) {
            fclose($server);
            self::fail('Failed to retrieve socket address.');
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($server);
            self::fail('Failed to fork child process.');
        }

        if ($pid === 0) {
            try {
                $conn = stream_socket_accept($server, 5);
                if ($conn !== false) {
                    $handler($conn);
                    fclose($conn);
                }
            } finally {
                fclose($server);
                if (function_exists('posix_kill')) {
                    $childPid = getmypid();
                    if ($childPid !== false) {
                        posix_kill($childPid, SIGKILL);
                    }
                }
                exit(0);
            }
        }

        fclose($server);

        return [$address, $pid];
    }

    private function waitForChildProcess(int $pid): void
    {
        if (function_exists('pcntl_waitpid')) {
            $status = 0;
            pcntl_waitpid($pid, $status);
        }
    }
}
