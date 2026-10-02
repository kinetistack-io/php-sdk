<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

trait ExtractsTokenUsageTrait
{
    /**
     * Extract token consumption count from an API payload array.
     * Supports 'tokens_consumed', 'tokensConsumed', and 'usage' (scalar or nested object).
     *
     * @param array<string, mixed> $data
     */
    private static function extractTokensConsumed(array $data): ?int
    {
        if (isset($data['tokens_consumed'])) {
            return (int) $data['tokens_consumed'];
        }

        if (isset($data['tokensConsumed'])) {
            return (int) $data['tokensConsumed'];
        }

        if (isset($data['usage'])) {
            if (is_array($data['usage'])) {
                $usageVal = $data['usage']['tokens_consumed']
                    ?? $data['usage']['tokensConsumed']
                    ?? $data['usage']['total_tokens']
                    ?? $data['usage']['totalTokens']
                    ?? $data['usage']['tokens']
                    ?? null;

                return $usageVal !== null ? (int) $usageVal : null;
            }

            if (is_numeric($data['usage'])) {
                return (int) $data['usage'];
            }
        }

        return null;
    }
}
