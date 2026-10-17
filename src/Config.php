<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient;

use InvalidArgumentException;

final class Config
{
    public readonly string $endpoint;
    public readonly string $apiVersion;
    public readonly float $timeout;
    public readonly ?TlsConfig $tls;

    public function __construct(
        string $endpoint = 'http://127.0.0.1:2379',
        string $apiVersion = 'v3',
        float $timeout = 10.0,
        ?TlsConfig $tls = null,
    ) {
        $endpoint = rtrim(trim($endpoint), '/');
        $apiVersion = trim($apiVersion, '/');

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('The etcd endpoint must be a valid URL.');
        }
        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('The etcd endpoint must use http or https.');
        }
        if (!preg_match('/^v[0-9A-Za-z]+$/', $apiVersion)) {
            throw new InvalidArgumentException('The API version must look like "v3" or "v3beta".');
        }
        if ($timeout <= 0) {
            throw new InvalidArgumentException('The timeout must be greater than zero.');
        }
        if ($tls !== null && $scheme !== 'https') {
            throw new InvalidArgumentException('TLS configuration requires an https endpoint.');
        }

        $this->endpoint = $endpoint;
        $this->apiVersion = $apiVersion;
        $this->timeout = $timeout;
        // HTTPS 默认使用严格证书校验；显式传入 TlsConfig 时才调整策略。
        $this->tls = $scheme === 'https' ? ($tls ?? new TlsConfig()) : null;
    }

    public function url(string $path): string
    {
        return sprintf('%s/%s/%s', $this->endpoint, $this->apiVersion, ltrim($path, '/'));
    }
}
