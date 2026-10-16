<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient\Transport;

interface TransportInterface
{
    /** @param array<string, mixed> $payload @param array<string, string> $headers */
    public function post(
        string $url,
        array $payload,
        array $headers,
        float $timeout,
        ?string $clientCertificate = null,
        ?string $clientKey = null,
    ): TransportResponse;
}
