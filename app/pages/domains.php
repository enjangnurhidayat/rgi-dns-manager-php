<?php
require_login();
$pageTitle = 'Manajemen Domain';
$activePage = 'domains';
$stmt = db()->query("SELECT domains.*, categories.name AS category_name FROM domains LEFT JOIN categories ON domains.category_id = categories.id ORDER BY domains.id DESC");
$domainList = $stmt->fetchAll();
$categories = db()->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY id")->fetchAll();
require APP_ROOT . '/app/partials/header.php';
?>
<section class="intro"><h2>Kelola Domain dengan Mudah</h2><p>Tambahkan domain, atur kategori, dan pantau status domain DNS dalam satu tempat.</p></section>
<section class="card">
    <div class="card-head"><h2>＋ Tambah Domain</h2></div>
    <form class="add-form" action="/domains/add" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="text" name="domain" placeholder="contoh-domain.com" maxlength="253" required>
        <select name="category_id" required><option value="">Pilih Kategori</option>
            <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="primary-button" type="submit">Tambah Domain</button>
    </form>
</section>
<section class="card">
    <div class="card-head"><h2>◈ Daftar Domain</h2>
        <form action="/domains/sync" method="post" onsubmit="return confirm('Sinkronkan seluruh domain aktif ke MikroTik?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button class="primary-button" type="submit">⟳ Sinkronkan DNS</button>
        </form>
    </div>
    <div class="table-wrap"><table><thead><tr><th>No.</th><th>Domain</th><th>Kategori</th><th>Status</th><th>Sinkronisasi</th><th>Aksi</th></tr></thead><tbody>
    <?php if (!$domainList): ?><tr><td colspan="6" class="empty-cell">Belum ada domain.</td></tr><?php endif; ?>
    <?php foreach ($domainList as $index => $item): ?>
        <tr>
            <td><?= $index + 1 ?></td><td class="domain-name"><?= e($item['domain']) ?></td><td><?= e($item['category_name'] ?? '-') ?></td>
            <td><span class="badge <?= $item['is_active'] ? 'active' : 'inactive' ?>">● <?= $item['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
            <td><?php
                $sync = $item['sync_status'] ?? 'Belum disinkronkan';
                $syncClass = $sync === 'Tersinkron' ? 'active' : (in_array($sync, ['Konflik','Gagal'], true) ? 'inactive' : '');
            ?><span class="badge <?= e($syncClass) ?>"><?= e($sync) ?></span></td>
            <td><div class="actions">
                <form action="/domains/toggle/<?= (int)$item['id'] ?>" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button type="submit">Ubah Status</button></form>
                <form action="/domains/delete/<?= (int)$item['id'] ?>" method="post" onsubmit="return confirm('Hapus domain ini?')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="danger" type="submit">Hapus</button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<div class="page-foot">RGI DNS MANAGER · NETWORK MANAGEMENT SYSTEM</div>
<?php require APP_ROOT . '/app/partials/footer.php'; ?>
