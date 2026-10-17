<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient;

use EasySwoole\HttpClient\HttpClient;
use InvalidArgumentException;

final class TlsConfig
{
    public function __construct(
        public readonly bool $verifyPeer = true,
        public readonly bool $allowSelfSigned = false,
        public readonly ?string $caFile = null,
        public readonly ?string $caPath = null,
        public readonly ?string $clientCertificate = null,
        public readonly ?string $clientPrivateKey = null,
        public readonly ?string $passphrase = null,
        public readonly ?string $serverName = null,
    ) {
        if ($this->allowSelfSigned && !$this->verifyPeer) {
            throw new InvalidArgumentException('allowSelfSigned requires verifyPeer to be enabled.');
        }
        if (($this->clientCertificate === null) !== ($this->clientPrivateKey === null)) {
            throw new InvalidArgumentException(
                'clientCertificate and clientPrivateKey must be configured together.',
            );
        }
        if ($this->passphrase !== null && $this->clientPrivateKey === null) {
            throw new InvalidArgumentException('passphrase requires a client private key.');
        }

        foreach ([
            'caFile' => $this->caFile,
            'caPath' => $this->caPath,
            'clientCertificate' => $this->clientCertificate,
            'clientPrivateKey' => $this->clientPrivateKey,
            'serverName' => $this->serverName,
        ] as $name => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException("{$name} cannot be empty.");
            }
        }
    }

    public function applyTo(HttpClient $client): void
    {
        // etcd 通常使用 2379 端口，不能依赖底层仅对 443 自动开启 SSL。
        $client->setEnableSSL(true);
        $client->setSslVerifyPeer($this->verifyPeer, $this->allowSelfSigned);

        if ($this->serverName !== null) {
            $client->setSslHostName($this->serverName);
        }
        if ($this->caFile !== null) {
            $client->setSslCafile($this->caFile);
        }
        if ($this->caPath !== null) {
            $client->setSslCapath($this->caPath);
        }
        if ($this->clientCertificate !== null && $this->clientPrivateKey !== null) {
            $client->setSslCertFile($this->clientCertificate);
            $client->setSslKeyFile($this->clientPrivateKey);
        }
        if ($this->passphrase !== null) {
            $client->setSslPassphrase($this->passphrase);
        }
    }
}
