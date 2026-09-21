<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Client;

use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\Transport\SseParser;
use PHPUnit\Framework\TestCase;

class StreamParsingTest extends TestCase
{
    private SseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SseParser();
    }

    public function testParseCleanSseStream(): void
    {
        $chunks = [
            "data: {\"chunk\": \"KinetiStack \"}\n\n",
            "data: {\"chunk\": \"uses \"}\n\n",
            "data: {\"chunk\": \"Symfony 8.\"}\n\n",
            "event: done\ndata: [DONE]\n\n",
        ];

        $generator = $this->parser->parse($chunks);
        $this->assertInstanceOf(\Generator::class, $generator);

        /** @var RagStreamChunkDto[] $results */
        $results = iterator_to_array($generator);

        $this->assertCount(3, $results);
        $this->assertSame('KinetiStack ', $results[0]->text);
        $this->assertSame('uses ', $results[1]->text);
        $this->assertSame('Symfony 8.', $results[2]->text);

        $assembled = implode('', array_map(static fn (RagStreamChunkDto $d) => $d->text, $results));
        $this->assertSame('KinetiStack uses Symfony 8.', $assembled);
    }

    public function testParseFragmentedChunksAcrossBoundaries(): void
    {
        $fragments = [
            'da',
            "ta: {\"chunk\": \"Hello",
            " world\"}\n",
            "\n",
            'da',
            "ta: {\"chunk\": \"!\"}\n\nevent: ",
            "done\ndata: [DO",
            "NE]\n\n",
        ];

        /** @var RagStreamChunkDto[] $results */
        $results = iterator_to_array($this->parser->parse($fragments));

        $this->assertCount(2, $results);
        $this->assertSame('Hello world', $results[0]->text);
        $this->assertSame('!', $results[1]->text);
    }

    public function testParseWithCrlfLineEndings(): void
    {
        $chunks = [
            "data: {\"chunk\": \"CRLF line 1\"}\r\n\r\n",
            "data: {\"chunk\": \"CRLF line 2\"}\r\n\r\n",
            "event: done\r\ndata: [DONE]\r\n\r\n",
        ];

        $results = iterator_to_array($this->parser->parse($chunks));

        $this->assertCount(2, $results);
        $this->assertSame('CRLF line 1', $results[0]->text);
        $this->assertSame('CRLF line 2', $results[1]->text);
    }

    public function testIgnoresCommentsAndEmptyLines(): void
    {
        $chunks = [
            ": ping\n\n",
            ": keep-alive\n",
            "\n",
            "data: {\"chunk\": \"Content after comments\"}\n\n",
            "event: done\ndata: [DONE]\n\n",
        ];

        $results = iterator_to_array($this->parser->parse($chunks));

        $this->assertCount(1, $results);
        $this->assertSame('Content after comments', $results[0]->text);
    }

    public function testTerminatesOnDataDoneWithoutEventDone(): void
    {
        $chunks = [
            "data: {\"chunk\": \"Sole chunk\"}\n\n",
            "data: [DONE]\n\n",
            "data: {\"chunk\": \"Should not be parsed\"}\n\n",
        ];

        $results = iterator_to_array($this->parser->parse($chunks));

        $this->assertCount(1, $results);
        $this->assertSame('Sole chunk', $results[0]->text);
    }

    public function testServerErrorEventThrowsServerException(): void
    {
        $chunks = [
            "data: {\"chunk\": \"Beginning text\"}\n\n",
            "event: error\ndata: LLM service connection terminated unexpectedly\n\n",
        ];

        $generator = $this->parser->parse($chunks);

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('LLM service connection terminated unexpectedly');

        foreach ($generator as $chunk) {
            $this->assertSame('Beginning text', $chunk->text);
        }
    }

    public function testServerSynthesisErrorChunkThrowsServerException(): void
    {
        $chunks = [
            "data: {\"chunk\": \"Starting synthesis...\"}\n\n",
            "data: {\"chunk\": \"\\n[Error: Synthesis failed. Ollama process timed out]\"}\n\n",
            "event: done\ndata: [DONE]\n\n",
        ];

        $generator = $this->parser->parse($chunks);

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('[Error: Synthesis failed. Ollama process timed out]');

        foreach ($generator as $chunk) {
            // First chunk succeeds
            $this->assertSame('Starting synthesis...', $chunk->text);
        }
    }

    public function testJsonErrorFieldThrowsServerException(): void
    {
        $chunks = [
            "data: {\"error\": \"Token quota exhausted\"}\n\n",
        ];

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Token quota exhausted');

        iterator_to_array($this->parser->parse($chunks));
    }

    public function testPlainPlainTextErrorThrowsServerException(): void
    {
        $chunks = [
            "data: [Error: Internal model error]\n\n",
        ];

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('[Error: Internal model error]');

        iterator_to_array($this->parser->parse($chunks));
    }

    public function testPlainTextDataYieldsChunk(): void
    {
        $chunks = [
            "data: Raw text chunk without JSON\n\n",
            "event: done\ndata: [DONE]\n\n",
        ];

        $results = iterator_to_array($this->parser->parse($chunks));

        $this->assertCount(1, $results);
        $this->assertSame('Raw text chunk without JSON', $results[0]->text);
    }

    public function testTrailingBufferWithoutTrailingNewlinesYieldsChunk(): void
    {
        $chunks = [
            "data: {\"chunk\": \"Final chunk without trailing double newline\"}",
        ];

        $results = iterator_to_array($this->parser->parse($chunks));

        $this->assertCount(1, $results);
        $this->assertSame('Final chunk without trailing double newline', $results[0]->text);
    }

    public function testNetworkDropPropagatesTransportExceptionDuringIteration(): void
    {
        $failingStream = function (): \Generator {
            yield "data: {\"chunk\": \"First chunk\"}\n\n";
            throw new TransportException('Connection reset by peer');
        };

        $generator = $this->parser->parse($failingStream());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Connection reset by peer');

        foreach ($generator as $chunk) {
            $this->assertSame('First chunk', $chunk->text);
        }
    }
}
