<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient\Transport;

use EasySwoole\EtcdClient\Exception\TransportException;
use EasySwoole\HttpClient\HttpClient;
use JsonException;
use Throwable;

final class EasySwooleTransport implements TransportInterface
{
    public function post(
        string $url,
        array $payload,
        array $headers,
        float $timeout,
        ?string $clientCertificate = null,
        ?string $clientKey = null,
    ): TransportResponse {
        try {
            $client = new HttpClient($url);
            $client->setTimeout($timeout);
            $client->setHeaders($headers, true, false);
            if ($clientCertificate !== null && $clientKey !== null) {
                $client->setSslCertFile($clientCertificate);
                $client->setSslKeyFile($clientKey);
            }
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $response = $client->postJson($json);

            return new TransportResponse($response->getStatusCode(), (string) $response->getBody());
        } catch (JsonException $exception) {
            throw new TransportException('Unable to encode the etcd request.', 0, $exception);
        } catch (Throwable $exception) {
            throw new TransportException('Unable to send the etcd request.', 0, $exception);
        }
    }
}
