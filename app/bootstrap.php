<?php
declare(strict_types=1);

function walkie_config(): array {
    static $config = null;
    if ($config !== null) return $config;
    $config = [
        'secret' => getenv('SIGNAL_SECRET') ?: '',
        'max_peers' => max(2, min(32, (int)(getenv('SIGNAL_MAX_PEERS') ?: 8))),
        'floor_timeout_ms' => max(5000, min(120000, (int)(getenv('SIGNAL_FLOOR_TIMEOUT_MS') ?: 30000))),
        'max_body' => max(4096, min(131072, (int)(getenv('SIGNAL_MAX_BODY') ?: 32768))),
        'max_data' => max(1024, min(65536, (int)(getenv('SIGNAL_MAX_DATA') ?: 12288))),
    ];
    return $config;
}
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function storage_dir(): string { return dirname(__DIR__) . '/storage'; }
function ensure_storage(): void {
    foreach ([storage_dir(), storage_dir().'/rooms', storage_dir().'/logs'] as $dir) {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
    }
}
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function room_file(string $channel): string {
    return storage_dir() . '/rooms/' . hash('sha256', $channel) . '.json';
}
function with_room_lock(string $channel, callable $callback): mixed {
    ensure_storage();
    $path = room_file($channel);
    $fp = fopen($path, 'c+');
    if (!$fp) throw new RuntimeException('Unable to open room storage.');
    try {
        if (!flock($fp, LOCK_EX)) throw new RuntimeException('Unable to lock room storage.');
        rewind($fp);
        $raw = stream_get_contents($fp);
        $room = json_decode($raw ?: '', true);
        if (!is_array($room)) $room = ['peers'=>[], 'speaker'=>null, 'events'=>[], 'seq'=>0];
        $result = $callback($room);
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($room, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        return $result;
    } finally { fclose($fp); }
}
function signed_token(array $guest): string {
    $payload = ['id'=>$guest['id'], 'nickname'=>$guest['nickname'], 'channel'=>$guest['channel'], 'exp'=>time()+3600];
    $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $secret = walkie_config()['secret'];
    if ($secret === '') $secret = hash('sha256', __DIR__ . PHP_VERSION . php_uname('n'));
    return $encoded . '.' . hash_hmac('sha256', $encoded, $secret);
}
function verify_token(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 2) return null;
    $secret = walkie_config()['secret'];
    if ($secret === '') $secret = hash('sha256', __DIR__ . PHP_VERSION . php_uname('n'));
    if (!hash_equals(hash_hmac('sha256', $parts[0], $secret), $parts[1])) return null;
    $decoded = base64_decode(strtr($parts[0], '-_', '+/'));
    $data = json_decode($decoded ?: '', true);
    if (!is_array($data) || (int)($data['exp'] ?? 0) < time()) return null;
    return $data;
}
