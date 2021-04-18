<?php
declare(strict_types=1);

namespace EasySwoole\EtcdClient\Test;

use EasySwoole\HttpClient\Exception\InvalidUrl;

class EtcdTest extends Base
{
//    /**
//     * @throws InvalidUrl
//     */
//    public function testPutAndGet()
//    {
//        $response = $this->getEtcd()->client()->put('test', '123456');
//        $this->assertNotNull($response);
//
//        $response = $this->getEtcd()->client()->get('test');
//        $this->assertEquals('123456', $response['test']);
//    }
//
//
//    /**
//     * @throws InvalidUrl
//     */
//    public function testUser()
//    {
//        $response = $this->getEtcd()->client()->getUser('root');
//        var_dump($response);
//    }

    /**
     * @throws InvalidUrl
     */
    public function testAuth()
    {
        $client = $this->getEtcd()->client();

        $response =$client->authEnable();
        var_dump($response);

        $response = $client->authenticate('root', '123456');
        var_dump($response);
        $client->setToken($response);
        //$client->setToken('CXUzNavcdAMnTYCp.105');

        $response = $client->get('test');
        var_dump($response);
        //$this->assertEquals('123456', $response['test']);


        $response = $client->authDisable();
        var_dump($response);
    }
}