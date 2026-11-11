<?php
declare(strict_types=1);

function load_env(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'") {
            $value = trim($value, "\"'");
        }
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}
load_env(dirname(__DIR__) . '/.env');

function env_value(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value === false ? $default : (string)$value;
}

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', 'RGI DNS Manager');
define('DB_PATH', APP_ROOT . '/storage/tkj35.db');
define('MIKROTIK_HOST', env_value('MIKROTIK_HOST', '192.168.5.1'));
define('MIKROTIK_PORT', (int)env_value('MIKROTIK_PORT', '8728'));
define('MIKROTIK_USER', env_value('MIKROTIK_USER'));
define('MIKROTIK_PASSWORD', env_value('MIKROTIK_PASSWORD'));
define('ADMIN_USERNAME', env_value('ADMIN_USERNAME', 'admin'));
define('ADMIN_PASSWORD_HASH', env_value('ADMIN_PASSWORD_HASH'));
define('DNS_MANAGER_COMMENT', 'RGI-DNS-MANAGER');
define('DNS_SINKHOLE_COMMENT', 'RGI-DNS-SINKHOLE');
