<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/RouterOSApi.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_name('RGIDNSSESSID');

session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
    'path' => '/'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try { initialize_database(); }
catch (Throwable $error) {
    error_log('Database initialization: ' . $error->getMessage());
    http_response_code(500);
    exit('Database belum siap. Periksa ekstensi PDO SQLite dan izin folder storage.');
}
