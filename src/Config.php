<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient;

use InvalidArgumentException;

final class Config
{
    public function __construct(
        public readonly string $endpoint = 'http://127.0.0.1:2379',
        public readonly string $apiVersion = 'v3',
        public readonly float $timeout = 10.0,
        public readonly ?string $clientCertificate = null,
        public readonly ?string $clientKey = null,
    ) {
        if (filter_var($this->endpoint, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('The etcd endpoint must be a valid URL.');
        }
        $scheme = parse_url($this->endpoint, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('The etcd endpoint must use http or https.');
        }
        if (!preg_match('/^v[0-9A-Za-z]+$/', $this->apiVersion)) {
            throw new InvalidArgumentException('The API version must look like "v3" or "v3beta".');
        }
        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('The timeout must be greater than zero.');
        }
        if (($this->clientCertificate === null) !== ($this->clientKey === null)) {
            throw new InvalidArgumentException('A client certificate and key must be provided together.');
        }
    }

    public function url(string $path): string
    {
        return sprintf('%s/%s/%s', rtrim($this->endpoint, '/'), trim($this->apiVersion, '/'), ltrim($path, '/'));
    }
}
