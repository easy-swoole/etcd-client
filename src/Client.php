<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient;

use EasySwoole\EtcdClient\Exception\EtcdException;
use EasySwoole\EtcdClient\Transport\TransportInterface;
use InvalidArgumentException;
use JsonException;

final class Client
{
    private ?string $token;

    public function __construct(
        private readonly Config $config,
        private readonly TransportInterface $transport,
        ?string $token = null,
    ) {
        $this->token = $token;
    }

    public function withToken(?string $token): self
    {
        $client = clone $this;
        $client->token = $token;

        return $client;
    }

    /** @return array<string, mixed> */
    public function put(string $key, string $value, array $options = []): array
    {
        return $this->call('kv/put', [
            'key' => self::encode($key),
            'value' => self::encode($value),
        ] + $options, ['prev_kv']);
    }

    /** @return array<string, mixed> */
    public function range(string $key, ?string $rangeEnd = null, array $options = []): array
    {
        $payload = ['key' => self::encode($key)] + $options;
        if ($rangeEnd !== null) {
            $payload['range_end'] = self::encode($rangeEnd);
        }

        return $this->call('kv/range', $payload, ['kvs']);
    }

    /** @return array<string, mixed> */
    public function rangePrefix(string $prefix, array $options = []): array
    {
        if ($prefix === '') {
            throw new InvalidArgumentException('The prefix cannot be empty.');
        }

        return $this->range($prefix, self::prefixEnd($prefix), $options);
    }

    /** @return array<string, mixed> */
    public function rangeAll(array $options = []): array
    {
        return $this->range("\0", "\0", $options);
    }

    /** @return array<string, mixed> */
    public function delete(string $key, ?string $rangeEnd = null, bool $previous = false): array
    {
        $payload = ['key' => self::encode($key), 'prev_kv' => $previous];
        if ($rangeEnd !== null) {
            $payload['range_end'] = self::encode($rangeEnd);
        }

        return $this->call('kv/deleterange', $payload, ['prev_kvs']);
    }

    /** @return array<string, mixed> */
    public function deletePrefix(string $prefix, bool $previous = false): array
    {
        if ($prefix === '') {
            throw new InvalidArgumentException('The prefix cannot be empty.');
        }

        return $this->delete($prefix, self::prefixEnd($prefix), $previous);
    }

    /** @return array<string, mixed> */
    public function compact(int $revision, bool $physical = false): array
    {
        return $this->call('kv/compaction', ['revision' => $revision, 'physical' => $physical]);
    }

    /** @return array<string, mixed> */
    public function grantLease(int $ttl, int $id = 0): array
    {
        return $this->call('lease/grant', ['TTL' => $ttl, 'ID' => $id]);
    }

    /** @return array<string, mixed> */
    public function revokeLease(int $id): array
    {
        return $this->call('lease/revoke', ['ID' => $id]);
    }

    /** @return array<string, mixed> */
    public function keepLeaseAlive(int $id): array
    {
        $response = $this->call('lease/keepalive', ['ID' => $id]);

        return isset($response['result']) && is_array($response['result']) ? $response['result'] : $response;
    }

    /** @return array<string, mixed> */
    public function leaseTimeToLive(int $id, bool $includeKeys = false): array
    {
        return $this->call('lease/timetolive', ['ID' => $id, 'keys' => $includeKeys], ['keys']);
    }

    public function authenticate(string $username, string $password): string
    {
        $response = $this->call('auth/authenticate', ['name' => $username, 'password' => $password]);
        $token = $response['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new EtcdException('The authentication response did not contain a token.');
        }

        return $token;
    }

    /** @return array<string, mixed> */
    public function enableAuthentication(): array
    {
        return $this->call('auth/enable');
    }

    /** @return array<string, mixed> */
    public function disableAuthentication(): array
    {
        return $this->call('auth/disable');
    }

    /** @return array<string, mixed> */
    public function addUser(string $name, string $password): array
    {
        return $this->call('auth/user/add', ['name' => $name, 'password' => $password]);
    }

    /** @return array<string, mixed> */
    public function user(string $name): array
    {
        return $this->call('auth/user/get', ['name' => $name]);
    }

    /** @return array<string, mixed> */
    public function users(): array
    {
        return $this->call('auth/user/list');
    }

