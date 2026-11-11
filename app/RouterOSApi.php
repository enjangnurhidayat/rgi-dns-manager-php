<?php
declare(strict_types=1);

/**
 * Klien RouterOS API sederhana untuk RouterOS v6/v7.
 * Menggunakan API TCP standar (umumnya port 8728).
 */
final class RouterOSApi {
    private $socket = null;

public function connect(
    ?string $username = null,
    ?string $password = null
): void {
    $username = $username ?? MIKROTIK_USER;
    $password = $password ?? MIKROTIK_PASSWORD;

    $errno = 0;
    $error = '';

    $this->socket = @fsockopen(
        MIKROTIK_HOST,
        MIKROTIK_PORT,
        $errno,
        $error,
        4
    );

    if (!$this->socket) {
        throw new RuntimeException(
            "Tidak dapat terhubung ke MikroTik: {$error} ({$errno})"
        );
    }

    stream_set_timeout($this->socket, 5);

    $this->command('/login', [
        'name' => $username,
        'password' => $password
    ]);
}

    private function writeLength(int $length): void {
        if ($length < 0x80) fwrite($this->socket, chr($length));
        elseif ($length < 0x4000) fwrite($this->socket, pack('n', $length | 0x8000));
        elseif ($length < 0x200000) {
            fwrite($this->socket, chr(($length >> 16) | 0xC0) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF));
        } elseif ($length < 0x10000000) {
            fwrite($this->socket, pack('N', $length | 0xE0000000));
        } else {
            fwrite($this->socket, chr(0xF0) . pack('N', $length));
        }
    }

    private function readLength(): int {
        $first = fread($this->socket, 1);
        if ($first === '' || $first === false) throw new RuntimeException('Koneksi MikroTik terputus.');
        $c = ord($first);
        if (($c & 0x80) === 0) return $c;
        if (($c & 0xC0) === 0x80) {
            $b = fread($this->socket, 1);
            return (($c & 0x3F) << 8) | ord($b);
        }
        if (($c & 0xE0) === 0xC0) {
            $b = fread($this->socket, 2);
            return (($c & 0x1F) << 16) | (ord($b[0]) << 8) | ord($b[1]);
        }
        if (($c & 0xF0) === 0xE0) {
            $b = fread($this->socket, 3);
            return (($c & 0x0F) << 24) | (ord($b[0]) << 16) | (ord($b[1]) << 8) | ord($b[2]);
        }
        if ($c === 0xF0) return unpack('N', fread($this->socket, 4))[1];
        throw new RuntimeException('Format panjang data RouterOS tidak dikenal.');
    }

    private function readWord(): string {
        $length = $this->readLength();
        if ($length === 0) return '';
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') throw new RuntimeException('Gagal membaca respons MikroTik.');
            $data .= $chunk;
        }
        return $data;
    }

    private function writeWord(string $word): void {
        $this->writeLength(strlen($word));
        fwrite($this->socket, $word);
    }

    public function command(string $command, array $attributes = []): array {
        if (!$this->socket) throw new RuntimeException('Belum terhubung ke MikroTik.');
        $this->writeWord($command);
        foreach ($attributes as $key => $value) $this->writeWord('=' . $key . '=' . $value);
        $this->writeWord('');

        $rows = []; $row = [];
        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                if ($row) { $rows[] = $row; $row = []; }
                continue;
            }
            if ($word === '!re') { if ($row) $rows[] = $row; $row = []; continue; }
            if ($word === '!done') {
                if ($row) $rows[] = $row;
                break;
            }
            if ($word === '!trap' || $word === '!fatal') {
                $message = 'Kesalahan RouterOS';
                while (true) {
                    $next = $this->readWord();
                    if ($next === '') break;
                    if (str_starts_with($next, '=message=')) $message = substr($next, 9);
                }
                throw new RuntimeException($message);
            }
            if ($word[0] === '=' && str_contains($word, '=')) {
                $part = substr($word, 1);
                $pos = strpos($part, '=');
                if ($pos !== false) $row[substr($part, 0, $pos)] = substr($part, $pos + 1);
            }
        }
        return $rows;
    }

    public function disconnect(): void {
        if (is_resource($this->socket)) fclose($this->socket);
        $this->socket = null;
    }
    public function __destruct() { $this->disconnect(); }
}

function get_router_status(): array {
    $api = new RouterOSApi();
    try {
        $api->connect();
        $identity = $api->command('/system/identity/print');
        $resources = $api->command('/system/resource/print');
        if (!$resources) throw new RuntimeException('Data resource router tidak tersedia.');
        $r = $resources[0];
        $total = (int)($r['total-memory'] ?? 0);
        $free = (int)($r['free-memory'] ?? 0);
        $used = max(0, $total - $free);
        return [
            'online' => true,
            'identity' => $identity[0]['name'] ?? 'Tidak diketahui',
            'version' => $r['version'] ?? '-',
            'cpu_load' => $r['cpu-load'] ?? '-',
            'total_memory' => $total,
            'free_memory' => $free,
            'used_memory' => $used,
            'memory_percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
            'uptime' => $r['uptime'] ?? '-'
        ];
    } finally { $api->disconnect(); }
}

