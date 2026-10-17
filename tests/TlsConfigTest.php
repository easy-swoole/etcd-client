<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient\Tests;

use EasySwoole\EtcdClient\TlsConfig;
use EasySwoole\HttpClient\HttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class TlsConfigTest extends TestCase
{
    public function testTlsOptionsAreAppliedToEasySwooleClient(): void
    {
        $tls = new TlsConfig(
            verifyPeer: true,
            allowSelfSigned: true,
            caFile: '/certs/etcd-ca.pem',
            caPath: '/certs/ca',
            clientCertificate: '/certs/client.pem',
            clientPrivateKey: '/certs/client-key.pem',
            passphrase: 'secret',
            serverName: 'etcd.internal',
        );
        $client = new HttpClient('https://127.0.0.1:2379');

        $tls->applyTo($client);

        $settingsProperty = new ReflectionProperty(HttpClient::class, 'clientSetting');
        $settings = $settingsProperty->getValue($client);
        $sslProperty = new ReflectionProperty(HttpClient::class, 'enableSSL');

        self::assertTrue($sslProperty->getValue($client));
        self::assertTrue($settings['ssl_verify_peer']);
        self::assertTrue($settings['ssl_allow_self_signed']);
        self::assertSame('/certs/etcd-ca.pem', $settings['ssl_cafile']);
        self::assertSame('/certs/ca', $settings['ssl_capath']);
        self::assertSame('/certs/client.pem', $settings['ssl_cert_file']);
        self::assertSame('/certs/client-key.pem', $settings['ssl_key_file']);
        self::assertSame('secret', $settings['ssl_passphrase']);
        self::assertSame('etcd.internal', $settings['ssl_host_name']);
    }

    #[DataProvider('invalidTlsProvider')]
    public function testInvalidTlsCombinationsAreRejected(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TlsConfig(...$arguments);
    }

    public static function invalidTlsProvider(): iterable
    {
        yield 'self-signed without verification' => [[
            'verifyPeer' => false,
            'allowSelfSigned' => true,
        ]];
        yield 'certificate without key' => [[
            'clientCertificate' => '/certs/client.pem',
        ]];
        yield 'key without certificate' => [[
            'clientPrivateKey' => '/certs/client-key.pem',
        ]];
        yield 'passphrase without key' => [[
            'passphrase' => 'secret',
        ]];
        yield 'empty CA file' => [[
            'caFile' => ' ',
        ]];
        yield 'empty server name' => [[
            'serverName' => '',
        ]];
    }
}
