<?php
$pageTitle = $pageTitle ?? 'RGI DNS Manager';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | RGI DNS Manager</title>
<link rel="stylesheet" href="/assets/css/app.css">

<link rel="icon" type="image/png" href="/assets/images/rgi.png">
<link rel="apple-touch-icon" href="/assets/images/rgi.png">

</head>
<body>
<div class="layout">
<aside class="sidebar">
    <div class="brand">
        <img class="brand-logo" src="/assets/images/rgi.png" alt="Logo Rumah Gemilang Indonesia">
        <div><div class="brand-name">RGI DNS<br>MANAGER</div><small class="brand-subtitle">NETWORK MANAGEMENT</small></div>
    </div>
    <div class="nav-title">MENU UTAMA</div>
    <nav class="nav">
<a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="/">
    <span class="nav-icon">▦</span>Dashboard
</a>

<a class="<?= $activePage === 'domains' ? 'active' : '' ?>" href="/domains">
    <span class="nav-icon">◈</span>Manajemen Domain
</a>

<a class="<?= $activePage === 'sinkhole' ? 'active' : '' ?>" href="/sinkhole">
    <span class="nav-icon">⛨</span>Manajemen DNS Sinkhole
</a>

<a class="<?= $activePage === 'logs' ? 'active' : '' ?>" href="/logs">
    <span class="nav-icon">◷</span>Log Aktivitas
</a>
    </nav>
    <div class="sidebar-footer">Sistem Manajemen DNS<br>TKJ Angkatan 35 · Versi 1.0.0</div>
</aside>
<main class="main">
<header class="topbar">
    <div><div class="breadcrumb">Workspace / <?= e($pageTitle) ?></div><h1><?= e($pageTitle) ?></h1></div>
    <div class="user-area">
        <div class="user-pill">● <?= e($_SESSION['username'] ?? 'Administrator') ?></div>
        <form method="post" action="/logout">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button class="logout-button" type="submit">Logout ↗</button>
        </form>
    </div>
</header>
<?php foreach (take_flashes() as $notice): ?>
<div class="message <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div>
<?php endforeach; ?>
