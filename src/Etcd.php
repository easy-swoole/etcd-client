<?php
declare(strict_types=1);

namespace EasySwoole\EtcdClient;

class Etcd
{
    const PERMISSION_READ = 0;

    const PERMISSION_WRITE = 1;

    const PERMISSION_READWRITE = 2;

    protected $config;

    function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function client(): Client
    {
        return new Client($this->config);
    }
}