    /** @return array<string, mixed> */
    public function deleteUser(string $name): array
    {
        return $this->call('auth/user/delete', ['name' => $name]);
    }

    /** @return array<string, mixed> */
    public function changePassword(string $name, string $password): array
    {
        return $this->call('auth/user/changepw', ['name' => $name, 'password' => $password]);
    }

    /** @return array<string, mixed> */
    public function grantUserRole(string $user, string $role): array
    {
        return $this->call('auth/user/grant', ['user' => $user, 'role' => $role]);
    }

    /** @return array<string, mixed> */
    public function revokeUserRole(string $user, string $role): array
    {
        return $this->call('auth/user/revoke', ['name' => $user, 'role' => $role]);
    }

    /** @return array<string, mixed> */
    public function addRole(string $name): array
    {
        return $this->call('auth/role/add', ['name' => $name]);
    }

    /** @return array<string, mixed> */
    public function role(string $name): array
    {
        return $this->call('auth/role/get', ['role' => $name], ['perm']);
    }

    /** @return array<string, mixed> */
    public function roles(): array
    {
        return $this->call('auth/role/list');
    }

    /** @return array<string, mixed> */
    public function deleteRole(string $name): array
    {
        return $this->call('auth/role/delete', ['role' => $name]);
    }

    /** @return array<string, mixed> */
    public function grantRolePermission(
        string $role,
        Permission $permission,
        string $key,
        ?string $rangeEnd = null,
    ): array {
        $grant = ['permType' => $permission->value, 'key' => self::encode($key)];
        if ($rangeEnd !== null) {
            $grant['range_end'] = self::encode($rangeEnd);
        }

        return $this->call('auth/role/grant', ['name' => $role, 'perm' => $grant]);
    }

    /** @return array<string, mixed> */
    public function revokeRolePermission(string $role, string $key, ?string $rangeEnd = null): array
    {
        $payload = ['role' => $role, 'key' => self::encode($key)];
        if ($rangeEnd !== null) {
            $payload['range_end'] = self::encode($rangeEnd);
        }

        return $this->call('auth/role/revoke', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $binaryCollections
     * @return array<string, mixed>
     */
    private function call(string $path, array $payload = [], array $binaryCollections = []): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->token !== null) {
            $headers['Grpc-Metadata-Token'] = $this->token;
        }

        $response = $this->transport->post(
            $this->config->url($path),
            $payload,
            $headers,
            $this->config->timeout,
        );

        try {
            $body = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new EtcdException('etcd returned invalid JSON.', $response->statusCode, $exception);
        }
        if (!is_array($body)) {
            throw new EtcdException('etcd returned an unexpected JSON value.', $response->statusCode);
        }
        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            $message = is_string($body['message'] ?? null) ? $body['message'] : 'etcd request failed.';
            throw new EtcdException($message, $response->statusCode);
        }
        foreach ($binaryCollections as $field) {
            if (isset($body[$field])) {
                $body[$field] = self::decodeCollection($body[$field]);
            }
        }

        return $body;
    }

    private static function encode(string $value): string
    {
        return base64_encode($value);
    }

    private static function prefixEnd(string $prefix): string
    {
        for ($index = strlen($prefix) - 1; $index >= 0; --$index) {
            if (ord($prefix[$index]) < 0xff) {
                return substr($prefix, 0, $index) . chr(ord($prefix[$index]) + 1);
            }
        }

        return "\0";
    }

    private static function decodeCollection(mixed $items): mixed
    {
        if (is_array($items) && array_is_list($items) && ($items === [] || is_string($items[0]))) {
            return array_map(static function (mixed $item): mixed {
                if (!is_string($item)) {
                    return $item;
                }

                $decoded = base64_decode($item, true);

                return $decoded === false ? $item : $decoded;
            }, $items);
        }

        $single = is_array($items) && !array_is_list($items);
        $rows = $single ? [$items] : $items;
        if (!is_array($rows)) {
            return $items;
        }
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (['key', 'value', 'range_end'] as $field) {
                if (is_string($row[$field] ?? null)) {
                    $decoded = base64_decode($row[$field], true);
                    if ($decoded !== false) {
                        $row[$field] = $decoded;
                    }
                }
            }
        }

        return $single ? ($rows[0] ?? []) : $rows;
    }
}
