<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Exception;

class ValidationException extends KinetiException
{
    /**
     * @param array<int, array{propertyPath: string, message: string}> $violations
     */
    public function __construct(
        string $message,
        public readonly array $violations = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 422, $previous);
    }

    /**
     * @return array<int, array{propertyPath: string, message: string}>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
