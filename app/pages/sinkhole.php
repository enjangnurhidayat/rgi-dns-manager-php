<?php
require_login();

$pageTitle = 'Manajemen DNS Sinkhole';
$activePage = 'sinkhole';

$previewResults = $_SESSION['sinkhole_preview'] ?? [];
$previewMessage = $_SESSION['sinkhole_preview_message'] ?? '';

unset(
    $_SESSION['sinkhole_preview'],
    $_SESSION['sinkhole_preview_message']
);

$categories = [
    'Judi Online',
    'Konten Dewasa',
    'Phishing',
    'Malware',
    'Lainnya'
];

$categoryStmt = db()->query("
    SELECT id, name
    FROM categories
    WHERE is_active = 1
      AND name IN ('Judi Online', 'Konten Dewasa', 'Phishing', 'Malware', 'Lainnya')
    ORDER BY id ASC
");

$categoryOptions = $categoryStmt->fetchAll();

$placeholders = implode(',', array_fill(0, count($categories), '?'));

$stmt = db()->prepare("
    SELECT
        domains.*,
        categories.name AS category_name
    FROM domains
    INNER JOIN categories
        ON domains.category_id = categories.id
    WHERE categories.name IN ($placeholders)
    ORDER BY domains.id DESC
");

$stmt->execute($categories);
$domainList = $stmt->fetchAll();

$totalDomain = count($domainList);
$totalAktif = count(array_filter(
    $domainList,
    fn($item) => (int)$item['is_active'] === 1
));
$totalNonaktif = $totalDomain - $totalAktif;

require APP_ROOT . '/app/partials/header.php';
?>

<section class="intro">
    <h2>Manajemen DNS Sinkhole</h2>
    <p>
        Kelola daftar domain yang akan diblokir melalui sistem DNS Sinkhole
        dan pantau status pengelolaannya.
    </p>
</section>

<?php if ($previewMessage !== ''): ?>
<section class="card">
    <div class="card-head">
        <h2>Hasil Simulasi DNS Sinkhole</h2>
    </div>

    <p><?= e($previewMessage) ?></p>

    <?php if (!empty($previewResults)): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Domain</th>
                        <th>Rencana</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewResults as $index => $result): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= e($result['domain']) ?></td>
                            <td><?= e($result['status']) ?></td>
                            <td><?= e($result['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <h2>◈ Ringkasan DNS Sinkhole</h2>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Domain</h3>
            <p><?= $totalDomain ?></p>
        </div>

        <div class="stat-card">
            <h3>Domain Aktif</h3>
            <p><?= $totalAktif ?></p>
        </div>

        <div class="stat-card">
            <h3>Domain Nonaktif</h3>
            <p><?= $totalNonaktif ?></p>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h2>＋ Tambah Domain Sinkhole</h2>
    </div>
    <form class="add-form" action="/sinkhole/add" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <input
            type="text"
            name="domain"
            placeholder="contoh-domain.com"
            maxlength="253"
            required
        >

        <select name="category_id" required>
            <option value="">Pilih Kategori</option>

            <?php foreach ($categoryOptions as $category): ?>
                <option value="<?= (int)$category['id'] ?>">
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="primary-button" type="submit">
            Simpan Domain
        </button>
    </form>

    <p class="form-hint">
        Domain baru disimpan dalam status nonaktif dan belum dikirim ke MikroTik.
    </p>

</section>
<section class="card">
    <div class="card-head">
        <h2>◈ Daftar Domain Sinkhole</h2>

        <form action="/sinkhole/preview" method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button class="primary-button" type="submit">
                Simulasikan (Dry Run)
            </button>
        </form>

        <form action="/sinkhole/sync" method="post"
              onsubmit="return confirm('Sinkronkan domain aktif DNS Sinkhole ke MikroTik?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button class="primary-button" type="submit">
                Sinkronkan ke MikroTik
            </button>
        </form>
    </div>

    <div class="table-wrap">

<table>
    <thead>
        <tr>
            <th>No.</th>
            <th>Domain</th>
            <th>Kategori</th>
            <th>Status</th>
            <th>Sinkronisasi</th>
            <th>Aksi</th>
        </tr>
    </thead>

    <tbody>
        <?php if (!$domainList): ?>
            <tr>
                <td colspan="6" class="empty-cell">
                    Belum ada domain dalam kategori DNS Sinkhole.
                </td>
            </tr>
        <?php endif; ?>


<?php foreach ($domainList as $index => $item): ?>
    <tr>
        <td><?= $index + 1 ?></td>

        <td class="domain-name">
            <?= e($item['domain']) ?>
        </td>

        <td>
            <?= e($item['category_name']) ?>
        </td>

        <td>
            <span class="badge <?= $item['is_active'] ? 'active' : 'inactive' ?>">
                ● <?= $item['is_active'] ? 'Aktif' : 'Nonaktif' ?>
            </span>
        </td>

        <td>
            <?php
            $sync = $item['sync_status'] ?? 'Belum disinkronkan';

            $syncClass = $sync === 'Tersinkron'
                ? 'active'
                : (in_array($sync, ['Konflik', 'Gagal'], true)
                    ? 'inactive'
                    : '');
            ?>

            <span class="badge <?= e($syncClass) ?>">
                <?= e($sync) ?>
            </span>
        </td>

        <td>
            <form class="sinkhole-action-form"
                  action="/sinkhole/toggle/<?= (int)$item['id'] ?>"
                  method="post"
                  onsubmit="return confirm('Ubah status domain Sinkhole ini?')">

                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

                <button
                    type="submit"
                    class="sinkhole-action-button <?= (int)$item['is_active'] === 1 ? 'danger' : '' ?>">
                    <?= (int)$item['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                </button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>

    </tbody>
</table>

    </div>
</section>

<div class="page-foot">
    RGI DNS MANAGER · NETWORK MANAGEMENT SYSTEM
</div>

<?php require APP_ROOT . '/app/partials/footer.php'; ?>
