<?php
declare(strict_types=1);

session_start();
const ROOT = __DIR__;
const UPLOAD_DIR = ROOT . '/uploads';

function cfg(): array {
    static $cfg;
    if ($cfg) return $cfg;
    $file = ROOT . '/config.php';
    if (!is_file($file)) {
        header('Location: install/');
        exit;
    }
    $cfg = require $file;
    return $cfg;
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $c = cfg();
    $dsn = "mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $path=''): string { return rtrim(cfg()['base_url'], '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) throw new RuntimeException('Güvenlik doğrulaması başarısız.'); }
function admin_logged(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void { if (!admin_logged()) redirect('admin/?login=1'); }

function setting(string $key, string $default=''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $st = db()->prepare('SELECT value FROM settings WHERE `key`=? LIMIT 1');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $cache[$key] = ($v === false ? $default : (string)$v);
    } catch (Throwable) { return $default; }
}
function set_setting(string $key, string $value): void {
    $st = db()->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
    $st->execute([$key,$value]);
}

function upload_image(string $field, string $old=''): string {
    if (empty($_FILES[$field]['name'])) return $old;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Dosya yüklenemedi.');
    if ($_FILES[$field]['size'] > 5*1024*1024) throw new RuntimeException('Görsel en fazla 5 MB olabilir.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if (!isset($map[$mime])) throw new RuntimeException('Yalnız JPG, PNG, WEBP veya GIF kabul edilir.');
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = bin2hex(random_bytes(12)).'.'.$map[$mime];
    move_uploaded_file($_FILES[$field]['tmp_name'], UPLOAD_DIR.'/'.$name);
    return 'uploads/'.$name;
}
function media_value(string $uploadField, string $urlField, string $old=''): string {
    $uploaded = upload_image($uploadField, '');
    if ($uploaded !== '') return $uploaded;
    $remote = trim($_POST[$urlField] ?? '');
    if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_URL)) return $remote;
    return $old;
}
function media_url(?string $path): string {
    if (!$path) return '';
    return preg_match('~^https?://~i',$path) ? $path : url($path);
}
function age(?string $birth): ?int {
    if (!$birth) return null;
    try { return (new DateTime($birth))->diff(new DateTime('today'))->y; } catch(Throwable) { return null; }
}
function positions(): array { return ['Kaleci','Stoper','Sağ Bek','Sol Bek','Ön Libero','Orta Saha','On Numara','Sağ Kanat','Sol Kanat','Forvet']; }
function selected_positions(array|string|null $p): array {
    if (is_array($p)) return $p;
    $d = json_decode((string)$p,true);
    return is_array($d) ? $d : array_filter(array_map('trim',explode(',',(string)$p)));
}
function placeholder_logo(): string {
    return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><circle cx="100" cy="100" r="92" fill="#e5e7eb"/><path d="M100 35l25 18-9 30H84l-9-30zM53 82l31 1 10 30-25 18-25-18zm94 0 9 31-25 18-25-18 10-30zm-78 49 25-18 25 18-9 30H78zm62 0 25-18 20 24-17 28-29-4z" fill="#9ca3af"/></svg>');
}
function silhouette(): string {
    return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 650"><defs><linearGradient id="g" x2="1" y2="1"><stop stop-color="#20242c"/><stop offset="1" stop-color="#0b0d12"/></linearGradient></defs><rect width="500" height="650" fill="transparent"/><circle cx="250" cy="145" r="72" fill="url(#g)"/><path d="M150 245l100-55 100 55 72 150-65 45-25 210H168l-25-210-65-45z" fill="url(#g)"/></svg>');
}
function fetch_all(string $sql,array $params=[]): array { $s=db()->prepare($sql);$s->execute($params);return $s->fetchAll(); }
function fetch_one(string $sql,array $params=[]): ?array { $s=db()->prepare($sql);$s->execute($params);$r=$s->fetch();return $r?:null; }

function unique_jersey(int $teamId, int $number, int $exclude=0): bool {
    $s=db()->prepare('SELECT COUNT(*) FROM players WHERE team_id=? AND jersey_no=? AND active=1 AND id<>?');
    $s->execute([$teamId,$number,$exclude]); return (int)$s->fetchColumn()===0;
}

function migrate(): void {
    $sql = [
      "CREATE TABLE IF NOT EXISTS settings (`key` varchar(100) PRIMARY KEY, `value` longtext NULL)",
      "CREATE TABLE IF NOT EXISTS admins (id int auto_increment primary key,name varchar(100),email varchar(190) unique,password varchar(255),created_at timestamp default current_timestamp)",
      "CREATE TABLE IF NOT EXISTS teams (id int auto_increment primary key,name varchar(100),slug varchar(100) unique,category varchar(100),description text,logo varchar(500),accent varchar(20) default '#d90429',active tinyint default 1,sort_order int default 0)",
      "CREATE TABLE IF NOT EXISTS players (id int auto_increment primary key,team_id int not null,name varchar(150),slug varchar(180) unique,jersey_no tinyint unsigned,positions text,birth_date date null,height smallint null,preferred_foot varchar(20),nationality varchar(100),previous_club varchar(150),bio text,photo varchar(500),active tinyint default 1,featured tinyint default 0,sort_order int default 0,created_at timestamp default current_timestamp, UNIQUE KEY unique_team_jersey(team_id,jersey_no), FOREIGN KEY(team_id) REFERENCES teams(id) ON DELETE CASCADE)",
      "CREATE TABLE IF NOT EXISTS events (id int auto_increment primary key,team_id int null,type varchar(30),title varchar(180),description text,start_at datetime,end_at datetime null,location varchar(180),map_url varchar(500),opponent varchar(180),home_away varchar(20),club_logo varchar(500),opponent_logo varchar(500),status varchar(30) default 'Planlandı',active tinyint default 1,created_at timestamp default current_timestamp, FOREIGN KEY(team_id) REFERENCES teams(id) ON DELETE SET NULL)",
      "CREATE TABLE IF NOT EXISTS staff (id int auto_increment primary key,name varchar(150),role varchar(100),team_id int null,photo varchar(500),bio text,start_date date null,end_date date null,active tinyint default 1,sort_order int default 0, FOREIGN KEY(team_id) REFERENCES teams(id) ON DELETE SET NULL)",
      "CREATE TABLE IF NOT EXISTS news (id int auto_increment primary key,title varchar(200),slug varchar(220) unique,summary text,content longtext,image varchar(500),published_at datetime,active tinyint default 1)",
      "CREATE TABLE IF NOT EXISTS form_fields (id int auto_increment primary key,form_type varchar(30),label varchar(150),name varchar(100),field_type varchar(30),options text,required tinyint default 0,active tinyint default 1,sort_order int default 0)",
      "CREATE TABLE IF NOT EXISTS applications (id int auto_increment primary key,form_type varchar(30),data_json longtext,status varchar(30) default 'Yeni',admin_note text,created_at timestamp default current_timestamp)",
      "CREATE TABLE IF NOT EXISTS socials (id int auto_increment primary key,label varchar(80),type varchar(40),value varchar(500),active tinyint default 1,sort_order int default 0)",
    ];
    foreach($sql as $q) db()->exec($q);
}
