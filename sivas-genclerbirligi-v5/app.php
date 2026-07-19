<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Istanbul');

const ROOT = __DIR__;
const UPLOAD_DIR = ROOT . '/uploads';

function cfg(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }
    $file = ROOT . '/config.php';
    if (!is_file($file)) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if (!str_contains($script, '/install/')) {
            header('Location: ' . detected_base_url() . '/install/');
            exit;
        }
        return [];
    }
    $config = require $file;
    return is_array($config) ? $config : [];
}

function detected_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $directory = rtrim(str_replace('\\', '/', dirname($script)), '/.');
    foreach (['/admin', '/install'] as $suffix) {
        if ($directory === $suffix || str_ends_with($directory, $suffix)) {
            $directory = substr($directory, 0, -strlen($suffix));
            break;
        }
    }
    return $scheme . '://' . $host . ($directory && $directory !== '/' ? $directory : '');
}

function url(string $path = ''): string
{
    return rtrim(detected_base_url(), '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = cfg();
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['db_host'], $c['db_port'], $c['db_name']);
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
}

function admin_logged(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!admin_logged()) {
        redirect('admin/');
    }
}

function fetch_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row ?: null;
}

function setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $statement = db()->prepare('SELECT value FROM settings WHERE `key` = ? LIMIT 1');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();
        return $cache[$key] = $value === false ? $default : (string) $value;
    } catch (Throwable) {
        return $default;
    }
}

function set_setting(string $key, string $value): void
{
    $statement = db()->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $statement->execute([$key, $value]);
}

function slugify(string $text): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $slug = strtolower(trim((string) preg_replace('~[^a-zA-Z0-9]+~', '-', $ascii), '-'));
    return $slug !== '' ? $slug : 'kayit-' . bin2hex(random_bytes(3));
}

function age(?string $birthDate): ?int
{
    if (!$birthDate) {
        return null;
    }
    try {
        return (new DateTime($birthDate))->diff(new DateTime('today'))->y;
    } catch (Throwable) {
        return null;
    }
}

function positions(): array
{
    return ['Kaleci', 'Stoper', 'Sağ Bek', 'Sol Bek', 'Ön Libero', 'Merkez Orta Saha', 'On Numara', 'Sağ Kanat', 'Sol Kanat', 'Forvet'];
}

function selected_positions(array|string|null $value): array
{
    if (is_array($value)) {
        return array_values(array_filter($value, 'is_string'));
    }
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', (string) $value))));
}

function unique_jersey(int $teamId, int $number, int $excludeId = 0): bool
{
    $statement = db()->prepare('SELECT COUNT(*) FROM players WHERE team_id = ? AND jersey_no = ? AND active = 1 AND id <> ?');
    $statement->execute([$teamId, $number, $excludeId]);
    return (int) $statement->fetchColumn() === 0;
}

function upload_image(string $field, string $old = '', int $maxMb = 5): string
{
    if (empty($_FILES[$field]['name'])) {
        return $old;
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Görsel yüklenemedi.');
    }
    if ((int) $_FILES[$field]['size'] > $maxMb * 1024 * 1024) {
        throw new RuntimeException("Görsel en fazla {$maxMb} MB olabilir.");
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Yalnız JPG, PNG, WEBP veya GIF görseller kabul edilir.');
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('uploads klasörü oluşturulamadı.');
    }
    $filename = bin2hex(random_bytes(14)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
        throw new RuntimeException('Görsel kaydedilemedi. uploads klasörünün yazılabilir olduğunu kontrol edin.');
    }
    return 'uploads/' . $filename;
}

