<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient\Tests;

use EasySwoole\EtcdClient\Config;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testBuildsApiUrl(): void
    {
        $config = new Config('https://etcd.internal:2379/', 'v3beta', 2.5, '/cert.pem', '/key.pem');

        self::assertSame('https://etcd.internal:2379/v3beta/kv/range', $config->url('/kv/range'));
        self::assertSame(2.5, $config->timeout);
    }

    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Config(...$arguments);
    }

    public static function invalidConfigurations(): iterable
    {
        yield 'invalid endpoint' => [['not-a-url']];
        yield 'invalid scheme' => [['ftp://localhost:2379']];
        yield 'invalid version' => [['http://localhost:2379', '../v3']];
        yield 'invalid timeout' => [['http://localhost:2379', 'v3', 0.0]];
        yield 'incomplete mTLS pair' => [['https://localhost:2379', 'v3', 1.0, '/cert.pem']];
    }
}
