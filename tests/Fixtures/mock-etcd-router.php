<?php

declare(strict_types=1);

header('Content-Type: application/json');

$stateFile = getenv('MOCK_ETCD_STATE');
if (!is_string($stateFile) || $stateFile === '') {
    http_response_code(500);
    echo json_encode(['message' => 'MOCK_ETCD_STATE is not configured']);
    return;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/health') {
    echo json_encode(['health' => 'true']);
    return;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['message' => 'invalid JSON body']);
    return;
}

$state = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : null;
if (!is_array($state)) {
    $state = ['revision' => 0, 'values' => []];
}

$decode = static function (mixed $value): string {
    $decoded = base64_decode((string) $value, true);

    return $decoded === false ? '' : $decoded;
};
$respond = static function (array $body, int $status = 200): void {
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
};
$save = static function (array $newState) use ($stateFile): void {
    file_put_contents($stateFile, json_encode($newState, JSON_THROW_ON_ERROR), LOCK_EX);
};

switch ($path) {
    case '/v3/kv/put':
        $key = $decode($payload['key'] ?? '');
        $previous = $state['values'][$key] ?? null;
        $state['values'][$key] = $decode($payload['value'] ?? '');
        ++$state['revision'];
        $save($state);
        $body = ['header' => ['revision' => (string) $state['revision']]];
        if (($payload['prev_kv'] ?? false) && $previous !== null) {
            $body['prev_kv'] = ['key' => base64_encode($key), 'value' => base64_encode($previous)];
        }
        $respond($body);
        break;

    case '/v3/kv/range':
        $start = $decode($payload['key'] ?? '');
        $end = isset($payload['range_end']) ? $decode($payload['range_end']) : null;
        $rows = [];
        foreach ($state['values'] as $key => $value) {
            if (($end === null && $key === $start) || ($end !== null && $key >= $start && ($end === "\0" || $key < $end))) {
                $rows[] = ['key' => base64_encode($key), 'value' => base64_encode($value)];
            }
        }
        $respond(['header' => ['revision' => (string) $state['revision']], 'kvs' => $rows, 'count' => (string) count($rows)]);
        break;

    case '/v3/kv/deleterange':
        $start = $decode($payload['key'] ?? '');
        $end = isset($payload['range_end']) ? $decode($payload['range_end']) : null;
        $deleted = 0;
        foreach (array_keys($state['values']) as $key) {
            if (($end === null && $key === $start) || ($end !== null && $key >= $start && ($end === "\0" || $key < $end))) {
                unset($state['values'][$key]);
                ++$deleted;
            }
        }
        if ($deleted > 0) {
            ++$state['revision'];
        }
        $save($state);
        $respond(['header' => ['revision' => (string) $state['revision']], 'deleted' => (string) $deleted]);
        break;

    case '/v3/auth/authenticate':
        if (($payload['name'] ?? null) !== 'root' || ($payload['password'] ?? null) !== 'password') {
            $respond(['message' => 'authentication failed'], 401);
            break;
        }
        $respond(['token' => 'mock-token']);
        break;

    default:
        $respond(['message' => sprintf('mock endpoint not implemented: %s', $path)], 404);
}
