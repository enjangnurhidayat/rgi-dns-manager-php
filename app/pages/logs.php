<?php
require_login();
$pageTitle = 'Log Aktivitas';
$activePage = 'logs';
$logs = db()->query("SELECT id, activity, details, created_at FROM activity_logs ORDER BY id DESC LIMIT 100")->fetchAll();
require APP_ROOT . '/app/partials/header.php';
?>
<section class="intro"><h2>Riwayat Aktivitas Sistem</h2><p>Halaman ini menampilkan catatan aktivitas administrator dalam mengelola domain dan melakukan sinkronisasi DNS ke perangkat MikroTik.</p></section>
<section class="card">
    <div class="card-head"><h2>◷ Daftar Aktivitas</h2><span class="count"><?= count($logs) ?> Catatan</span></div>
    <div class="table-wrap"><table class="logs-table"><thead><tr><th>NO.</th><th>AKTIVITAS</th><th>DETAIL</th><th>WAKTU</th></tr></thead><tbody>
    <?php if (!$logs): ?><tr><td colspan="4" class="empty-cell">Belum ada aktivitas.</td></tr><?php endif; ?>
    <?php foreach ($logs as $index => $item): ?><tr><td><?= $index + 1 ?></td><td class="activity-name"><?= e($item['activity']) ?></td><td class="details-cell"><?= e($item['details'] ?: '-') ?></td><td class="time-cell"><?= e($item['created_at']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <a class="back-link" href="/">← Kembali ke Dashboard</a>
</section>
<?php require APP_ROOT . '/app/partials/footer.php'; ?>
