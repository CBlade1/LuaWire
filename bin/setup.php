<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("CLI only\n");
$root = dirname(__DIR__);
$password = $argv[1] ?? '';
if (strlen($password) < 14) exit("Usage: php bin/setup.php 'a-password-at-least-14-characters'\n");
if (!is_dir($root.'/storage')) mkdir($root.'/storage', 0700, true);
$token = bin2hex(random_bytes(32));
$hash = password_hash($password, PASSWORD_DEFAULT);
$env = "LUAWIRE_PASSWORD_HASH='" . str_replace("'", "'\\''", $hash) . "'\n".
       "LUAWIRE_AGENT_TOKEN='" . $token . "'\n" .
       "LUAWIRE_URL='http://127.0.0.1:8787'\n";
$path = $root.'/.env.local';
if (file_exists($path)) exit("Already configured. Remove .env.local manually to reset credentials.\n");
file_put_contents($path,$env,LOCK_EX); chmod($path,0600);
if (!file_exists($root.'/storage/queue.json')) file_put_contents($root.'/storage/queue.json','[]',LOCK_EX);
chmod($root.'/storage/queue.json',0600);
echo "Created .env.local and local queue. Keep .env.local private.\n";
