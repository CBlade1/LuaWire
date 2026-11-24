<?php
declare(strict_types=1);

function setting(string $name, string $default = ''): string {
    $value = getenv($name);
    return $value === false ? $default : $value;
}
function storage_path(string $name): string { return dirname(__DIR__) . '/storage/' . $name; }
function response_json(array $data, int $code = 200): never {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store'); echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function reject(int $code, string $message): never { response_json(['error' => $message], $code); }
function require_local(): void {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!in_array($ip, ['127.0.0.1', '::1'], true)) { reject(403, 'Loopback access only'); }
}
function require_agent(): void {
    require_local();
    $secret = setting('LUAWIRE_AGENT_TOKEN');
    if (strlen($secret) < 32 || !hash_equals($secret, (string)($_SERVER['HTTP_X_LUA_AGENT_TOKEN'] ?? ''))) reject(401, 'Unauthorized');
}
function require_operator(): void {
    if (empty($_SESSION['operator'])) reject(401, 'Login required');
}
function require_csrf(): void {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) reject(403, 'Invalid CSRF token');
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST'], true)) reject(405, 'Method not allowed');
}
function payload(): array {
    if (strlen((string)file_get_contents('php://input')) > 131072) reject(413, 'Request too large');
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) reject(400, 'Invalid JSON');
    return $input;
}
function store(callable $fn): mixed {
    $path = storage_path('queue.json');
    $file = fopen($path, 'c+');
    if (!$file) throw new RuntimeException('Storage unavailable');
    try {
        if (!flock($file, LOCK_EX)) throw new RuntimeException('Storage lock unavailable');
        rewind($file); $raw = stream_get_contents($file);
        $jobs = $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
        if (!is_array($jobs)) throw new RuntimeException('Corrupt queue');
        $result = $fn($jobs);
        rewind($file);
        $output = json_encode($jobs, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if (fwrite($file, $output) !== strlen($output)) throw new RuntimeException('Storage write failed');
        ftruncate($file, strlen($output)); fflush($file);
        return $result;
    } finally { flock($file, LOCK_UN); fclose($file); }
}
function expire_jobs(array &$jobs): void {
    $now = time();
    foreach ($jobs as &$job) {
        if ($job['status'] === 'pending' && $job['created'] + 120 < $now) { $job['status'] = 'expired'; $job['finished'] = $now; $job['result'] = 'Not claimed within 120 seconds'; }
        if ($job['status'] === 'running' && $job['claimed'] + 60 < $now) { $job['status'] = 'unknown'; $job['finished'] = $now; $job['result'] = 'Worker did not acknowledge completion; execution may have occurred'; }
    }
    unset($job);
    if (count($jobs) > 500) $jobs = array_slice($jobs, -500);
}
function find_job(array &$jobs, string $id): ?int {
    foreach ($jobs as $index => $job) if ($job['id'] === $id) return $index;
    return null;
}
