<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient\Tests;

use EasySwoole\EtcdClient\Transport\TransportInterface;
use EasySwoole\EtcdClient\Transport\TransportResponse;

final class FakeTransport implements TransportInterface
{
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    public function __construct(private TransportResponse $response = new TransportResponse(200, '{}'))
    {
    }

    public function respondWith(int $statusCode, array|string $body): void
    {
        $this->response = new TransportResponse(
            $statusCode,
            is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    public function post(
        string $url,
        array $payload,
        array $headers,
        float $timeout,
    ): TransportResponse {
        $this->requests[] = [
            'url' => $url,
            'payload' => $payload,
            'headers' => $headers,
            'timeout' => $timeout,
        ];

        return $this->response;
    }

    /** @return array<string, mixed> */
    public function lastRequest(): array
    {
        return $this->requests[array_key_last($this->requests)];
    }
}