function sync_domains_to_mikrotik(
    array $domains,
    string $comment = DNS_MANAGER_COMMENT
): array {
    if (!in_array($comment, [DNS_MANAGER_COMMENT, DNS_SINKHOLE_COMMENT], true)) {
        throw new InvalidArgumentException('Penanda DNS tidak diizinkan.');
    }

    if (empty($domains)) {
        throw new InvalidArgumentException(
            'Daftar domain kosong. Sinkronisasi dibatalkan.'
        );
    }

    // Normalisasi dan validasi sebelum koneksi API.
    $activeDomains = [];

    foreach ($domains as $raw) {
        $domain = strtolower(rtrim(trim((string)$raw), '.'));

        if (valid_domain($domain)) {
            $activeDomains[$domain] = true;
        }
    }

    if (empty($activeDomains)) {
        throw new InvalidArgumentException(
            'Tidak ada domain valid. Sinkronisasi dibatalkan.'
        );
    }

    $api = new RouterOSApi();
    $results = [];

    try {
        $api->connect();

        // Ambil seluruh DNS statis MikroTik.
        $existing = $api->command('/ip/dns/static/print');

        // Hapus hanya entri milik penanda yang sedang diproses.
        foreach ($existing as $item) {
            $name = strtolower($item['name'] ?? '');
            $itemComment = $item['comment'] ?? '';
            $dynamic = $item['dynamic'] ?? 'false';
            $id = $item['.id'] ?? '';

            if (
                $itemComment !== $comment ||
                $dynamic === 'true' ||
                $id === ''
            ) {
                continue;
            }

            if (!isset($activeDomains[$name])) {
                $api->command('/ip/dns/static/remove', [
                    '.id' => $id
                ]);

                $results[] = [
                    'domain' => $name,
                    'status' => 'Dihapus',
                    'message' => 'Entri DNS milik penanda aplikasi dibersihkan.'
                ];
            }
        }

        // Baca ulang DNS setelah proses pembersihan.
        $existing = $api->command('/ip/dns/static/print');

        foreach (array_keys($activeDomains) as $domain) {
            $matches = array_values(array_filter(
                $existing,
                fn($item) => strtolower($item['name'] ?? '') === $domain
            ));

            $owned = array_values(array_filter(
                $matches,
                fn($item) =>
                    ($item['comment'] ?? '') === $comment &&
                    ($item['dynamic'] ?? 'false') !== 'true'
            ));

            if ($owned) {
                $results[] = [
                    'domain' => $domain,
                    'status' => 'Sudah disinkronkan',
                    'message' => 'Entri DNS milik aplikasi sudah tersedia.'
                ];
                continue;
            }

            if ($matches) {
                $results[] = [
                    'domain' => $domain,
                    'status' => 'Konflik',
                    'message' => 'Nama domain digunakan entri DNS lain.'
                ];
                continue;
            }

            try {
                $api->command('/ip/dns/static/add', [
                    'name' => $domain,
                    'type' => 'NXDOMAIN',
                    'comment' => $comment,
                    'ttl' => '5m'
                ]);

                $results[] = [
                    'domain' => $domain,
                    'status' => 'Berhasil',
                    'message' => 'DNS NXDOMAIN berhasil ditambahkan.'
                ];

                $existing = $api->command('/ip/dns/static/print');

            } catch (Throwable $error) {
                $results[] = [
                    'domain' => $domain,
                    'status' => 'Gagal',
                    'message' => $error->getMessage()
                ];
            }
        }

        return $results;

    } finally {
        $api->disconnect();
    }
}
function preview_domains_sync(
    array $domains,
    string $comment = DNS_MANAGER_COMMENT
): array {
    if (!in_array($comment, [DNS_MANAGER_COMMENT, DNS_SINKHOLE_COMMENT], true)) {
        throw new InvalidArgumentException('Penanda DNS tidak diizinkan.');
    }

    if (empty($domains)) {
        return [];
    }

    // Normalisasi dan validasi domain.
    $activeDomains = [];

    foreach ($domains as $raw) {
        $domain = strtolower(rtrim(trim((string)$raw), '.'));

        if (valid_domain($domain)) {
            $activeDomains[$domain] = true;
        }
    }

    if (empty($activeDomains)) {
        throw new InvalidArgumentException('Tidak ada domain valid untuk disimulasikan.');
    }

    $api = new RouterOSApi();
    $results = [];

    try {
        $api->connect();

        // Hanya membaca DNS statis MikroTik.
        $existing = $api->command('/ip/dns/static/print');

        // Periksa entri lama milik aplikasi yang nantinya akan dibersihkan.
        foreach ($existing as $item) {
            $name = strtolower($item['name'] ?? '');
            $itemComment = $item['comment'] ?? '';
            $dynamic = $item['dynamic'] ?? 'false';
            $id = $item['.id'] ?? '';

            if (
                $itemComment === $comment &&
                $dynamic !== 'true' &&
                $id !== '' &&
                !isset($activeDomains[$name])
            ) {
                $results[] = [
                    'domain' => $name,
                    'status' => 'Akan dihapus',
                    'message' => 'Entri milik aplikasi sudah tidak termasuk domain aktif.'
                ];
            }
        }

        // Periksa setiap domain aktif.
        foreach (array_keys($activeDomains) as $domain) {
            $matches = array_values(array_filter(
                $existing,
                fn($item) => strtolower($item['name'] ?? '') === $domain
            ));

            $owned = array_values(array_filter(
                $matches,
                fn($item) =>
                    ($item['comment'] ?? '') === $comment &&
                    ($item['dynamic'] ?? 'false') !== 'true'
            ));

            if ($owned) {
                $results[] = [
                    'domain' => $domain,
                    'status' => 'Sudah disinkronkan',
                    'message' => 'Entri DNS milik aplikasi sudah tersedia.'
                ];
                continue;
            }

            if ($matches) {
                $results[] = [
                    'domain' => $domain,
                    'status' => 'Konflik',
                    'message' => 'Nama domain digunakan entri DNS lain.'
                ];
                continue;
            }

            $results[] = [
                'domain' => $domain,
                'status' => 'Akan ditambahkan',
                'message' => 'Entri DNS NXDOMAIN direncanakan untuk ditambahkan.'
            ];
        }

        return $results;

    } finally {
        $api->disconnect();
    }
}
