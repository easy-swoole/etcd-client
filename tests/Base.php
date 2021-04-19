<?php
declare(strict_types=1);

namespace EasySwoole\EtcdClient\Test;

use EasySwoole\EtcdClient\Client;
use EasySwoole\EtcdClient\Config;
use EasySwoole\EtcdClient\Etcd;
use PHPUnit\Framework\TestCase;

class Base extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = (new Etcd(new Config(
            [
                'host' => '127.0.0.1',
                'version' => 'v3',
                'pretty' => true,
                'ssl' => false
            ]
        )))->client();
    }
}