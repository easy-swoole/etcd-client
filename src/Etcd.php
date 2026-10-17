<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient;

use EasySwoole\EtcdClient\Transport\EasySwooleTransport;
use EasySwoole\EtcdClient\Transport\TransportInterface;

final class Etcd
{
    public readonly Client $client;

    public function __construct(Config $config, ?TransportInterface $transport = null)
    {
        $this->client = new Client($config, $transport ?? new EasySwooleTransport($config->tls));
    }
}
