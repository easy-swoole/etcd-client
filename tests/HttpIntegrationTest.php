<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient\Tests;

use EasySwoole\EtcdClient\Config;
use EasySwoole\EtcdClient\Etcd;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

#[Group('integration')]
final class HttpIntegrationTest extends TestCase
{
    /** @var resource|null */
    private static $serverProcess;
    private static int $port;
    private static string $stateFile;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            self::fail(sprintf('Unable to reserve a mock server port: %s', $errorMessage));
        }
        self::$port = (int) parse_url((string) stream_socket_get_name($socket, false), PHP_URL_PORT);
        fclose($socket);

        self::$stateFile = tempnam(sys_get_temp_dir(), 'mock-etcd-');
        if (self::$stateFile === false) {
            self::fail('Unable to create the mock etcd state file.');
        }

        $router = __DIR__ . '/Fixtures/mock-etcd-router.php';
        $command = [PHP_BINARY, '-S', sprintf('127.0.0.1:%d', self::$port), $router];
        self::$serverProcess = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir() . '/mock-etcd-server.log', 'a'],
            2 => ['file', sys_get_temp_dir() . '/mock-etcd-server.log', 'a'],
        ], $pipes, null, ['MOCK_ETCD_STATE' => self::$stateFile]);

        if (!is_resource(self::$serverProcess)) {
            self::fail('Unable to start the mock etcd server.');
        }
        fclose($pipes[0]);

        $healthUrl = sprintf('http://127.0.0.1:%d/health', self::$port);
        for ($attempt = 0; $attempt < 50; ++$attempt) {
            if (@file_get_contents($healthUrl) !== false) {
                return;
            }
            usleep(20_000);
        }

        self::fail('The mock etcd server did not become ready.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
        if (isset(self::$stateFile) && is_file(self::$stateFile)) {
            unlink(self::$stateFile);
        }
    }

    public function testRealHttpTransportAgainstLocalMockEtcd(): void
    {
        $result = null;
        Coroutine\run(function () use (&$result): void {
            $client = (new Etcd(new Config(
                endpoint: sprintf('http://127.0.0.1:%d', self::$port),
                timeout: 2.0,
            )))->client;

            $client->put('services/api', '127.0.0.1:9501');
            $client->put('services/worker', '127.0.0.1:9502');
            $range = $client->rangePrefix('services/');
            $token = $client->authenticate('root', 'password');
            $deleted = $client->withToken($token)->deletePrefix('services/');
            $empty = $client->rangePrefix('services/');

            $result = [$range, $token, $deleted, $empty];
        });

        self::assertIsArray($result);
        self::assertSame('2', $result[0]['count']);
        self::assertSame('services/api', $result[0]['kvs'][0]['key']);
        self::assertSame('127.0.0.1:9501', $result[0]['kvs'][0]['value']);
        self::assertSame('mock-token', $result[1]);
        self::assertSame('2', $result[2]['deleted']);
        self::assertSame('0', $result[3]['count']);
    }
}
