<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$required = [
    'index.php','app.php','install/index.php','admin/index.php','admin/actions.php','admin/dashboard.php',
    'assets/style.css','assets/admin.css','assets/app.js','assets/admin.js',
    'admin/sections/dashboard.php','admin/sections/settings.php','admin/sections/teams.php',
    'admin/sections/players.php','admin/sections/events.php','admin/sections/staff.php',
    'admin/sections/news.php','admin/sections/gallery.php','admin/sections/sponsors.php',
    'admin/sections/socials.php','admin/sections/forms.php','admin/sections/applications.php',
    'admin/sections/standings.php','admin/sections/system.php',
];
foreach ($required as $file) {
    $path = $root . '/' . $file;
    if (!is_file($path) || filesize($path) === 0) {
        $errors[] = "Eksik veya boş dosya: {$file}";
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (str_starts_with($relative, '.git/')) continue;
    $content = file_get_contents($file->getPathname()) ?: '';
    if (str_ends_with($relative, '.php')) {
        foreach (['sivas-genclerbirligi-v3','sivas-genclerbirligi-v4','href="/admin/dashboard.php','href="admin/dashboard.php'] as $forbidden) {
            if (str_contains($content, $forbidden)) $errors[] = "Eski veya kırık yol bulundu: {$relative} -> {$forbidden}";
        }
    }
}

foreach (['assets/style.css','assets/admin.css'] as $cssFile) {
    $content = file_get_contents($root . '/' . $cssFile) ?: '';
    if (substr_count($content, '{') !== substr_count($content, '}')) {
        $errors[] = "CSS süslü parantez sayısı eşleşmiyor: {$cssFile}";
    }
}

$index = file_get_contents($root . '/index.php') ?: '';
foreach (['home','club','teams','players','events','staff','standings','news','gallery','application'] as $route) {
    if (!str_contains($index, "page === '{$route}'") && $route !== 'home') {
        $errors[] = "Public rota eksik: {$route}";
    }
}

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "V5 static checks passed\n";
