<?php
declare(strict_types=1);

// Sajikan file statis langsung saat menggunakan PHP Development Server.
if (PHP_SAPI === 'cli-server') {
    $publicRoot = realpath(__DIR__);
    $requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $requestedFile = realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim($requestPath, '/'));

    if (
        $requestedFile !== false &&
        str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR) &&
        is_file($requestedFile)
    ) {
        return false;
    }
}
require dirname(__DIR__) . '/app/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'POST') verify_csrf();

    if ($path === '/login' && $method === 'GET') {
        require APP_ROOT . '/app/pages/login.php';
        exit;
    }
    if ($path === '/login' && $method === 'POST') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === ADMIN_USERNAME && ADMIN_PASSWORD_HASH !== '' && password_verify($password, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION = ['logged_in' => true, 'username' => $username, '_csrf' => bin2hex(random_bytes(32))];
            add_log('Login administrator', $username);
            redirect('/');
        }
        flash('Username atau password salah.', 'error');
        redirect('/login');
    }
    if ($path === '/logout' && $method === 'POST') {
        require_login();
        $username = (string)($_SESSION['username'] ?? 'admin');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        flash('Anda berhasil logout.', 'success');
        add_log('Logout administrator', $username);
        redirect('/login');
    }

    if ($path === '/' && $method === 'GET') {
        require_login(); require APP_ROOT . '/app/pages/dashboard.php'; exit;
    }
    if ($path === '/domains' && $method === 'GET') {
        require_login(); require APP_ROOT . '/app/pages/domains.php'; exit;
    }
