<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

/**
 * Configuration options for the KinetiStack SDK client.
 */
final class Configuration
{
    private bool $useHttpQueryMethod;

    /**
     * @var array<string, mixed>
     */
    private array $httpOptions;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        array $options = [],
        ?bool $useHttpQueryMethod = null,
    ) {
        if ($useHttpQueryMethod !== null) {
            $this->useHttpQueryMethod = $useHttpQueryMethod;
        } else {
            $raw = $options['use_http_query_method'] ?? $options['useHttpQueryMethod'] ?? null;
            if ($raw !== null) {
                $this->useHttpQueryMethod = (bool) filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            } else {
                $this->useHttpQueryMethod = true;
            }
        }

        $httpOptions = $options;
        unset($httpOptions['use_http_query_method'], $httpOptions['useHttpQueryMethod']);
        $this->httpOptions = $httpOptions;
    }

    /**
     * Create a Configuration instance from an options array or existing Configuration.
     *
     * @param array<string, mixed>|self $options
     */
    public static function fromOptions(array|self $options): self
    {
        if ($options instanceof self) {
            return $options;
        }

        return new self($options);
    }

    /**
     * Whether the SDK dispatches search and RAG queries using RFC 10008 HTTP QUERY method.
     */
    public function useHttpQueryMethod(): bool
    {
        return $this->useHttpQueryMethod;
    }

    /**
     * Return a new immutable instance with the specified HTTP QUERY method preference.
     */
    public function withHttpQueryMethod(bool $useHttpQueryMethod): static
    {
        $clone = clone $this;
        $clone->useHttpQueryMethod = $useHttpQueryMethod;

        return $clone;
    }

    /**
     * Get underlying HTTP client options.
     *
     * @return array<string, mixed>
     */
    public function getHttpOptions(): array
    {
        return $this->httpOptions;
    }

    /**
     * Return a new immutable instance with updated HTTP options.
     *
     * @param array<string, mixed> $httpOptions
     */
    public function withHttpOptions(array $httpOptions): static
    {
        $clone = clone $this;
        $clone->httpOptions = $httpOptions;

        return $clone;
    }

    /**
     * Export configuration options to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->httpOptions, [
            'use_http_query_method' => $this->useHttpQueryMethod,
        ]);
    }
}