function media_value(string $fileField, string $urlField, string $old = '', int $maxMb = 5): string
{
    $uploaded = upload_image($fileField, '', $maxMb);
    if ($uploaded !== '') {
        return $uploaded;
    }
    $remote = trim((string) ($_POST[$urlField] ?? ''));
    if ($remote !== '' && !filter_var($remote, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Görsel URL adresi geçerli değil.');
    }
    return $remote !== '' ? $remote : $old;
}

function media_url(?string $path): string
{
    if (!$path) {
        return '';
    }
    return preg_match('~^https?://~i', $path) ? $path : url($path);
}

function placeholder_logo(): string
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><rect width="240" height="240" rx="60" fill="#e5e7eb"/><circle cx="120" cy="120" r="72" fill="none" stroke="#9ca3af" stroke-width="12"/><path d="M120 58l30 22-11 36h-38L90 80zm-58 61l39-3 13 39-32 24-31-24zm116 0 11 36-31 24-32-24 13-39zm-96 60 32-24 32 24-12 38H94zm64 0 32-24 30 26-18 35-44 1z" fill="#9ca3af"/></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function silhouette(): string
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 680"><defs><linearGradient id="g" x2="1" y2="1"><stop stop-color="#252a33"/><stop offset="1" stop-color="#090b10"/></linearGradient></defs><circle cx="260" cy="142" r="82" fill="url(#g)"/><path d="M157 252l103-58 103 58 80 160-70 50-27 218H174l-27-218-70-50z" fill="url(#g)"/></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function social_href(array $social): ?string
{
    $value = trim((string) ($social['value'] ?? ''));
    return match ($social['type'] ?? 'link') {
        'email' => 'mailto:' . $value,
        'phone' => 'tel:' . preg_replace('~[^0-9+]~', '', $value),
        'whatsapp' => 'https://wa.me/' . preg_replace('~\D~', '', $value),
        'address' => null,
        default => filter_var($value, FILTER_VALIDATE_URL) ? $value : null,
    };
}

function audit(string $action, string $entity = '', ?int $entityId = null): void
{
    try {
        $statement = db()->prepare('INSERT INTO audit_logs(admin_id, action, entity, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$_SESSION['admin_id'] ?? null, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable) {
    }
}

function application_label(string $type): string
{
    return $type === 'academy' ? 'Altyapı Başvurusu' : 'A Takım Başvurusu';
}

function migrate(): void
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS settings (`key` varchar(120) PRIMARY KEY, value longtext NULL)",
        "CREATE TABLE IF NOT EXISTS admins (id int auto_increment primary key, name varchar(120) not null, email varchar(190) not null unique, password varchar(255) not null, created_at timestamp default current_timestamp)",
        "CREATE TABLE IF NOT EXISTS teams (id int auto_increment primary key, name varchar(120) not null, slug varchar(140) not null unique, category varchar(120), description text, logo varchar(500), cover_image varchar(500), accent varchar(20) default '#e3062f', active tinyint default 1, sort_order int default 0)",
        "CREATE TABLE IF NOT EXISTS players (id int auto_increment primary key, team_id int not null, name varchar(160) not null, slug varchar(190) not null unique, jersey_no tinyint unsigned null, positions text, birth_date date null, height smallint null, preferred_foot varchar(30), nationality varchar(100), previous_clubs text, bio text, photo varchar(500), active tinyint default 1, featured tinyint default 0, sort_order int default 0, created_at timestamp default current_timestamp, UNIQUE KEY unique_team_jersey(team_id, jersey_no), FOREIGN KEY(team_id) REFERENCES teams(id) ON DELETE CASCADE)",
        "CREATE TABLE IF NOT EXISTS events (id int auto_increment primary key, team_id int null, type varchar(40) not null, title varchar(190) not null, description text, start_at datetime not null, end_at datetime null, location varchar(190), map_url varchar(500), opponent varchar(190), home_away varchar(30), club_logo varchar(500), opponent_logo varchar(500), status varchar(40) default 'Planlandı', active tinyint default 1, created_at timestamp default current_timestamp, FOREIGN KEY(team_id) REFERENCES teams(id) ON DELETE SET NULL)",
        "CREATE TABLE IF NOT EXISTS staff (id int auto_increment primary key, name varchar(160) not null, role varchar(120) not null, team_id int null, photo varchar(500), bio text, start_date date null, end_date date null, active tinyint default 1, sort_order int default 0, FOREIGN KEY(team_id) REFERENCES teams(id) ON DELETE SET NULL)",
        "CREATE TABLE IF NOT EXISTS news (id int auto_increment primary key, title varchar(220) not null, slug varchar(240) not null unique, summary text, content longtext, image varchar(500), published_at datetime null, active tinyint default 1, featured tinyint default 0, created_at timestamp default current_timestamp)",
        "CREATE TABLE IF NOT EXISTS gallery_items (id int auto_increment primary key, title varchar(180), category varchar(100), caption text, image varchar(500) not null, active tinyint default 1, sort_order int default 0, created_at timestamp default current_timestamp)",
        "CREATE TABLE IF NOT EXISTS sponsors (id int auto_increment primary key, name varchar(160) not null, website varchar(500), placement varchar(50) default 'logo', image varchar(500) not null, width int null, height int null, starts_at date null, ends_at date null, active tinyint default 1, sort_order int default 0)",
        "CREATE TABLE IF NOT EXISTS socials (id int auto_increment primary key, label varchar(100) not null, type varchar(40) not null, value varchar(500) not null, icon varchar(80), active tinyint default 1, sort_order int default 0)",
        "CREATE TABLE IF NOT EXISTS form_fields (id int auto_increment primary key, form_type varchar(30) not null, label varchar(160) not null, name varchar(120) not null, field_type varchar(40) not null, options text, placeholder varchar(220), help_text varchar(300), required tinyint default 0, active tinyint default 1, sort_order int default 0, UNIQUE KEY unique_form_field(form_type, name))",
        "CREATE TABLE IF NOT EXISTS applications (id int auto_increment primary key, form_type varchar(30) not null, data_json longtext not null, status varchar(40) default 'Yeni', admin_note text, created_at timestamp default current_timestamp)",
        "CREATE TABLE IF NOT EXISTS standings (id int auto_increment primary key, team_name varchar(160) not null, played int default 0, won int default 0, drawn int default 0, lost int default 0, goals_for int default 0, goals_against int default 0, points int default 0, logo varchar(500), sort_order int default 0)",
        "CREATE TABLE IF NOT EXISTS audit_logs (id bigint auto_increment primary key, admin_id int null, action varchar(190) not null, entity varchar(100), entity_id int null, ip_address varchar(60), created_at timestamp default current_timestamp, FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE SET NULL)",
    ];
    foreach ($queries as $query) {
        db()->exec($query);
    }
}
