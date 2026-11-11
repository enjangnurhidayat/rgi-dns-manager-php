<?php
require_login();

$pageTitle = 'Dashboard Overview';
$activePage = 'dashboard';

$router = router_default();

try {
    $router = get_router_status();
} catch (Throwable $error) {
    error_log('MikroTik status: ' . $error->getMessage());
}

$pdo = db();

// Statistik domain umum.
$totalDomains = (int)$pdo->query(
    'SELECT COUNT(*) FROM domains'
)->fetchColumn();

$activeDomains = (int)$pdo->query(
    'SELECT COUNT(*) FROM domains WHERE is_active = 1'
)->fetchColumn();

$inactiveDomains = $totalDomains - $activeDomains;

// Statistik domain yang termasuk kategori DNS Sinkhole.
$eligibleActiveDomains = (int)$pdo->query("
    SELECT COUNT(*)
    FROM domains d
    INNER JOIN categories c ON c.id = d.category_id
    WHERE d.is_active = 1
      AND c.name IN (
          'Judi Online',
          'Konten Dewasa',
          'Phishing',
          'Malware',
          'Lainnya'
      )
")->fetchColumn();

// Statistik seluruh kategori, termasuk kategori non-Sinkhole.
$categoryStats = $pdo->query("
    SELECT
        c.name,
        COUNT(d.id) AS total,
        COALESCE(SUM(
            CASE WHEN d.is_active = 1 THEN 1 ELSE 0 END
        ), 0) AS active
    FROM categories c
    LEFT JOIN domains d ON d.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

// Aktivitas terbaru.
$recentActivities = $pdo->query("
    SELECT activity, details, created_at
    FROM activity_logs
    ORDER BY id DESC
    LIMIT 5
")->fetchAll();

// Simulasi Dry Run terakhir.
$lastDryRun = $pdo->query("
    SELECT created_at, details
    FROM activity_logs
    WHERE activity = 'Simulasi DNS Sinkhole'
    ORDER BY id DESC
    LIMIT 1
")->fetch();

$routerStatus = $router['online'] ? 'Online' : 'Offline';
$checkedAt = date('d-m-Y H:i:s');

require APP_ROOT . '/app/partials/header.php';
?>

<section class="welcome">
    <h2>Selamat Datang di RGI DNS Manager</h2>
    <p>
        Pusat monitoring dan manajemen DNS berbasis MikroTik.
        Pantau kondisi perangkat, statistik domain, serta aktivitas
        pengelolaan DNS melalui satu dashboard.
    </p>
    <span class="welcome-label">NETWORK MANAGEMENT SYSTEM</span>
</section>

<div class="section-heading">
    <h3>Ringkasan Sistem</h3>
    <span>Data saat halaman dibuka</span>
</div>

<section class="stats">
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">Status Router</span>
            <span class="stat-icon">⌘</span>
        </div>
        <div class="stat-value"><?= e($routerStatus) ?></div>
        <div class="stat-note">Koneksi MikroTik melalui API</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">Total Domain</span>
            <span class="stat-icon">◈</span>
        </div>
        <div class="stat-value"><?= $totalDomains ?></div>
        <div class="stat-note">Seluruh domain terdaftar</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">Domain Aktif</span>
            <span class="stat-icon">✓</span>
        </div>
        <div class="stat-value"><?= $activeDomains ?></div>
        <div class="stat-note">
            <?= $inactiveDomains ?> domain nonaktif
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">CPU Load</span>
            <span class="stat-icon">▥</span>
        </div>
        <div class="stat-value">
            <?= $router['online'] ? e($router['cpu_load']) . '%' : '-' ?>
        </div>
        <div class="stat-note">Penggunaan CPU router</div>
    </div>
</section>

<div class="section-heading">
    <h3>Monitoring Perangkat</h3>
    <span>Informasi MikroTik</span>
</div>

<section class="lower-grid">
    <div class="panel">
        <h3>Informasi Perangkat</h3>

        <div class="device-status">
            <div>
                <strong><?= e($router['identity']) ?></strong>
                <small>MikroTik Router</small>
            </div>

            <span class="status <?= $router['online'] ? 'online' : 'offline' ?>">
                ● <?= $router['online'] ? 'TERHUBUNG' : 'OFFLINE' ?>
            </span>
        </div>

        <div class="details">
            <div class="detail">
                <small>RouterOS Version</small>
                <strong><?= e($router['version']) ?></strong>
            </div>

            <div class="detail">
                <small>Metode Monitoring</small>
                <strong>RouterOS API</strong>
            </div>

            <div class="detail">
                <small>System Uptime</small>
                <strong><?= e($router['uptime']) ?></strong>
            </div>

            <div class="detail">
                <small>Terakhir Diperiksa</small>
                <strong><?= e($checkedAt) ?></strong>
            </div>
        </div>
    </div>

    <div class="panel">
        <h3>Penggunaan Memori Router</h3>

        <div class="stat-value">
            <?= $router['online'] ? e($router['memory_percent']) . '%' : '-' ?>
        </div>

        <div class="memory-progress">
            <div style="width:<?= $router['online'] ? min(100, max(0, (float)$router['memory_percent'])) : 0 ?>%"></div>
        </div>

        <div class="memory-summary">
            <span>RAM digunakan</span>
            <strong><?= format_mib((int)$router['used_memory']) ?></strong>
        </div>

        <div class="memory-summary spaced">
            <span>RAM tersedia</span>
            <strong><?= format_mib((int)$router['free_memory']) ?></strong>
        </div>

        <div class="memory-summary spaced">
            <span>Total RAM</span>
            <strong><?= format_mib((int)$router['total_memory']) ?></strong>
        </div>
    </div>
</section>

<div class="section-heading">
    <h3>Statistik DNS Sinkhole</h3>
    <span>Data berdasarkan database aplikasi</span>
</div>

<section class="stats dashboard-sinkhole-stats">
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">Domain Sinkhole Aktif</span>
            <span class="stat-icon">⛨</span>
        </div>
        <div class="stat-value"><?= $eligibleActiveDomains ?></div>
        <div class="stat-note">Masuk daftar simulasi Sinkhole</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">Status Penerapan</span>
            <span class="stat-icon">◷</span>
        </div>
        <div class="stat-value dashboard-status-text">Dry Run</div>
        <div class="stat-note">Belum menjadi bukti penerapan di router</div>
    </div>
</section>

<section class="lower-grid">
    <div class="panel">
        <h3>Statistik Kategori Domain</h3>

        <?php if ($categoryStats): ?>
            <div class="table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>KATEGORI</th>
                            <th>TOTAL</th>
                            <th>AKTIF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryStats as $category): ?>
                            <tr>
                                <td class="domain-name">
                                    <?= e($category['name']) ?>
                                </td>
                                <td><?= (int)$category['total'] ?></td>
                                <td><?= (int)$category['active'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="dashboard-empty">Belum ada data kategori.</p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3>Simulasi DNS Sinkhole</h3>

        <div class="sinkhole-info">
            <span class="status online">● MODE SIMULASI</span>

            <p>
                Dry Run digunakan untuk memeriksa rencana perubahan
                DNS tanpa menerapkan perubahan pada MikroTik.
            </p>
        </div>

        <div class="detail">
            <small>Jumlah domain aktif dalam cakupan Sinkhole</small>
            <strong><?= $eligibleActiveDomains ?> domain</strong>
        </div>

        <div class="detail dashboard-dryrun-detail">
            <small>Simulasi terakhir</small>
            <strong>
                <?= $lastDryRun ? e($lastDryRun['created_at']) : 'Belum tersedia' ?>
            </strong>
        </div>

        <?php if ($lastDryRun): ?>
            <p class="dashboard-last-run">
                <?= e($lastDryRun['details']) ?>
            </p>
        <?php endif; ?>

        <a class="quick-link" href="/sinkhole">
            Buka Manajemen DNS Sinkhole <span>→</span>
        </a>
    </div>
</section>

<div class="section-heading">
    <h3>Aktivitas Terbaru</h3>
    <a class="dashboard-heading-link" href="/logs">Lihat semua →</a>
</div>

<section class="panel">
    <?php if ($recentActivities): ?>
        <div class="dashboard-activity-list">
            <?php foreach ($recentActivities as $activity): ?>
                <div class="dashboard-activity">
                    <div class="dashboard-activity-marker">●</div>

                    <div class="dashboard-activity-content">
                        <strong><?= e($activity['activity']) ?></strong>

                        <p>
                            <?= e($activity['details'] ?? '-') ?>
                        </p>

                        <small><?= e($activity['created_at']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="dashboard-empty">Belum ada aktivitas tercatat.</p>
    <?php endif; ?>
</section>

<div class="section-heading">
    <h3>Akses Cepat</h3>
    <span>Navigasi pengelolaan</span>
</div>

<section class="dashboard-quick-grid">
    <a class="quick-link" href="/domains">
        Manajemen Domain <span>→</span>
    </a>

    <a class="quick-link" href="/sinkhole">
        Manajemen DNS Sinkhole <span>→</span>
    </a>

    <a class="quick-link" href="/logs">
        Log Aktivitas <span>→</span>
    </a>
</section>

<?php require APP_ROOT . '/app/partials/footer.php'; ?>
