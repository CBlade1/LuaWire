<?php
declare(strict_types=1);
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) return false;
require dirname(__DIR__) . '/src/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; object-src \'none\'; base-uri \'none\'; frame-ancestors \'none\'');
require_local();
ini_set('session.use_strict_mode','1');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Strict');
if (!is_dir(storage_path(''))) { reject(500,'Storage directory missing'); }
if (!is_writable(storage_path(''))) { reject(500,'Storage directory not writable'); }
session_name('luawire_sid');
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(20));
$route = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    if ($route === '/api/login' && $method === 'POST') {
        require_csrf();
        $data = payload();
        $expected = setting('LUAWIRE_PASSWORD_HASH');
        if (!$expected || !password_verify((string)($data['password'] ?? ''), $expected)) { usleep(250000); reject(401, 'Invalid password'); }
        session_regenerate_id(true); $_SESSION['operator'] = true;
        response_json(['ok'=>true]);
    }
    if ($route === '/api/logout' && $method === 'POST') { require_operator(); require_csrf(); $_SESSION = []; session_destroy(); response_json(['ok'=>true]); }
    if ($route === '/api/agent/claim' && $method === 'POST') {
        require_agent();
        $job = store(function (&$jobs) {
            expire_jobs($jobs);
            foreach ($jobs as &$job) {
                if ($job['status'] !== 'pending') continue;
                $job['status'] = 'running'; $job['claimed'] = time();
                return ['id'=>$job['id'], 'code'=>$job['code']];
            }
            unset($job); return null;
        });
        response_json(['job'=>$job]);
    }
    if ($route === '/api/agent/result' && $method === 'POST') {
        require_agent();
        $data = payload();
        $id = (string)($data['id'] ?? '');
        $result = substr((string)($data['result'] ?? ''), 0, 16384);
        $ok = ($data['ok'] ?? false) === true;
        $stored = store(function (&$jobs) use ($id,$ok,$result) {
            expire_jobs($jobs); $idx = find_job($jobs,$id);
            if ($idx === null || $jobs[$idx]['status'] !== 'running') return false;
            $jobs[$idx]['status'] = $ok ? 'succeeded' : 'failed';
            $jobs[$idx]['result'] = $result; $jobs[$idx]['finished'] = time(); return true;
        });
        if (!$stored) reject(409, 'Job no longer running');
        response_json(['ok'=>true]);
    }
    if (str_starts_with((string)$route, '/api/')) {
        require_operator();
        if ($route === '/api/status' && $method === 'GET') {
            $data = store(function (&$jobs) {
                expire_jobs($jobs);
                $latest = array_reverse(array_slice($jobs, -50));
                foreach ($latest as &$job) unset($job['code']); unset($job);
                return $latest;
            });
            response_json(['jobs'=>$data]);
        }
        if ($route === '/api/submit' && $method === 'POST') {
            require_csrf();
            $data = payload(); $code = (string)($data['code'] ?? '');
            if (trim($code) === '' || strlen($code) > 16384) reject(422,'Code must be between 1 and 16,384 bytes');
            $job = store(function (&$jobs) use ($code) {
                expire_jobs($jobs);
                $pending = count(array_filter($jobs, fn($j)=>in_array($j['status'], ['pending','running'], true)));
                if ($pending >= 20) return null;
                $item = ['id'=>bin2hex(random_bytes(12)), 'code'=>$code, 'status'=>'pending','created'=>time(),'claimed'=>null,'finished'=>null,'result'=>''];
                $jobs[] = $item; return $item['id'];
            });
            if (!$job) reject(429,'Queue full');
            response_json(['id'=>$job], 201);
        }
        reject(404,'Not found');
    }
    if ($route !== '/' && $route !== '/index.php') { http_response_code(404); exit('Not found'); }
    header('Content-Type: text/html; charset=utf-8');
    $csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');
    $logged = !empty($_SESSION['operator']) ? 'true' : 'false';
    require __DIR__ . '/ui.php';
} catch (Throwable $e) { error_log('LuaWire: '.$e->getMessage()); reject(500,'Internal error'); }
