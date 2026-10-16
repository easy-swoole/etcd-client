<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient\Tests;

use EasySwoole\EtcdClient\Client;
use EasySwoole\EtcdClient\Config;
use EasySwoole\EtcdClient\Exception\EtcdException;
use EasySwoole\EtcdClient\Permission;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private FakeTransport $transport;
    private Client $client;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->client = new Client(new Config(timeout: 3.5), $this->transport);
    }

    public function testPutEncodesBinaryValuesAndDecodesPreviousValue(): void
    {
        $this->transport->respondWith(200, [
            'header' => ['revision' => '2'],
            'prev_kv' => ['key' => base64_encode("key\0"), 'value' => base64_encode('old')],
        ]);

        $result = $this->client->put("key\0", 'new', ['prev_kv' => true]);
        $request = $this->transport->lastRequest();

        self::assertSame('http://127.0.0.1:2379/v3/kv/put', $request['url']);
        self::assertSame(['key' => base64_encode("key\0"), 'value' => base64_encode('new'), 'prev_kv' => true], $request['payload']);
        self::assertSame(['key' => "key\0", 'value' => 'old'], $result['prev_kv']);
        self::assertSame(3.5, $request['timeout']);
    }

    public function testRangePrefixUsesEtcdPrefixBoundaryAndDecodesKvs(): void
    {
        $this->transport->respondWith(200, ['kvs' => [
            ['key' => base64_encode('/services/a'), 'value' => base64_encode('10.0.0.1')],
        ]]);

        $result = $this->client->rangePrefix('/services/');
        $payload = $this->transport->lastRequest()['payload'];

        self::assertSame(base64_encode('/services/'), $payload['key']);
        self::assertSame(base64_encode('/services0'), $payload['range_end']);
        self::assertSame('/services/a', $result['kvs'][0]['key']);
        self::assertSame('10.0.0.1', $result['kvs'][0]['value']);
    }

    public function testDeletePrefixRequestsPreviousValues(): void
    {
        $this->client->deletePrefix('app/', true);

        self::assertSame([
            'key' => base64_encode('app/'),
            'prev_kv' => true,
            'range_end' => base64_encode('app0'),
        ], $this->transport->lastRequest()['payload']);
    }

    public function testTokenIsAppliedToClonedClientOnly(): void
    {
        $authenticated = $this->client->withToken('secret-token');
        $authenticated->range('key');
        $this->client->range('key');

        self::assertSame('secret-token', $this->transport->requests[0]['headers']['Grpc-Metadata-Token']);
        self::assertArrayNotHasKey('Grpc-Metadata-Token', $this->transport->requests[1]['headers']);
    }

    public function testAuthenticationReturnsToken(): void
    {
        $this->transport->respondWith(200, ['token' => 'jwt']);

        self::assertSame('jwt', $this->client->authenticate('root', 'password'));
        self::assertSame(['name' => 'root', 'password' => 'password'], $this->transport->lastRequest()['payload']);
    }

    public function testLeaseEndpointsUseCorrectV3Paths(): void
    {
        $this->client->revokeLease(42);
        self::assertStringEndsWith('/v3/lease/revoke', $this->transport->lastRequest()['url']);

        $this->transport->respondWith(200, ['keys' => [base64_encode('leased-key')]]);
        $result = $this->client->leaseTimeToLive(42, true);
        self::assertStringEndsWith('/v3/lease/timetolive', $this->transport->lastRequest()['url']);
        self::assertSame(['leased-key'], $result['keys']);
    }

    public function testRolePermissionUsesEnumAndBase64(): void
    {
        $this->client->grantRolePermission('reader', Permission::Read, 'app/', 'app0');

        self::assertSame([
            'name' => 'reader',
            'perm' => [
                'permType' => 0,
                'key' => base64_encode('app/'),
                'range_end' => base64_encode('app0'),
            ],
        ], $this->transport->lastRequest()['payload']);
    }

    public function testServerErrorBecomesDomainException(): void
    {
        $this->transport->respondWith(403, ['message' => 'permission denied']);

        $this->expectException(EtcdException::class);
        $this->expectExceptionMessage('permission denied');
        $this->client->range('secret');
    }

    public function testInvalidJsonBecomesDomainException(): void
    {
        $this->transport->respondWith(200, '{invalid');

        $this->expectException(EtcdException::class);
        $this->expectExceptionMessage('invalid JSON');
        $this->client->range('key');
    }
}
