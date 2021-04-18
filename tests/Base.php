<?php
declare(strict_types=1);

namespace EasySwoole\EtcdClient\Test;

use EasySwoole\EtcdClient\Config;
use EasySwoole\EtcdClient\Etcd;
use PHPUnit\Framework\TestCase;

class Base extends TestCase
{
    private $etcd;

    function getEtcd(): Etcd
    {
        if (empty($this->etcd)) {
            $this->etcd = new Etcd(new Config(
                [
                    'host' => '127.0.0.1',
                    'version' => 'v3alpha',
                    'pretty' => true,
                    'ssl' => false
                ]
            ));
        }
        return $this->etcd;
    }
}