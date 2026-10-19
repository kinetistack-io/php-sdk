<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Client\ClientHelperTrait;
use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Enum\AnalyticsGrouping;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\TransportInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AnalyticsClient implements AnalyticsClientInterface
{
    use ClientHelperTrait;

    private TransportInterface $transport;

    /**
     * @param TransportInterface|string $transportOrApiHost Transport instance or API host URL
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $httpClient
     * @param array<string, mixed> $options Default HTTP options
     */
    public function __construct(
        TransportInterface|string $transportOrApiHost,
        #[\SensitiveParameter]
        private string $jwtToken = '',
        private readonly HttpClientInterface|ClientInterface|TransportInterface|null $httpClient = null,
        private readonly array $options = []
    ) {
        $this->transport = $this->createBearerTransport(
            is_string($transportOrApiHost) ? $transportOrApiHost : '',
            $this->jwtToken,
            $transportOrApiHost instanceof TransportInterface ? $transportOrApiHost : null,
            $this->httpClient,
            $this->options
        );
    }

    public function withToken(#[\SensitiveParameter] string $jwtToken): static
    {
        $clone = clone $this;
        $clone->jwtToken = $jwtToken;
        $authHeaderValue = $jwtToken !== '' ? 'Bearer ' . $jwtToken : '';

        $clone->transport = $this->transport->withAuthHeaderValue($authHeaderValue);

        return $clone;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $info = get_object_vars($this);
        if ($this->jwtToken !== '') {
            $info['jwtToken'] = '[REDACTED]';
        }

        return $info;
    }

    /**
     * Query aggregated usage analytics buckets.
     *
     * @throws KinetiException
     */
    public function getAnalytics(
        AnalyticsGrouping $grouping = AnalyticsGrouping::DAY,
        ?string $from = null,
        ?string $to = null,
        ?string $projectId = null
    ): AnalyticsDto {
        $query = [
            'group_by' => $grouping->value,
        ];

        if ($from !== null) {
            $query['from'] = $from;
        }
        if ($to !== null) {
            $query['to'] = $to;
        }
        if ($projectId !== null) {
            $query['project_id'] = $projectId;
        }

        $response = $this->transport->request('GET', '/api/v1/admin/analytics', [
            'query' => $query,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return AnalyticsDto::fromArray($data);
    }
}
