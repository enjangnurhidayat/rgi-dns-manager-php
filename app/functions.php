<?php
declare(strict_types=1);

function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function verify_csrf(): void {
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Permintaan tidak valid atau sesi formulir telah kedaluwarsa. Muat ulang halaman.');
    }
}
function flash(string $message, string $type = 'success'): void {
    $_SESSION['_flash'][] = ['message' => $message, 'type' => $type];
}
function take_flashes(): array {
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}
function require_login(): void {
    if (empty($_SESSION['logged_in'])) redirect('/login');
}
function valid_domain(string $domain): bool {
    $domain = strtolower(rtrim(trim($domain), '.'));
    if (strlen($domain) < 1 || strlen($domain) > 253) return false;
    $labels = explode('.', $domain);
    if (count($labels) < 2) return false;
    foreach ($labels as $label) {
        if (strlen($label) < 1 || strlen($label) > 63) return false;
        if (!preg_match('/^[a-z0-9-]+$/i', $label) || !ctype_alnum($label[0]) || !ctype_alnum($label[strlen($label) - 1])) return false;
    }
    return (bool)preg_match('/^[a-z]{2,63}$/i', end($labels));
}
function format_mib(int $bytes): string {
    return number_format($bytes / 1048576, 1) . ' MiB';
}
function router_default(): array {
    return [
        'online' => false, 'identity' => 'Tidak terhubung', 'version' => '-',
        'cpu_load' => '-', 'total_memory' => 0, 'free_memory' => 0,
        'used_memory' => 0, 'memory_percent' => 0, 'uptime' => '-'
    ];
}
