<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app.php';
migrate();

$error = '';
$notice = '';

if (isset($_GET['logout'])) {
    session_destroy();
    redirect('admin/');
}

if (!admin_logged()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            verify_csrf();
            $admin = fetch_one('SELECT * FROM admins WHERE email=?', [trim((string) ($_POST['email'] ?? ''))]);
            if (!$admin || !password_verify((string) ($_POST['password'] ?? ''), $admin['password'])) {
                throw new RuntimeException('E-posta veya şifre yanlış.');
            }
            $_SESSION['admin_id'] = $admin['id'];
            redirect('admin/');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
    ?><!doctype html>
    <html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Yönetici Girişi</title><link rel="stylesheet" href="<?= e(url('assets/admin.css?v=5.0.0')) ?>"></head>
    <body class="login-page"><form method="post" class="login-card"><div class="login-mark">SGB</div><small>YÖNETİM MERKEZİ</small><h1>Kulüp paneline giriş</h1><p>İçerik, takım, başvuru ve operasyon yönetimi.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><label>E-posta<input type="email" name="email" required autofocus></label><label>Şifre<input type="password" name="password" required></label><button>Giriş Yap</button></form></body></html><?php
    exit;
}

require_admin();
$allowedSections = ['dashboard','settings','teams','players','events','staff','news','gallery','sponsors','socials','forms','applications','standings','system'];
$section = (string) ($_GET['section'] ?? 'dashboard');
if (!in_array($section, $allowedSections, true)) {
    $section = 'dashboard';
}

require __DIR__ . '/actions.php';

$menu = [
    'dashboard' => ['Gösterge', '⌂'],
    'settings' => ['Site Ayarları', '◉'],
    'teams' => ['Takımlar', '◈'],
    'players' => ['Oyuncular', '●'],
    'events' => ['Etkinlikler', '◷'],
    'staff' => ['Yönetim & Hocalar', '◆'],
    'news' => ['Haberler', '▤'],
    'gallery' => ['Galeri', '▦'],
    'sponsors' => ['Sponsorlar & Reklam', '▰'],
    'socials' => ['İletişim & Sosyal', '◎'],
    'forms' => ['Başvuru Formları', '☷'],
    'applications' => ['Başvurular', '✉'],
    'standings' => ['Puan Durumu', '≡'],
    'system' => ['Sistem Kontrolü', '✓'],
];
$newApplicationsCount = (int) (fetch_one("SELECT COUNT(*) total FROM applications WHERE status='Yeni'")['total'] ?? 0);
?><!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($menu[$section][0]) ?> | SGB Yönetim</title>
    <link rel="stylesheet" href="<?= e(url('assets/admin.css?v=5.0.0')) ?>">
</head>
<body class="admin-body">
<aside class="admin-sidebar">
    <div class="admin-brand"><span>SGB</span><div><b>Yönetim Merkezi</b><small>V5.0</small></div></div>
    <nav><?php foreach ($menu as $key => [$label,$icon]): ?><a class="<?= $section === $key ? 'active' : '' ?>" href="<?= e(url('admin/index.php?section=' . $key)) ?>"><i><?= e($icon) ?></i><span><?= e($label) ?></span><?php if ($key === 'applications' && $newApplicationsCount > 0): ?><em><?= $newApplicationsCount ?></em><?php endif; ?></a><?php endforeach; ?></nav>
    <div class="sidebar-bottom"><a href="<?= e(url('index.php')) ?>" target="_blank">Siteyi Gör ↗</a><a href="<?= e(url('admin/index.php?logout=1')) ?>">Güvenli Çıkış</a></div>
</aside>
<div class="admin-shell">
    <header class="admin-top"><div><button class="sidebar-toggle" type="button">☰</button><small><?= e(mb_strtoupper($menu[$section][0])) ?></small><h1><?= e($menu[$section][0]) ?></h1></div><div class="top-actions"><a href="<?= e(url('index.php')) ?>" target="_blank">Canlı Site ↗</a><span><?= e(date('d.m.Y · H:i')) ?></span></div></header>
    <main class="admin-content">
        <?php if ($notice): ?><div class="alert success"><?= e($notice) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <?php require __DIR__ . '/sections/' . $section . '.php'; ?>
    </main>
</div>
<script src="<?= e(url('assets/admin.js?v=5.0.0')) ?>"></script>
</body></html>
