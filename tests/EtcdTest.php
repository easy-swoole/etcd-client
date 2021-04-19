<?php
declare(strict_types=1);

namespace EasySwoole\EtcdClient\Test;

use EasySwoole\EtcdClient\Etcd;
use EasySwoole\HttpClient\Exception\InvalidUrl;

class EtcdTest extends Base
{
    protected $key = '/test';

    protected $role = 'root';
    protected $user = 'root';
    protected $password = '123456';

    /**
     * @throws InvalidUrl
     */
    public function testPutAndRange()
    {
        $value = 'testput';
        $this->client->put($this->key, $value);

        $body = $this->client->get($this->key);
        $this->assertArrayHasKey($this->key, $body);
        $this->assertEquals($value, $body[$this->key]);
    }

    /**
     * @throws InvalidUrl
     */
    public function testGetAllKeys()
    {
        $body = $this->client->getAllKeys();
        $this->assertNotEmpty($body);
    }

    /**
     * @throws InvalidUrl
     */
    public function testGetKeysWithPrefix()
    {
        $body = $this->client->getKeysWithPrefix('/');
        $this->assertNotEmpty($body);
    }

    /**
     * @throws InvalidUrl
     */
    public function testDeleteRange()
    {
        $this->client->del($this->key);
        $body = $this->client->get($this->key);
        $this->assertArrayNotHasKey($this->key, $body);
    }

    /**
     * @throws InvalidUrl
     */
    public function testGrant()
    {
        $body = $this->client->grant(3600);
        $this->assertArrayHasKey('ID', $body);
        $id = (int) $body['ID'];

        $body = $this->client->timeToLive($id);
        $this->assertArrayHasKey('ID', $body);

        $this->client->keepAlive($id);
        $this->assertArrayHasKey('ID', $body);

        $this->client->revoke($id);
    }

    /**
     * @throws InvalidUrl
     */
    public function testAddRole()
    {
        $result = $this->client->addRole($this->role);
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testAddUser()
    {
        $result = $this->client->addUser($this->user, $this->password);
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testChangeUserPassword()
    {
        $result = $this->client->changeUserPassword($this->user, '456789');
        $this->assertNotNull($result);
        $result = $this->client->changeUserPassword($this->user, $this->password);
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testGrantUserRole()
    {
        $result = $this->client->grantUserRole($this->user, $this->role);
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testGetRole()
    {
        $result = $this->client->getRole($this->role);
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testRoleList()
    {
        $body = $this->client->roleList();
        $this->assertTrue(in_array($this->user, $body));
    }

    /**
     * @throws InvalidUrl
     */
    public function testGetUser()
    {
        $result = $this->client->getUser($this->user);
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testUserList()
    {
        $body = $this->client->userList();
        $this->assertTrue(in_array($this->user, $body));
    }

    /**
     * @throws InvalidUrl
     */
    public function testGrantRolePermission()
    {
        $result = $this->client->grantRolePermission($this->role,
            Etcd::PERMISSION_READWRITE, '\0', 'z' );
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testAuthenticate()
    {
        $this->client->authEnable();
        $token = $this->client->authenticate($this->user, $this->password);
        $this->client->setToken($token);
        $this->client->addUser('admin', '345678');
        $this->client->addRole('admin');
        $this->client->grantUserRole('admin', 'admin');

        $this->client->authDisable();
        $this->client->deleteRole('admin');
        $result = $this->client->deleteUser('admin');
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testRevokeRolePermission()
    {
        $result = $this->client->revokeRolePermission($this->role, '\0', 'z');
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testRevokeUserRole()
    {
        $result = $this->client->revokeUserRole($this->user, $this->role);
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testDeleteRole()
    {
        $result = $this->client->deleteRole($this->role);
        $this->assertNotNull($result);
    }

    /**
     * @throws InvalidUrl
     */
    public function testDeleteUser()
    {
        $result = $this->client->deleteUser($this->user);
        $this->assertNotNull($result);
    }
}