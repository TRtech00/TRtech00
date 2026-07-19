<?php
declare(strict_types=1);

$error = '';
$root = dirname(__DIR__);
$configFile = $root . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $host = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
        $port = trim((string) ($_POST['db_port'] ?? '3306'));
        $database = trim((string) ($_POST['db_name'] ?? ''));
        $user = trim((string) ($_POST['db_user'] ?? 'root'));
        $password = (string) ($_POST['db_pass'] ?? '');
        $adminName = trim((string) ($_POST['admin_name'] ?? 'Kulüp Yöneticisi'));
        $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
        $adminPassword = (string) ($_POST['admin_password'] ?? '');

        if ($database === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($adminPassword) < 8) {
            throw new RuntimeException('Veritabanı, geçerli e-posta ve en az 8 karakterli yönetici şifresi zorunludur.');
        }

        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $config = [
            'db_host' => $host,
            'db_port' => $port,
            'db_name' => $database,
            'db_user' => $user,
            'db_pass' => $password,
        ];
        $content = "<?php\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($configFile, $content) === false) {
            throw new RuntimeException('config.php oluşturulamadı. Proje klasörüne yazma izni verin.');
        }

        require $root . '/app.php';
        migrate();

        $admin = db()->prepare('INSERT INTO admins(name,email,password) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), password=VALUES(password)');
        $admin->execute([$adminName, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);

        $defaults = [
            'site_name' => 'Sivas Gençlerbirliği Spor',
            'site_short_name' => 'SGB',
            'site_tagline' => 'Önceliğimiz Gençlerimiz, Hedefimiz Gelecek',
            'league_name' => 'Sivas Süper Amatör Ligi',
            'hero_eyebrow' => 'SİVAS GENÇLERBİRLİĞİ',
            'hero_title' => 'Geleceği Sahada Birlikte Kuruyoruz',
            'hero_text' => 'Altyapıdan A takıma uzanan yolculukta gençlere alan, disiplin ve takım ruhu kazandırıyoruz.',
            'primary_color' => '#e3062f',
            'dark_color' => '#0b0e14',
            'surface_color' => '#f4f5f7',
            'contact_city' => 'Sivas',
            'contact_email' => '',
            'contact_phone' => '',
            'footer_text' => 'Sporun birleştirici gücüyle gençleri geleceğe hazırlıyoruz.',
            'about_title' => 'Bir kulüpten fazlası',
            'about_text' => 'Sivas Gençlerbirliği Spor; disiplin, eğitim ve takım kültürünü aynı sahada buluşturur.',
        ];
        foreach ($defaults as $key => $value) {
            set_setting($key, $value);
        }

        $teams = [
            ['A Takım', 'a-takim', 'Süper Amatör', '#e3062f', 1],
            ['U18 Takımı', 'u18', 'U18', '#5b21b6', 2],
            ['U16 Takımı', 'u16', 'U16', '#0f766e', 3],
        ];
        $teamStatement = db()->prepare('INSERT IGNORE INTO teams(name,slug,category,accent,active,sort_order) VALUES(?,?,?,?,1,?)');
        foreach ($teams as $team) {
            $teamStatement->execute($team);
        }

        $fields = [
            ['Ad Soyad', 'full_name', 'text', '', 'Oyuncunun adı ve soyadı', 1, 10],
            ['Telefon', 'phone', 'tel', '', '05xx xxx xx xx', 1, 20],
            ['E-posta', 'email', 'email', '', 'ornek@eposta.com', 0, 30],
            ['Doğum Tarihi', 'birth_date', 'date', '', '', 1, 40],
            ['Oynadığı Mevkiler', 'positions', 'multiselect', implode("\n", positions()), 'Birden fazla mevki seçilebilir', 1, 50],
            ['Önceki Kulüp veya Futbol Okulları', 'previous_clubs', 'textarea', '', 'Varsa kulüp adı ve oynadığı sezonları yazın', 0, 60],
            ['Boy (cm)', 'height', 'number', '', 'Örnek: 178', 0, 70],
            ['Tercih Ettiği Ayak', 'preferred_foot', 'select', "Sağ\nSol\nHer İkisi", '', 0, 80],
            ['Mesaj', 'message', 'textarea', '', 'Kendiniz veya oyuncu hakkında eklemek istedikleriniz', 0, 90],
        ];
        $fieldStatement = db()->prepare('INSERT INTO form_fields(form_type,label,name,field_type,options,placeholder,required,active,sort_order) VALUES(?,?,?,?,?,?,?,1,?) ON DUPLICATE KEY UPDATE label=VALUES(label),field_type=VALUES(field_type),options=VALUES(options),placeholder=VALUES(placeholder),required=VALUES(required),active=1,sort_order=VALUES(sort_order)');
        foreach (['academy', 'first_team'] as $type) {
            foreach ($fields as $field) {
                $fieldStatement->execute([$type, ...$field]);
            }
        }

        header('Location: ' . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/admin/');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        if (is_file($configFile) && !isset($pdo)) {
            @unlink($configFile);
        }
    }
}
?><!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sivas Gençlerbirliği V5 Kurulum</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(circle at 10% 10%,#34101a,#0a0d13 48%);font:15px Inter,system-ui;color:#fff;padding:24px}.box{width:min(920px,100%);background:rgba(20,24,33,.94);border:1px solid rgba(255,255,255,.1);border-radius:30px;padding:38px;box-shadow:0 30px 90px rgba(0,0,0,.45)}h1{font-size:42px;margin:0 0 8px}.muted{color:#aab1bd;margin-bottom:28px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}label{display:block;font-weight:750}input{width:100%;margin-top:8px;padding:14px 15px;border:1px solid #343b48;border-radius:13px;background:#0d1118;color:#fff;font:inherit}.full{grid-column:1/-1}.error{padding:14px;background:#7f1d1d;border-radius:14px;margin:18px 0}button{margin-top:24px;border:0;border-radius:999px;background:#e3062f;color:#fff;padding:15px 24px;font-weight:900;cursor:pointer;box-shadow:0 16px 36px rgba(227,6,47,.32)}small{display:block;color:#8f98a8;margin-top:8px}@media(max-width:700px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.box{padding:24px}h1{font-size:32px}}
    </style>
</head>
<body>
<div class="box">
    <h1>SGB V5 Kurulum</h1>
    <div class="muted">Localhost ve hosting için otomatik kurulum. Site yolları klasör adına göre kendiliğinden hesaplanır.</div>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post">
        <div class="grid">
            <label>Veritabanı sunucusu<input name="db_host" value="127.0.0.1" required></label>
            <label>Port<input name="db_port" value="3306" required></label>
            <label>Veritabanı adı<input name="db_name" value="sivas_genclerbirligi_v5" required></label>
            <label>Veritabanı kullanıcısı<input name="db_user" value="root" required></label>
            <label class="full">Veritabanı şifresi<input type="password" name="db_pass"><small>XAMPP varsayılanında boş bırakılır.</small></label>
            <label>Yönetici adı<input name="admin_name" value="Kulüp Yöneticisi" required></label>
            <label>Yönetici e-posta<input type="email" name="admin_email" required></label>
            <label class="full">Yönetici şifre<input type="password" name="admin_password" minlength="8" required><small>En az 8 karakter kullanın.</small></label>
        </div>
        <button>Kurulumu Tamamla</button>
    </form>
</div>
</body>
</html>
