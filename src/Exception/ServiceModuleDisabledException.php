<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

class ServiceModuleDisabledException extends KinetiException
{
    public function __construct(
        string $message,
        public readonly string $moduleIdentifier,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 403, $previous);
    }

    public function getModuleIdentifier(): string
    {
        return $this->moduleIdentifier;
    }
}
