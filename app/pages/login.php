<?php
if (!empty($_SESSION['logged_in'])) redirect('/');
$pageTitle = 'Login';
$errors = take_flashes();
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | RGI DNS Manager</title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="login-page">
<div class="login-shell">
    <div class="login-brand"><img src="/assets/images/rgi.png" alt="Logo RGI"><div><strong>RGI DNS MANAGER</strong><small>NETWORK MANAGEMENT SYSTEM</small></div></div>
    <div class="login-box">
        <h2>Selamat Datang</h2><p class="subtitle">Masuk untuk mengelola jaringan DNS Anda</p>
        <?php foreach ($errors as $notice): ?><div class="message <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div><?php endforeach; ?>
        <form method="post" action="/login">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" autocomplete="username" placeholder="Masukkan username" required>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
            <button class="primary-button full" type="submit">Masuk ke Dashboard →</button>
        </form>
        <div class="security">◈ Login aman · Administrator</div>
    </div>
    <div class="login-footer">Sistem Manajemen DNS TKJ Angkatan 35<br>© <?= date('Y') ?> RGI DNS Manager</div>
</div>
</body></html>
