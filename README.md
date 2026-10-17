# etcd-client

一个面向 PHP 8.1+ 的 etcd v3 JSON Gateway 客户端。默认使用
[`easyswoole/http-client`](https://github.com/easy-swoole/http-client) 发送请求，提供 KV、租约、认证、用户和角色权限等常用能力。

本项目采用强类型 API，并支持注入自定义 HTTP Transport，便于单元测试和替换底层通信实现。

## 环境要求

- PHP 8.1 或更高版本
- `ext-json`
- `ext-swoole`
- 开启 v3 JSON Gateway 的 etcd 服务

## 安装

```shell
composer require easyswoole/etcd-client
```

## 快速开始

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use EasySwoole\EtcdClient\Config;
use EasySwoole\EtcdClient\Etcd;

$etcd = new Etcd(new Config(
    endpoint: 'http://127.0.0.1:2379',
    apiVersion: 'v3',
    timeout: 5.0,
));

$client = $etcd->client;

$client->put('services/api', '127.0.0.1:9501');
$result = $client->range('services/api');

var_dump($result['kvs'][0]['value']); // 127.0.0.1:9501
```

`Config` 参数说明：

| 参数 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `endpoint` | `string` | `http://127.0.0.1:2379` | etcd 服务地址，必须使用 HTTP 或 HTTPS |
| `apiVersion` | `string` | `v3` | JSON Gateway API 版本 |
| `timeout` | `float` | `10.0` | HTTP 请求超时秒数 |
| `tls` | `?TlsConfig` | HTTPS 时自动创建 | TLS 与 mTLS 配置；只能用于 HTTPS 地址 |

HTTPS 地址即使没有显式传入 `TlsConfig`，也会自动使用严格证书校验配置。

## KV 操作

### 写入键值

```php
$client->put('config/database/host', '127.0.0.1');
```

可以通过第三个参数传递 etcd Put 请求选项：

```php
$result = $client->put(
    'config/database/host',
    '10.0.0.10',
    ['prev_kv' => true],
);

// 请求了 prev_kv 时，旧键值会被自动进行 Base64 解码。
$previousValue = $result['prev_kv']['value'] ?? null;
```

将键绑定到租约：

```php
$lease = $client->grantLease(30);

$client->put('sessions/123', 'session-data', [
    'lease' => $lease['ID'],
]);
```

### 获取单个键

```php
$result = $client->range('config/database/host');

if (($result['count'] ?? '0') !== '0') {
    $key = $result['kvs'][0]['key'];
    $value = $result['kvs'][0]['value'];
}
```

### 获取指定范围

```php
$result = $client->range('config/a', 'config/z');
```

### 获取指定前缀

客户端会根据前缀自动计算 etcd 所需的 `range_end`：

```php
$result = $client->rangePrefix('services/');

foreach ($result['kvs'] ?? [] as $item) {
    printf("%s = %s\n", $item['key'], $item['value']);
}
```

### 获取全部键

```php
$result = $client->rangeAll();
```

可以传入 etcd Range 请求支持的附加选项：

```php
$result = $client->rangePrefix('services/', [
    'limit' => 10,
    'keys_only' => true,
]);
```

### 删除键或前缀

```php
// 删除单个键
$client->delete('config/database/host');

// 删除时要求 etcd 返回被删除前的键值
$result = $client->delete('config/database/host', previous: true);

// 删除指定前缀下的所有键
$result = $client->deletePrefix('services/');
```

删除指定范围：

```php
$client->delete('config/a', 'config/z');
```

### 压缩历史版本

```php
$client->compact(revision: 1000);
$client->compact(revision: 1000, physical: true);
```

## 租约操作

```php
// 创建一个有效期为 30 秒的租约
$lease = $client->grantLease(30);
$leaseId = (int) $lease['ID'];

// 使用指定的租约 ID
$lease = $client->grantLease(ttl: 30, id: 123456);

// 续租
$result = $client->keepLeaseAlive($leaseId);

// 查询租约，并要求返回绑定的键
$result = $client->leaseTimeToLive($leaseId, includeKeys: true);

// 撤销租约；绑定到该租约的键将被删除
$client->revokeLease($leaseId);
```

`leaseTimeToLive()` 返回的 `keys` 会自动进行 Base64 解码。

## 认证

### 获取并使用 Token

```php
$token = $client->authenticate('root', 'password');
$authenticatedClient = $client->withToken($token);

$result = $authenticatedClient->range('protected/key');
```

`withToken()` 不会修改原客户端，而是返回一个携带 Token 的新客户端实例，可避免认证信息意外影响其他请求。

清除 Token 时同样创建新实例：

```php
$anonymousClient = $authenticatedClient->withToken(null);
```

### 启用或关闭认证

```php
$client->enableAuthentication();
$authenticatedClient->disableAuthentication();
```

启用认证前，请先按照 etcd 的要求创建 root 用户和 root 角色，避免服务进入无法管理的状态。

## 用户管理

```php
// 新增用户
$client->addUser('reader', 'initial-password');

// 获取用户信息
$user = $client->user('reader');

// 获取全部用户
$users = $client->users();

// 修改密码
$client->changePassword('reader', 'new-password');

// 为用户授予或撤销角色
$client->grantUserRole('reader', 'config-reader');
$client->revokeUserRole('reader', 'config-reader');

// 删除用户
$client->deleteUser('reader');
```

## 角色与权限管理

```php
use EasySwoole\EtcdClient\Permission;

// 新增角色
$client->addRole('config-reader');

// 获取角色信息或角色列表
$role = $client->role('config-reader');
$roles = $client->roles();

// 授予单个键的读取权限
$client->grantRolePermission(
    role: 'config-reader',
    permission: Permission::Read,
    key: 'config/app',
);

// 授予一个键范围的读写权限
$client->grantRolePermission(
    role: 'config-admin',
    permission: Permission::ReadWrite,
    key: 'config/',
    rangeEnd: 'config0',
);

// 撤销权限
$client->revokeRolePermission('config-reader', 'config/app');

// 删除角色
$client->deleteRole('config-reader');
```

可用权限枚举：

| 枚举 | etcd 值 | 说明 |
| --- | ---: | --- |
| `Permission::Read` | `0` | 只读 |
| `Permission::Write` | `1` | 只写 |
| `Permission::ReadWrite` | `2` | 读写 |

## HTTPS 与双向 TLS

使用 HTTPS 时，客户端会显式开启 TLS，即使 etcd 使用的是常见的 `2379` 而不是 `443` 端口。默认安全策略如下：

- 校验服务端证书；
- 不允许自签名证书；
- 使用 `endpoint` 中的主机名进行 TLS 主机名校验；
- 使用操作系统默认 CA；也可以显式指定私有 CA。

### 使用私有 CA 和 mTLS

```php
use EasySwoole\EtcdClient\Config;
use EasySwoole\EtcdClient\Etcd;
use EasySwoole\EtcdClient\TlsConfig;

$tls = new TlsConfig(
    caFile: '/run/secrets/etcd-ca.crt',
    clientCertificate: '/run/secrets/etcd-client.crt',
    clientPrivateKey: '/run/secrets/etcd-client.key',
    passphrase: getenv('ETCD_CLIENT_KEY_PASSPHRASE') ?: null,
    serverName: 'etcd.internal',
);

$etcd = new Etcd(new Config(
    endpoint: 'https://etcd.internal:2379',
    timeout: 5.0,
    tls: $tls,
));

$client = $etcd->client;
```

如果通过 IP 地址连接，但证书签发给域名，可以单独指定证书主机名：

```php
$tls = new TlsConfig(
    caFile: '/run/secrets/etcd-ca.crt',
    serverName: 'etcd.internal',
);

$config = new Config(endpoint: 'https://10.0.0.5:2379', tls: $tls);
```

也可以指定经哈希处理的 CA 目录：

```php
$tls = new TlsConfig(
    caPath: '/etc/ssl/certs',
);

$config = new Config(endpoint: 'https://etcd.internal:2379', tls: $tls);
```

### 开发环境中的自签名证书

建议仍然保持证书校验，只允许自签名证书：

```php
$tls = new TlsConfig(
    verifyPeer: true,
    allowSelfSigned: true,
    serverName: 'localhost',
);

$config = new Config(endpoint: 'https://127.0.0.1:2379', tls: $tls);
```

只有在临时诊断环境中才应完全关闭服务端证书校验：

```php
$tls = new TlsConfig(
    verifyPeer: false,
);

$config = new Config(endpoint: 'https://127.0.0.1:2379', tls: $tls);
```

> 禁用 `verifyPeer` 会使客户端无法确认服务端身份，可能遭受中间人攻击，不应在生产环境使用。

`TlsConfig` 可用配置项：

| 配置 | 默认值 | 说明 |
| --- | --- | --- |
| `verifyPeer` | `true` | 验证服务端证书 |
| `allowSelfSigned` | `false` | 是否允许自签名证书；建议优先通过 `caFile` 信任私有 CA |
| `caFile` | `null` | CA 证书文件绝对路径 |
| `caPath` | `null` | 已进行哈希处理的 CA 证书目录 |
| `clientCertificate` | `null` | mTLS 客户端证书文件 |
| `clientPrivateKey` | `null` | mTLS 客户端私钥文件，必须与证书同时配置 |
| `passphrase` | `null` | 客户端私钥密码 |
| `serverName` | `null` | TLS 主机名/SNI，例如 `etcd.internal` |

## 返回值与 Base64 处理

etcd v3 JSON Gateway 要求键和值使用 Base64。客户端会自动处理以下字段：

- 请求中的键、值和范围边界会自动编码；
- `kvs`、`prev_kv`、`prev_kvs` 中的 `key` 和 `value` 会自动解码；
- 角色权限中的 `key`、`range_end` 会自动编解码；
- 租约详情中的 `keys` 会自动解码。

方法返回 etcd 原始响应结构，只对上述二进制字段做解码，不会移除 `header`、`revision`、`count` 等元数据。例如：

```php
$result = $client->range('name');

$revision = $result['header']['revision'] ?? null;
$count = (int) ($result['count'] ?? 0);
$value = $result['kvs'][0]['value'] ?? null;
```

## 异常处理

网络错误、无效 JSON 和 etcd 非 2xx 响应会转换为客户端异常：

```php
use EasySwoole\EtcdClient\Exception\EtcdException;
use EasySwoole\EtcdClient\Exception\TransportException;

try {
    $result = $client->range('config/app');
} catch (TransportException $exception) {
    // DNS、连接失败、超时或请求编码错误
} catch (EtcdException $exception) {
    // etcd 返回错误，或响应内容无法解析
}
```

`TransportException` 继承自 `EtcdException`。如果不需要分别处理，捕获 `EtcdException` 即可。

## 自定义 Transport

可以实现 `TransportInterface`，用于接入其他 HTTP 客户端、链路追踪或测试替身：

```php
use EasySwoole\EtcdClient\Config;
use EasySwoole\EtcdClient\Etcd;
use EasySwoole\EtcdClient\Transport\TransportInterface;

/** @var TransportInterface $transport */
$etcd = new Etcd(new Config(), $transport);
```

Transport 需要返回包含 HTTP 状态码和响应正文的 `TransportResponse`。

## 测试

安装开发依赖：

```shell
composer install
```

运行全部测试：

```shell
composer test
```

单元测试使用内存 Transport，不依赖网络或真实 etcd 服务。

### 本机 mock etcd 集成测试

项目提供了一个轻量的本机 mock etcd JSON Gateway，用于验证生产环境使用的 `EasySwooleTransport`、HTTP 请求、Base64 编解码和响应处理：

```shell
composer test:integration
```

集成测试会自动完成以下工作：

1. 分配一个空闲的本机端口；
2. 启动 PHP 内置 HTTP 服务；
3. 模拟 KV 写入、前缀查询、范围删除和认证接口；
4. 使用真实 EasySwoole HTTP Transport 发起请求；
5. 测试结束后关闭服务并清理临时状态文件。

因此不需要手动启动 mock 服务，也不需要安装真正的 etcd。

## License

Apache-2.0
