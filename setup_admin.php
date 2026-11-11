<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Jalankan melalui terminal.\n"); }
$root = __DIR__;
$envFile = $root . '/.env';
$example = $root . '/.env.example';
if (!is_file($envFile)) {
    if (!copy($example, $envFile)) exit("Gagal membuat .env\n");
}
function ask_hidden(string $label): string {
    echo $label;
    $hidden = function_exists('shell_exec') && PHP_OS_FAMILY !== 'Windows';
    if ($hidden) @shell_exec('stty -echo');
    $value = trim((string)fgets(STDIN));
    if ($hidden) { @shell_exec('stty echo'); echo PHP_EOL; }
    return $value;
}
$username = trim((string)readline('Username admin [admin]: '));
if ($username === '') $username = 'admin';
$password = ask_hidden('Password admin baru: ');
$confirm = ask_hidden('Ulangi password: ');
if (strlen($password) < 10) exit("Password minimal 10 karakter.\n");
if (!hash_equals($password, $confirm)) exit("Password tidak sama.\n");
$lines = file($envFile, FILE_IGNORE_NEW_LINES) ?: [];
$values = ['ADMIN_USERNAME' => $username, 'ADMIN_PASSWORD_HASH' => password_hash($password, PASSWORD_DEFAULT)];
$found = [];
foreach ($lines as &$line) {
    if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $m) && isset($values[$m[1]])) {
        $line = $m[1] . '=' . $values[$m[1]];
        $found[$m[1]] = true;
    }
}
unset($line);
foreach ($values as $key => $value) if (!isset($found[$key])) $lines[] = $key . '=' . $value;
file_put_contents($envFile, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
@chmod($envFile, 0600);
echo "Akun administrator berhasil disiapkan di .env.\n";
