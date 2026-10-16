<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Dto;

class HealthStatusDto
{
    use ExtractsResponseDataTrait;

    /**
     * @param array<string, string>|null $checks
     */
    public function __construct(
        public readonly string $status,
        public readonly ?array $checks = null,
    ) {
    }

    public function isHealthy(): bool
    {
        return in_array(strtolower($this->status), ['ok', 'ready', 'healthy'], true);
    }

    public function isReady(): bool
    {
        if (!in_array(strtolower($this->status), ['ok', 'ready', 'healthy'], true)) {
            return false;
        }

        if ($this->checks !== null) {
            foreach ($this->checks as $checkStatus) {
                if (!in_array(strtolower($checkStatus), ['ok', 'ready', 'healthy'], true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function getChecks(): array
    {
        return $this->checks ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'status' => $this->status,
        ];

        if ($this->checks !== null) {
            $data['checks'] = $this->checks;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = self::extractString($data['status'] ?? null, '');

        $checks = null;
        if (isset($data['checks']) && is_array($data['checks'])) {
            $checks = [];
            foreach ($data['checks'] as $k => $v) {
                if (is_scalar($v)) {
                    $checks[(string) $k] = (string) $v;
                }
            }
        }

        return new self($status, $checks);
    }
}
