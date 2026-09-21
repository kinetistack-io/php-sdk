<?php

declare(strict_types=1);

namespace KinetiStack\Sdk;

use KinetiStack\Sdk\Dto\AnalyticsDto;
use KinetiStack\Sdk\Enum\AnalyticsGrouping;
use KinetiStack\Sdk\Exception\KinetiException;
use KinetiStack\Sdk\Transport\HttpTransport;
use KinetiStack\Sdk\Transport\TransportInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AnalyticsClient implements AnalyticsClientInterface
{
    private TransportInterface $transport;
    private ?string $apiHost = null;

    /**
     * @param TransportInterface|string $transportOrApiHost Transport instance or API host URL
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $httpClient
     * @param array<string, mixed> $options Default HTTP options
     */
    public function __construct(
        TransportInterface|string $transportOrApiHost,
        private string $jwtToken = '',
        private readonly HttpClientInterface|ClientInterface|TransportInterface|null $httpClient = null,
        private readonly array $options = []
    ) {
        if ($transportOrApiHost instanceof TransportInterface) {
            $this->transport = $this->jwtToken !== ''
                ? $transportOrApiHost->withAuthHeaderValue('Bearer ' . $this->jwtToken)
                : $transportOrApiHost;
        } else {
            $this->apiHost = $transportOrApiHost;
            $this->transport = $this->createTransport(
                $this->apiHost,
                $this->jwtToken,
                $this->httpClient,
                $this->options
            );
        }
    }

    public function withToken(string $jwtToken): static
    {
        $clone = clone $this;
        $clone->jwtToken = $jwtToken;
        $authHeaderValue = $jwtToken !== '' ? 'Bearer ' . $jwtToken : '';

        if ($this->apiHost !== null) {
            $clone->transport = $clone->createTransport(
                $this->apiHost,
                $jwtToken,
                $this->httpClient,
                $this->options
            );
        } else {
            $clone->transport = $this->transport->withAuthHeaderValue($authHeaderValue);
        }

        return $clone;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
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

    /**
     * @param HttpClientInterface|ClientInterface|TransportInterface|null $httpClient
     * @param array<string, mixed> $options
     */
    private function createTransport(
        string $apiHost,
        string $jwtToken,
        HttpClientInterface|ClientInterface|TransportInterface|null $httpClient,
        array $options
    ): TransportInterface {
        $authHeaderValue = $jwtToken !== '' ? 'Bearer ' . $jwtToken : '';

        if ($httpClient instanceof TransportInterface) {
            return $jwtToken !== '' ? $httpClient->withAuthHeaderValue($authHeaderValue) : $httpClient;
        }

        return new HttpTransport($apiHost, 'Authorization', $authHeaderValue, $httpClient, $options);
    }
}
