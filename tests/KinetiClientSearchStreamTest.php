<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use KinetiStack\Sdk\Dto\RagStreamChunkDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Exception\AuthenticationException;
use KinetiStack\Sdk\Exception\AuthorizationException;
use KinetiStack\Sdk\Exception\ServerException;
use KinetiStack\Sdk\Exception\TransportException;
use KinetiStack\Sdk\Exception\ValidationException;
use KinetiStack\Sdk\KinetiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class KinetiClientSearchStreamTest extends TestCase
{
    use FixtureTrait;

    public function testSearchStreamSuccessWithSymfonyClient(): void
    {
        $sseChunks = [
            "data: {\"chunk\": \"Solar energy \"}\n\n",
            "data: {\"chunk\": \"is clean.\"}\n\n",
            "event: done\ndata: [DONE]\n\n",
        ];

        $mockResponse = new MockResponse($sseChunks, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream; charset=UTF-8'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('solar energy benefits')
            ->withLimit(5)
            ->withLocale('en');

        $generator = $kineti->searchStream($query);
        $this->assertInstanceOf(\Generator::class, $generator);

        /** @var RagStreamChunkDto[] $chunks */
        $chunks = [];
        foreach ($generator as $chunk) {
            $this->assertInstanceOf(RagStreamChunkDto::class, $chunk);
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertSame('Solar energy ', $chunks[0]->text);
        $this->assertSame('is clean.', $chunks[1]->text);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/search/rag', $mockResponse->getRequestUrl());

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame('solar energy benefits', $requestBody['query']);
        $this->assertSame(5, $requestBody['limit']);
        $this->assertSame('en', $requestBody['locale']);
        $this->assertTrue($requestBody['stream']);
    }

    public function testSearchStreamOverridesExplicitStreamFalse(): void
    {
        $sseChunks = [
            "data: {\"chunk\": \"Streamed successfully despite false.\"}\n\n",
            "event: done\ndata: [DONE]\n\n",
        ];

        $mockResponse = new MockResponse($sseChunks, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $query = SearchQueryDto::create('Overriding false stream')
            ->withStream(false);

        $this->assertFalse($query->isStreaming());

        $chunks = iterator_to_array($kineti->searchStream($query));

        $this->assertCount(1, $chunks);
        $this->assertSame('Streamed successfully despite false.', $chunks[0]->text);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertTrue($requestBody['stream']);
    }

    public function testSearchStreamWithStringQueryShortcut(): void
    {
        $sseChunks = [
            "data: {\"chunk\": \"Quick answer.\"}\n\n",
            "event: done\ndata: [DONE]\n\n",
        ];

        $mockResponse = new MockResponse($sseChunks, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $chunks = iterator_to_array($kineti->searchStream('What is RAG?'));

        $this->assertCount(1, $chunks);
        $this->assertSame('Quick answer.', $chunks[0]->text);

        $requestBody = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertSame('What is RAG?', $requestBody['query']);
        $this->assertTrue($requestBody['stream']);
    }

    public function testSearchStreamWithCustomPath(): void
    {
        $sseChunks = [
            "data: {\"chunk\": \"Response from query endpoint.\"}\n\n",
            "event: done\ndata: [DONE]\n\n",
        ];

        $mockResponse = new MockResponse($sseChunks, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $client);

        $chunks = iterator_to_array($kineti->searchStream('Custom endpoint test', '/api/v1/search/query'));

        $this->assertCount(1, $chunks);
        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertStringEndsWith('/api/v1/search/query', $mockResponse->getRequestUrl());
    }

    public function testSearchStreamSuccessWithGuzzlePsr18(): void
    {
        $body = "data: {\"chunk\": \"PSR-18 \"}\n\ndata: {\"chunk\": \"streaming works!\"}\n\nevent: done\ndata: [DONE]\n\n";
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'text/event-stream'], $body),
        ]);
        $guzzleClient = new Client(['handler' => HandlerStack::create($mock)]);
        $kineti = new KinetiClient('https://api.test', 'test-api-key', $guzzleClient);

        $generator = $kineti->searchStream('PSR-18 test');
        $this->assertInstanceOf(\Generator::class, $generator);

        /** @var RagStreamChunkDto[] $chunks */
        $chunks = iterator_to_array($generator);

        $this->assertCount(2, $chunks);
        $this->assertSame('PSR-18 ', $chunks[0]->text);
        $this->assertSame('streaming works!', $chunks[1]->text);
    }

    public function testSearchStreamUnauthorized401ThrowsDuringIteration(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_unauthorized_401.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 401,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'invalid-key', $client);

        $generator = $kineti->searchStream('query');
        $this->assertInstanceOf(\Generator::class, $generator);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid or missing API key.');

        iterator_to_array($generator);
    }

    public function testSearchStreamForbidden403ThrowsDuringIteration(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_forbidden_403.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 403,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $generator = $kineti->searchStream('query');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Access denied for this resource or project.');

        iterator_to_array($generator);
    }

    public function testSearchStreamValidationError422ThrowsDuringIteration(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_validation_error_422.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 422,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $generator = $kineti->searchStream('query');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('title: This value should not be blank.');

        iterator_to_array($generator);
    }

    public function testSearchStreamServerError500ThrowsDuringIteration(): void
    {
        $fixture = $this->loadFixture('Errors/rfc9457_server_error_500.json');
        $mockResponse = new MockResponse($fixture, [
            'http_code' => 500,
            'response_headers' => ['Content-Type' => 'application/problem+json'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $generator = $kineti->searchStream('query');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('An unexpected error occurred on the server.');

        iterator_to_array($generator);
    }

    public function testSearchStreamMidStreamErrorChunkThrowsServerException(): void
    {
        $sseChunks = [
            "data: {\"chunk\": \"Intro chunk.\"}\n\n",
            "data: {\"chunk\": \"\\n[Error: Synthesis failed. Ollama backend crashed]\"}\n\n",
            "event: done\ndata: [DONE]\n\n",
        ];

        $mockResponse = new MockResponse($sseChunks, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]);
        $client = new MockHttpClient($mockResponse);
        $kineti = new KinetiClient('https://api.test', 'test-key', $client);

        $generator = $kineti->searchStream('query');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('[Error: Synthesis failed. Ollama backend crashed]');

        foreach ($generator as $chunk) {
            $this->assertSame('Intro chunk.', $chunk->text);
        }
    }
}
