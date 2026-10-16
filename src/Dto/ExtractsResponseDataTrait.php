<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

/**
 * Trait providing resilient scalar coercion and fallback extraction helpers for Response DTOs.
 *
 * Ensures that unexpected optional response fields (e.g. arrays or objects instead of scalars,
 * or missing values) gracefully fall back to default values without triggering unhandled exceptions.
 */
trait ExtractsResponseDataTrait
{
    /**
     * Safely extract a string from mixed data, falling back to default if non-scalar or not convertible.
     */
    protected static function extractString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Safely extract a nullable string from mixed data, falling back to default if null or non-scalar.
     */
    protected static function extractNullableString(mixed $value, ?string $default = null): ?string
    {
        if ($value === null) {
            return $default;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Safely extract an integer from mixed data, falling back to default if non-numeric or non-scalar.
     */
    protected static function extractInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Safely extract a nullable integer from mixed data.
     */
    protected static function extractNullableInt(mixed $value, ?int $default = null): ?int
    {
        if ($value === null) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Safely extract a float from mixed data.
     */
    protected static function extractFloat(mixed $value, float $default = 0.0): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * Safely extract a nullable float from mixed data.
     */
    protected static function extractNullableFloat(mixed $value, ?float $default = null): ?float
    {
        if ($value === null) {
            return $default;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * Safely extract a boolean from mixed data.
     */
    protected static function extractBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($filtered !== null) {
                return $filtered;
            }
        }

        if (is_scalar($value)) {
            return (bool) $value;
        }

        return $default;
    }

    /**
     * Safely extract a nullable boolean from mixed data.
     */
    protected static function extractNullableBool(mixed $value, ?bool $default = null): ?bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($filtered !== null) {
                return $filtered;
            }
        }

        if (is_scalar($value)) {
            return (bool) $value;
        }

        return $default;
    }

    /**
     * Safely extract an array from mixed data.
     *
     * @param array<string, mixed>|list<mixed> $default
     * @return array<string, mixed>|list<mixed>
     */
    protected static function extractArray(mixed $value, array $default = []): array
    {
        if (is_array($value)) {
            /** @var array<string, mixed>|list<mixed> */
            return $value;
        }

        return $default;
    }

    /**
     * Safely extract a list of strings from mixed data.
     *
     * @param list<string> $default
     * @return list<string>
     */
    protected static function extractStringList(mixed $value, array $default = []): array
    {
        if (!is_array($value)) {
            return $default;
        }

        $list = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $list[] = $item;
            } elseif (is_int($item) || is_float($item) || is_bool($item) || $item instanceof \Stringable) {
                $list[] = (string) $item;
            }
        }

        return $list;
    }
}