if ($path === '/sinkhole' && $method === 'GET') {
    require_login();
    require APP_ROOT . '/app/pages/sinkhole.php';
    exit;
}
    if ($path === '/logs' && $method === 'GET') {
        require_login(); require APP_ROOT . '/app/pages/logs.php'; exit;
    }

    if ($path === '/domains/add' && $method === 'POST') {
        require_login();
        $domain = strtolower(rtrim(trim((string)($_POST['domain'] ?? '')), '.'));
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
        if (!valid_domain($domain)) {
            flash('Format domain tidak valid.', 'error'); redirect('/domains');
        }
        if (!$categoryId) {
            flash('Kategori tidak valid.', 'error'); redirect('/domains');
        }
        $check = db()->prepare('SELECT id FROM categories WHERE id = ? AND is_active = 1');
        $check->execute([$categoryId]);
        if (!$check->fetch()) {
            flash('Kategori tidak ditemukan.', 'error'); redirect('/domains');
        }
        try {
            $insert = db()->prepare('INSERT INTO domains (domain, category_id) VALUES (?, ?)');
            $insert->execute([$domain, $categoryId]);
            add_log('Tambah domain', $domain);
            flash('Domain berhasil ditambahkan.', 'success');
        } catch (PDOException $error) {
            flash('Domain sudah terdaftar atau terjadi kesalahan.', 'error');
        }
        redirect('/domains');
    }
    if ($path === '/sinkhole/add' && $method === 'POST') {
        require_login();

        $domain = strtolower(rtrim(trim((string)($_POST['domain'] ?? '')), '.'));
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);

        if (!valid_domain($domain)) {
            flash('Format domain tidak valid.', 'error');
            redirect('/sinkhole');
        }

        if (!$categoryId) {
            flash('Kategori tidak valid.', 'error');
            redirect('/sinkhole');
        }

        $check = db()->prepare("
            SELECT id
            FROM categories
            WHERE id = ?
              AND is_active = 1
              AND name IN ('Judi Online', 'Konten Dewasa', 'Phishing', 'Malware', 'Lainnya')
        ");
        $check->execute([$categoryId]);

        if (!$check->fetch()) {
            flash('Kategori Sinkhole tidak ditemukan.', 'error');
            redirect('/sinkhole');
        }

        try {
            $insert = db()->prepare("
                INSERT INTO domains
                    (domain, category_id, is_active, sync_status)
                VALUES (?, ?, 0, 'Belum disinkronkan')
            ");

            $insert->execute([$domain, $categoryId]);

            add_log('Tambah domain Sinkhole', $domain);
            flash('Domain berhasil ditambahkan dalam status nonaktif.', 'success');

        } catch (PDOException $error) {
            flash('Domain sudah terdaftar atau terjadi kesalahan.', 'error');
        }

        redirect('/sinkhole');
    }
    if ($path === '/sinkhole/preview' && $method === 'POST') {
        require_login();

        try {
            $active = db()->query("
                SELECT domains.domain
                FROM domains
                INNER JOIN categories
                    ON domains.category_id = categories.id
                WHERE domains.is_active = 1
                  AND categories.name IN (
                      'Judi Online',
                      'Konten Dewasa',
                      'Phishing',
                      'Malware',
                      'Lainnya'
                  )
                ORDER BY domains.id ASC
            ")->fetchAll(PDO::FETCH_COLUMN);

            if (empty($active)) {
                $_SESSION['sinkhole_preview'] = [];
                $_SESSION['sinkhole_preview_message'] =
                    'Belum ada domain Sinkhole aktif untuk disimulasikan.';

                add_log(
                    'Simulasi DNS Sinkhole',
                    'Tidak ada domain aktif. Tidak ada perubahan pada MikroTik.'
                );
            } else {
                $_SESSION['sinkhole_preview'] = preview_domains_sync(
                    $active,
                    DNS_SINKHOLE_COMMENT
                );

                $_SESSION['sinkhole_preview_message'] =
                    'Simulasi selesai. Tidak ada perubahan pada DNS MikroTik.';

                add_log(
                    'Simulasi DNS Sinkhole',
                    'Simulasi dilakukan terhadap ' . count($active) . ' domain aktif.'
                );
            }

        } catch (Throwable $error) {
            error_log('Sinkhole preview: ' . $error->getMessage());

            $_SESSION['sinkhole_preview'] = [];
            $_SESSION['sinkhole_preview_message'] =
                'Simulasi gagal. Periksa koneksi dan konfigurasi MikroTik API.';

            add_log(
                'Simulasi DNS Sinkhole gagal',
                $error->getMessage()
            );
        }

        redirect('/sinkhole');
    }

    if ($path === '/sinkhole/sync' && $method === 'POST') {
        require_login();

        $pdo = db();

        try {
            // Ambil hanya domain aktif kategori Sinkhole.
            $active = $pdo->query("
                SELECT domains.domain
                FROM domains
                INNER JOIN categories
                    ON domains.category_id = categories.id
                WHERE domains.is_active = 1
                  AND categories.name IN (
                      'Judi Online',
                      'Konten Dewasa',
                      'Phishing',
                      'Malware',
                      'Lainnya'
                  )
                ORDER BY domains.id ASC
            ")->fetchAll(PDO::FETCH_COLUMN);

            // Pengamanan: jangan ubah MikroTik jika tidak ada domain aktif.
            if (empty($active)) {
                add_log(
                    'Sinkronisasi DNS Sinkhole dibatalkan',
                    'Tidak ada domain Sinkhole aktif. MikroTik tidak diubah.'
                );

                flash(
                    'Sinkronisasi dibatalkan. Belum ada domain Sinkhole aktif. Tidak ada perubahan pada MikroTik.',
                    'warning'
                );
                redirect('/sinkhole');
            }

            // Sinkronisasi menggunakan penanda khusus Sinkhole.
            $results = sync_domains_to_mikrotik(
                $active,
                DNS_SINKHOLE_COMMENT
            );

            $success = 0;
            $failed = 0;

            $update = $pdo->prepare("
                UPDATE domains
                SET sync_status = ?,
                    synced_at = CASE
                        WHEN ? IN ('Tersinkron', 'Dihapus') THEN CURRENT_TIMESTAMP
                        ELSE NULL
                    END
                WHERE domain = ?
                  AND category_id IN (
                      SELECT id FROM categories
                      WHERE name IN (
                          'Judi Online',
                          'Konten Dewasa',
                          'Phishing',
                          'Malware',
                          'Lainnya'
                      )
                  )
            ");

            foreach ($results as $result) {
                $resultStatus = $result['status'];

                if (in_array(
                    $resultStatus,
                    ['Berhasil', 'Sudah disinkronkan'],
                    true
                )) {
                    $status = 'Tersinkron';
                } else {
                    $status = $resultStatus;
                }

                $ok = in_array($status, ['Tersinkron', 'Dihapus'], true);

                $success += $ok ? 1 : 0;
                $failed += $ok ? 0 : 1;

                $update->execute([
                    $status,
                    $status,
                    $result['domain']
                ]);

                add_log(
                    'Sinkronisasi DNS Sinkhole',
                    $result['domain'] . ' | ' .
                    $resultStatus . ' | ' .
                    $result['message']
                );
            }

            flash(
                "Sinkronisasi Sinkhole selesai. Berhasil: {$success}, Gagal atau konflik: {$failed}.",
                $failed === 0 ? 'success' : 'warning'
            );

        } catch (Throwable $error) {
            error_log('Sinkhole sync: ' . $error->getMessage());
            add_log('Sinkronisasi DNS Sinkhole gagal', $error->getMessage());
            flash('Sinkronisasi Sinkhole gagal. Periksa koneksi dan konfigurasi MikroTik API.', 'error');
        }

        redirect('/sinkhole');
    }

    if (preg_match('#^/sinkhole/toggle/([0-9]+)$#', $path, $match) && $method === 'POST') {
        require_login();

        $id = (int)$match[1];

        $find = db()->prepare("
            SELECT domains.domain, domains.is_active
            FROM domains
            INNER JOIN categories
                ON domains.category_id = categories.id
            WHERE domains.id = ?
              AND categories.name IN (
                  'Judi Online',
                  'Konten Dewasa',
                  'Phishing',
                  'Malware',
                  'Lainnya'
              )
        ");

        $find->execute([$id]);
        $domain = $find->fetch();

        if (!$domain) {
            flash('Domain Sinkhole tidak ditemukan.', 'warning');
            redirect('/sinkhole');
        }

        $statusLama = (int)$domain['is_active'] === 1 ? 'Aktif' : 'Nonaktif';
        $statusBaru = (int)$domain['is_active'] === 1 ? 'Nonaktif' : 'Aktif';

        $update = db()->prepare("
            UPDATE domains
            SET is_active = CASE
                WHEN is_active = 1 THEN 0
                ELSE 1
            END,
            sync_status = 'Belum disinkronkan',
            synced_at = NULL
            WHERE id = ?
        ");

        $update->execute([$id]);

        add_log(
            'Ubah status Sinkhole',
            $domain['domain'] . ' | ' . $statusLama . ' → ' . $statusBaru
        );

        flash('Status domain Sinkhole berhasil diperbarui.', 'success');
        redirect('/sinkhole');
    }
    if (preg_match('#^/domains/toggle/([0-9]+)$#', $path, $match) && $method === 'POST') {
        require_login();

        $id = (int)$match[1];

        // Ambil data domain sebelum status diubah
        $find = db()->prepare('SELECT domain, is_active FROM domains WHERE id = ?');
        $find->execute([$id]);
        $domain = $find->fetch();

        if (!$domain) {
            flash('Domain tidak ditemukan.', 'warning');
            redirect('/domains');
        }

        $namaDomain = $domain['domain'];
        $statusLama = (int)$domain['is_active'] === 1 ? 'Aktif' : 'Nonaktif';
        $statusBaru = (int)$domain['is_active'] === 1 ? 'Nonaktif' : 'Aktif';

        // Perbarui status domain
        $stmt = db()->prepare('
            UPDATE domains
            SET is_active = CASE
                WHEN is_active = 1 THEN 0
                ELSE 1
            END
            WHERE id = ?
        ');
        $stmt->execute([$id]);

        // Simpan riwayat perubahan
        add_log(
            'Ubah status domain',
            $namaDomain . ' | ' . $statusLama . ' → ' . $statusBaru
        );

        flash('Status domain berhasil diperbarui.', 'success');
        redirect('/domains');
    }

    if (preg_match('#^/domains/delete/([0-9]+)$#', $path, $match) && $method === 'POST') {
        require_login();
        $id = (int)$match[1];
        $find = db()->prepare('SELECT domain FROM domains WHERE id = ?');
        $find->execute([$id]);
        $row = $find->fetch();
        if ($row) {
            $delete = db()->prepare('DELETE FROM domains WHERE id = ?');
            $delete->execute([$id]);
            add_log('Hapus domain', $row['domain']);
            flash('Domain berhasil dihapus.', 'success');
        } else flash('Domain tidak ditemukan.', 'warning');
        redirect('/domains');
    }

    if ($path === '/domains/sync' && $method === 'POST') {
        require_login();
        $pdo = db();

        try {
            // Ambil seluruh domain aktif.
            $active = $pdo->query(
                'SELECT id, domain FROM domains WHERE is_active = 1 ORDER BY id ASC'
            )->fetchAll();

            // Tetap jalankan meskipun tidak ada domain aktif,
            // agar DNS lama milik aplikasi dapat dibersihkan.
            $results = sync_domains_to_mikrotik(
                array_column($active, 'domain')
            );

            $success = 0;
            $failed = 0;

            $update = $pdo->prepare(
                'UPDATE domains
                 SET sync_status = ?,
                     synced_at = CASE WHEN ? = 1 THEN CURRENT_TIMESTAMP ELSE synced_at END
                 WHERE domain = ?'
            );

            foreach ($results as $result) {
                $resultStatus = $result['status'];

                $ok = in_array(
                    $resultStatus,
                    ['Berhasil', 'Sudah disinkronkan', 'Dihapus'],
                    true
                );

                $success += $ok ? 1 : 0;
                $failed += $ok ? 0 : 1;

                if ($resultStatus === 'Dihapus') {
                    $status = 'Dihapus';
                } elseif ($ok) {
                    $status = 'Tersinkron';
                } else {
                    $status = $resultStatus;
                }

                $update->execute([
                    $status,
                    in_array($resultStatus, ['Berhasil', 'Sudah disinkronkan'], true) ? 1 : 0,
                    $result['domain']
                ]);

                add_log(
                    'Sinkronisasi DNS',
                    $result['domain'] . ' | ' .
                    $resultStatus . ' | ' .
                    $result['message']
                );
            }

            flash(
                "Sinkronisasi selesai. Berhasil: {$success}, Gagal atau konflik: {$failed}.",
                $failed === 0 ? 'success' : 'warning'
            );
        } catch (Throwable $error) {
            error_log('DNS sync: ' . $error->getMessage());
            add_log('Sinkronisasi DNS gagal', $error->getMessage());
            flash('Sinkronisasi gagal. Periksa koneksi dan konfigurasi MikroTik API.', 'error');
        }

        redirect('/domains');
    }

    http_response_code(404);
    echo '404 - Halaman tidak ditemukan';

} catch (Throwable $error) {
    error_log('Application error: ' . $error->getMessage());
    http_response_code(500);
    echo 'Terjadi kesalahan pada aplikasi. Periksa log server untuk detail.';
}
