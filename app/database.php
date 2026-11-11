<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('Ekstensi PHP PDO SQLite belum aktif. Instal php-sqlite3.');
    }
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function initialize_database(): void {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT,
        is_active INTEGER DEFAULT 1
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS domains (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT NOT NULL UNIQUE,
        category_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id)
    )");
    $cols = $pdo->query("PRAGMA table_info(domains)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('sync_status', $cols, true)) {
        $pdo->exec("ALTER TABLE domains ADD COLUMN sync_status TEXT DEFAULT 'Belum disinkronkan'");
    }
    if (!in_array('synced_at', $cols, true)) {
        $pdo->exec("ALTER TABLE domains ADD COLUMN synced_at TIMESTAMP");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        activity TEXT NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $insert = $pdo->prepare("INSERT OR IGNORE INTO categories (name, description) VALUES (?, ?)");
foreach ([
    ['Judi Online', 'Domain perjudian online'],
    ['Konten Dewasa', 'Domain konten dewasa'],
    ['Phishing', 'Domain yang terindikasi penipuan dan pencurian data'],
    ['Malware', 'Domain yang terindikasi menyebarkan perangkat lunak berbahaya'],
    ['Torrent', 'Domain distribusi torrent'],
    ['Lainnya', 'Kategori domain lainnya'],
] as $category) {
    $insert->execute($category);
}
}

function add_log(string $activity, string $details = ''): void {
    $stmt = db()->prepare('INSERT INTO activity_logs (activity, details) VALUES (?, ?)');
    $stmt->execute([$activity, $details]);
}
