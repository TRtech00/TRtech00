<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
migrate();

$page = (string) ($_GET['page'] ?? 'home');
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'application_submit') {
    try {
        verify_csrf();
        $formType = ($_POST['form_type'] ?? '') === 'academy' ? 'academy' : 'first_team';
        $fields = fetch_all('SELECT * FROM form_fields WHERE form_type = ? AND active = 1 ORDER BY sort_order, id', [$formType]);
        $data = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $value = $field['field_type'] === 'multiselect'
                ? array_values(array_filter(array_map('trim', (array) ($_POST[$name] ?? []))))
                : trim((string) ($_POST[$name] ?? ''));
            if ((int) $field['required'] === 1 && ($value === '' || $value === [])) {
                throw new RuntimeException($field['label'] . ' alanı zorunludur.');
            }
            if ($field['field_type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('E-posta adresi geçerli değil.');
            }
            $data[$field['label']] = is_array($value) ? implode(', ', $value) : $value;
        }
        $statement = db()->prepare('INSERT INTO applications(form_type, data_json) VALUES(?, ?)');
        $statement->execute([$formType, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $success = 'Başvurunuz kulüp yönetimine ulaştı. En kısa sürede sizinle iletişime geçilecektir.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$teams = fetch_all('SELECT * FROM teams WHERE active = 1 ORDER BY sort_order, id');
$socials = fetch_all('SELECT * FROM socials WHERE active = 1 ORDER BY sort_order, id');
$siteLogo = setting('site_logo');
$primary = setting('primary_color', '#e3062f');
$dark = setting('dark_color', '#0b0e14');
$surface = setting('surface_color', '#f4f5f7');

function public_header(string $active, array $socials, string $siteLogo, string $primary, string $dark, string $surface): void
{
    $nav = [
        'home' => ['Ana Sayfa', 'index.php'],
        'club' => ['Kulübümüz', 'index.php?page=club'],
        'teams' => ['Takımlar', 'index.php?page=teams'],
        'players' => ['Kadro', 'index.php?page=players'],
        'events' => ['Etkinlikler', 'index.php?page=events'],
        'staff' => ['Yönetim', 'index.php?page=staff'],
        'standings' => ['Puan Durumu', 'index.php?page=standings'],
        'news' => ['Haberler', 'index.php?page=news'],
        'gallery' => ['Galeri', 'index.php?page=gallery'],
    ];
    ?>
    <!doctype html>
    <html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="<?= e($dark) ?>">
        <title><?= e(setting('site_name', 'Sivas Gençlerbirliği Spor')) ?></title>
        <meta name="description" content="<?= e(setting('hero_text')) ?>">
        <link rel="stylesheet" href="<?= e(url('assets/style.css?v=5.0.0')) ?>">
        <style>:root{--primary:<?= e($primary) ?>;--dark:<?= e($dark) ?>;--surface:<?= e($surface) ?>}</style>
    </head>
    <body>
    <div class="topbar">
        <span><?= e(setting('site_tagline')) ?></span>
        <div class="top-socials">
            <?php foreach (array_slice($socials, 0, 4) as $social): $href = social_href($social); ?>
                <?php if ($href): ?><a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($social['label']) ?> ↗</a><?php else: ?><span><?= e($social['value']) ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <header class="site-header" id="siteHeader">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <?php if ($siteLogo): ?><img src="<?= e(media_url($siteLogo)) ?>" alt="Kulüp logosu"><?php else: ?><b><?= e(setting('site_short_name', 'SGB')) ?></b><?php endif; ?>
            <span><strong><?= e(setting('site_name')) ?></strong><small><?= e(setting('league_name')) ?></small></span>
        </a>
        <button class="mobile-toggle" type="button" aria-label="Menüyü aç">☰</button>
        <nav class="main-nav">
            <?php foreach ($nav as $key => [$label, $path]): ?><a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </nav>
        <button class="join-open" type="button">Kulübe Katıl</button>
    </header>
    <?php
}

function public_footer(array $socials, string $siteLogo): void
{
    ?>
    <footer>
        <div class="footer-brand">
            <?php if ($siteLogo): ?><img src="<?= e(media_url($siteLogo)) ?>" alt="Kulüp logosu"><?php endif; ?>
            <h3><?= e(setting('site_name')) ?></h3>
            <p><?= e(setting('footer_text')) ?></p>
        </div>
        <div><h4>Kulüp</h4><a href="<?= e(url('index.php?page=players')) ?>">Oyuncular</a><a href="<?= e(url('index.php?page=staff')) ?>">Yönetim & Hocalar</a><a href="<?= e(url('index.php?page=events')) ?>">Etkinlikler</a><a href="<?= e(url('index.php?page=news')) ?>">Haberler</a></div>
        <div><h4>Başvurular</h4><a href="<?= e(url('index.php?page=application&type=academy')) ?>">Altyapı Başvurusu</a><a href="<?= e(url('index.php?page=application&type=first_team')) ?>">A Takım Başvurusu</a><a href="<?= e(url('index.php?page=standings')) ?>">Puan Durumu</a></div>
        <div><h4>İletişim</h4><?php foreach ($socials as $social): $href = social_href($social); ?><?php if ($href): ?><a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($social['label']) ?></a><?php else: ?><p><?= e($social['label']) ?>: <?= e($social['value']) ?></p><?php endif; ?><?php endforeach; ?></div>
        <div class="footer-bottom"><span>© <?= date('Y') ?> <?= e(setting('site_name')) ?></span><span>Amatör futbolun geleceği için.</span></div>
    </footer>
    <div class="join-modal" id="joinModal" aria-hidden="true">
        <div class="join-box">
            <button class="join-close" type="button" aria-label="Kapat">×</button>
            <small>SAHAYA İLK ADIM</small>
            <h2>Kulübe nasıl katılmak istiyorsunuz?</h2>
            <p>Size uygun başvuruyu seçin; form doğrudan kulüp yönetim paneline düşsün.</p>
            <div class="join-grid">
                <a href="<?= e(url('index.php?page=application&type=academy')) ?>"><b>Altyapı Başvurusu</b><span>Çocuklar, gençler ve yaş grupları için</span><em>Başvuruyu aç →</em></a>
                <a href="<?= e(url('index.php?page=application&type=first_team')) ?>"><b>A Takım Başvurusu</b><span>Yetişkin futbolcular ve deneme talepleri için</span><em>Başvuruyu aç →</em></a>
            </div>
        </div>
    </div>
    <script src="<?= e(url('assets/app.js?v=5.0.0')) ?>"></script>
    </body></html>
    <?php
}

function player_card(array $player): void
{
    $positions = selected_positions($player['positions']);
    $photo = $player['photo'] ? media_url($player['photo']) : silhouette();
    $watermark = $player['team_logo'] ? media_url($player['team_logo']) : '';
    ?>
    <a class="player-card reveal" href="<?= e(url('index.php?page=player&slug=' . urlencode($player['slug']))) ?>" style="--card-accent:<?= e($player['team_accent'] ?: '#e3062f') ?>;--watermark:url('<?= e($watermark) ?>')">
        <div class="player-visual"><strong class="jersey"><?= e($player['jersey_no'] ?? '-') ?></strong><img src="<?= e($photo) ?>" alt="<?= e($player['name']) ?>"><div><small><?= e(mb_strtoupper($positions[0] ?? 'FUTBOLCU')) ?></small><h3><?= e(mb_strtoupper($player['name'])) ?></h3></div></div>
        <div class="player-meta"><b>#<?= e($player['jersey_no'] ?? '-') ?> · <?= e($positions[0] ?? '-') ?></b><span><?= $player['height'] ? e($player['height']) . ' cm · ' : '' ?><?= e($player['preferred_foot'] ?: '-') ?> ayak<?= age($player['birth_date']) !== null ? ' · ' . e(age($player['birth_date'])) . ' yaş' : '' ?></span></div>
    </a>
    <?php
}

public_header($page, $socials, $siteLogo, $primary, $dark, $surface);

if ($page === 'home') {
    $nextEvent = fetch_one('SELECT e.*,t.name team_name,t.logo team_logo FROM events e LEFT JOIN teams t ON t.id=e.team_id WHERE e.active=1 AND e.start_at>=NOW() ORDER BY e.start_at LIMIT 1');
    $featuredPlayers = fetch_all('SELECT p.*,t.name team_name,t.logo team_logo,t.accent team_accent FROM players p JOIN teams t ON t.id=p.team_id WHERE p.active=1 ORDER BY p.featured DESC,p.sort_order,p.id LIMIT 12');
    $latestNews = fetch_all('SELECT * FROM news WHERE active=1 AND (published_at IS NULL OR published_at<=NOW()) ORDER BY featured DESC,published_at DESC,id DESC LIMIT 6');
    $gallery = fetch_all('SELECT * FROM gallery_items WHERE active=1 ORDER BY sort_order,id DESC LIMIT 8');
    $staff = fetch_all('SELECT * FROM staff WHERE active=1 ORDER BY sort_order,id LIMIT 6');
    $sponsors = fetch_all("SELECT * FROM sponsors WHERE active=1 AND (starts_at IS NULL OR starts_at<=CURDATE()) AND (ends_at IS NULL OR ends_at>=CURDATE()) ORDER BY sort_order,id");
    $hero = setting('hero_image');
    ?>
    <main>
        <section class="hero" style="background-image:linear-gradient(90deg,rgba(5,7,12,.96) 0%,rgba(5,7,12,.72) 52%,rgba(5,7,12,.55) 100%),url('<?= e($hero ? media_url($hero) : '') ?>')">
            <div class="hero-copy reveal"><small><?= e(setting('hero_eyebrow')) ?></small><h1><?= e(setting('hero_title')) ?></h1><p><?= e(setting('hero_text')) ?></p><div class="hero-actions"><button class="join-open btn" type="button">Kulübe Katıl</button><a class="btn ghost" href="<?= e(url('index.php?page=teams')) ?>">Takımları Gör</a></div></div>
            <?php if ($nextEvent): ?><a class="next-event reveal" href="<?= e(url('index.php?page=events')) ?>"><div><small><?= e(mb_strtoupper($nextEvent['type'])) ?></small><b><?= e(date('d.m', strtotime($nextEvent['start_at']))) ?></b></div><h3><?= e($nextEvent['title']) ?></h3><p><?= e(date('d.m.Y · H:i', strtotime($nextEvent['start_at']))) ?></p><p><?= e($nextEvent['location']) ?></p><span>Tüm etkinlikleri gör →</span></a><?php endif; ?>
            <div class="scroll-cue">AŞAĞI KAYDIR ↓</div>
        </section>

        <section class="section intro reveal"><div><small>KULÜP KÜLTÜRÜ</small><h2><?= e(setting('about_title')) ?></h2></div><p><?= e(setting('about_text')) ?></p></section>

        <section class="section dark"><div class="section-head reveal"><div><small>TAKIMLARIMIZ</small><h2>Tek arma, farklı yaş grupları</h2></div><a href="<?= e(url('index.php?page=teams')) ?>">Takımları incele →</a></div><div class="team-grid"><?php foreach ($teams as $team): ?><a class="team-card reveal" href="<?= e(url('index.php?page=players&team=' . $team['id'])) ?>" style="--accent:<?= e($team['accent']) ?>;background-image:linear-gradient(180deg,rgba(20,24,32,.45),#07090d),url('<?= e(media_url($team['cover_image'])) ?>')"><div><?php if ($team['logo']): ?><img src="<?= e(media_url($team['logo'])) ?>" alt=""><?php else: ?><img src="<?= e(placeholder_logo()) ?>" alt=""><?php endif; ?></div><div><small><?= e(mb_strtoupper($team['category'])) ?></small><h3><?= e($team['name']) ?></h3><p><?= e($team['description']) ?></p></div></a><?php endforeach; ?></div></section>

        <?php if ($featuredPlayers): ?><section class="section"><div class="section-head reveal"><div><small>KADROMUZ</small><h2>Sahadaki karakterler</h2></div><a href="<?= e(url('index.php?page=players')) ?>">Tüm futbolcular →</a></div><div class="player-slider" data-autoplay="true"><?php foreach ($featuredPlayers as $player) player_card($player); ?></div></section><?php endif; ?>

        <?php if ($latestNews): ?><section class="section news-section"><div class="section-head reveal"><div><small>HABER & DUYURULAR</small><h2>Kulüpten son gelişmeler</h2></div><a href="<?= e(url('index.php?page=news')) ?>">Tüm haberler →</a></div><div class="news-grid"><?php foreach ($latestNews as $item): ?><a class="news-card reveal" href="<?= e(url('index.php?page=news-detail&slug=' . urlencode($item['slug']))) ?>"><?php if ($item['image']): ?><img src="<?= e(media_url($item['image'])) ?>" alt=""><?php endif; ?><div><small><?= $item['published_at'] ? e(date('d.m.Y', strtotime($item['published_at']))) : 'DUYURU' ?></small><h3><?= e($item['title']) ?></h3><p><?= e($item['summary']) ?></p><span>Haberi oku →</span></div></a><?php endforeach; ?></div></section><?php endif; ?>

        <?php if ($staff): ?><section class="section dark"><div class="section-head reveal"><div><small>YÖNETİM & TEKNİK EKİP</small><h2>Kulübü sahaya hazırlayan ekip</h2></div><a href="<?= e(url('index.php?page=staff')) ?>">Tüm ekibi gör →</a></div><div class="staff-row"><?php foreach ($staff as $person): ?><article class="staff-card reveal"><?php if ($person['photo']): ?><img src="<?= e(media_url($person['photo'])) ?>" alt=""><?php else: ?><img src="<?= e(silhouette()) ?>" alt=""><?php endif; ?><small><?= e(mb_strtoupper($person['role'])) ?></small><h3><?= e($person['name']) ?></h3><p><?= e($person['bio']) ?></p></article><?php endforeach; ?></div></section><?php endif; ?>

        <?php if ($gallery): ?><section class="section"><div class="section-head reveal"><div><small>GALERİ</small><h2>Kulübün hafızası</h2></div><a href="<?= e(url('index.php?page=gallery')) ?>">Galeriyi aç →</a></div><div class="masonry-gallery"><?php foreach ($gallery as $item): ?><figure class="reveal"><img src="<?= e(media_url($item['image'])) ?>" alt="<?= e($item['title']) ?>"><figcaption><b><?= e($item['title']) ?></b><span><?= e($item['category']) ?></span></figcaption></figure><?php endforeach; ?></div></section><?php endif; ?>

        <?php if ($sponsors): ?><section class="sponsor-strip"><small>DESTEKÇİLERİMİZ</small><div><?php foreach ($sponsors as $sponsor): ?><a href="<?= e($sponsor['website'] ?: '#') ?>" <?= $sponsor['website'] ? 'target="_blank" rel="noopener"' : '' ?>><img src="<?= e(media_url($sponsor['image'])) ?>" alt="<?= e($sponsor['name']) ?>"></a><?php endforeach; ?></div></section><?php endif; ?>
    </main>
    <?php
} elseif ($page === 'club') {
    ?>
    <main><section class="page-hero"><small>KULÜBÜMÜZ</small><h1><?= e(setting('about_title')) ?></h1><p><?= e(setting('about_text')) ?></p></section><section class="section prose"><h2>Vizyonumuz</h2><p><?= e(setting('vision_text', 'Sivas futboluna eğitimli, disiplinli ve karakterli sporcular kazandırmak.')) ?></p><h2>Misyonumuz</h2><p><?= e(setting('mission_text', 'Her yaş grubuna güvenli, gelişim odaklı ve sürdürülebilir bir futbol ortamı sunmak.')) ?></p></section></main>
    <?php
} elseif ($page === 'teams') {
    ?>
    <main><section class="page-hero"><small>TAKIMLARIMIZ</small><h1>Tek arma, ortak hedef</h1><p>Takımlarımızı ve yaş gruplarını inceleyin.</p></section><section class="section dark"><div class="team-grid"><?php foreach ($teams as $team): ?><a class="team-card reveal" href="<?= e(url('index.php?page=players&team=' . $team['id'])) ?>" style="--accent:<?= e($team['accent']) ?>;background-image:linear-gradient(180deg,rgba(20,24,32,.3),#07090d),url('<?= e(media_url($team['cover_image'])) ?>')"><div><img src="<?= e($team['logo'] ? media_url($team['logo']) : placeholder_logo()) ?>" alt=""></div><div><small><?= e(mb_strtoupper($team['category'])) ?></small><h3><?= e($team['name']) ?></h3><p><?= e($team['description']) ?></p></div></a><?php endforeach; ?></div></section></main>
    <?php
} elseif ($page === 'players') {
    $teamId = (int) ($_GET['team'] ?? 0);
    $params = [];
    $where = 'p.active=1';
    if ($teamId > 0) { $where .= ' AND p.team_id=?'; $params[] = $teamId; }
    $players = fetch_all("SELECT p.*,t.name team_name,t.logo team_logo,t.accent team_accent FROM players p JOIN teams t ON t.id=p.team_id WHERE {$where} ORDER BY t.sort_order,p.sort_order,p.jersey_no", $params);
    ?>
    <main><section class="page-hero"><small>KADROMUZ</small><h1>Futbolcular</h1><div class="filter-pills"><a class="<?= $teamId === 0 ? 'active' : '' ?>" href="<?= e(url('index.php?page=players')) ?>">Tümü</a><?php foreach ($teams as $team): ?><a class="<?= $teamId === (int) $team['id'] ? 'active' : '' ?>" href="<?= e(url('index.php?page=players&team=' . $team['id'])) ?>"><?= e($team['name']) ?></a><?php endforeach; ?></div></section><section class="section"><div class="players-grid"><?php foreach ($players as $player) player_card($player); ?><?php if (!$players): ?><div class="empty-state">Bu takım için henüz oyuncu eklenmedi.</div><?php endif; ?></div></section></main>
    <?php
} elseif ($page === 'player') {
    $player = fetch_one('SELECT p.*,t.name team_name,t.logo team_logo,t.accent team_accent FROM players p JOIN teams t ON t.id=p.team_id WHERE p.slug=? AND p.active=1', [(string) ($_GET['slug'] ?? '')]);
    if (!$player) { http_response_code(404); echo '<main class="page-hero"><h1>Oyuncu bulunamadı</h1></main>'; } else {
        $positions = selected_positions($player['positions']);
        $coordinates = ['Kaleci'=>[50,91],'Stoper'=>[50,73],'Sağ Bek'=>[82,72],'Sol Bek'=>[18,72],'Ön Libero'=>[50,58],'Merkez Orta Saha'=>[50,48],'On Numara'=>[50,35],'Sağ Kanat'=>[82,31],'Sol Kanat'=>[18,31],'Forvet'=>[50,15]];
        ?>
        <main><section class="player-detail" style="--accent:<?= e($player['team_accent']) ?>;--watermark:url('<?= e(media_url($player['team_logo'])) ?>')"><div class="detail-photo reveal"><strong><?= e($player['jersey_no']) ?></strong><img src="<?= e($player['photo'] ? media_url($player['photo']) : silhouette()) ?>" alt="<?= e($player['name']) ?>"></div><div class="detail-info reveal"><small><?= e(mb_strtoupper(implode(' · ', $positions))) ?></small><h1><?= e(mb_strtoupper($player['name'])) ?></h1><p class="team-name"><?= e($player['team_name']) ?></p><div class="info-grid"><div><small>TAKIM</small><b><?= e($player['team_name']) ?></b></div><div><small>FORMA NUMARASI</small><b>#<?= e($player['jersey_no']) ?></b></div><div><small>BOY</small><b><?= e($player['height'] ?: '-') ?><?= $player['height'] ? ' cm' : '' ?></b></div><div><small>YAŞ</small><b><?= age($player['birth_date']) !== null ? e(age($player['birth_date'])) . ' yaş' : '-' ?></b></div><div><small>TERCİH ETTİĞİ AYAK</small><b><?= e($player['preferred_foot'] ?: '-') ?></b></div><div><small>ÖNCEKİ KULÜPLER</small><b><?= e($player['previous_clubs'] ?: '-') ?></b></div></div><p class="bio"><?= nl2br(e($player['bio'])) ?></p><div class="pitch"><div class="pitch-lines"></div><?php foreach ($coordinates as $position => [$x,$y]): ?><span class="pitch-pos <?= in_array($position, $positions, true) ? 'active' : '' ?>" style="left:<?= $x ?>%;top:<?= $y ?>%"><?= e($position) ?></span><?php endforeach; ?></div></div></section></main>
        <?php
    }
} elseif ($page === 'events') {
    $events = fetch_all('SELECT e.*,t.name team_name,t.logo team_logo FROM events e LEFT JOIN teams t ON t.id=e.team_id WHERE e.active=1 ORDER BY e.start_at DESC');
    ?>
    <main><section class="page-hero"><small>FİKSTÜR & TAKVİM</small><h1>Etkinlikler</h1><p>Maç, antrenman, toplantı, seçme ve kulüp programları.</p></section><section class="section"><div class="events-grid"><?php foreach ($events as $event): $clubLogo = $event['club_logo'] ?: $event['team_logo']; ?><article class="event-card reveal"><div class="event-top"><img src="<?= e($clubLogo ? media_url($clubLogo) : placeholder_logo()) ?>" alt=""><div><small><?= e(mb_strtoupper($event['type'])) ?></small><strong><?= e(date('d.m', strtotime($event['start_at']))) ?></strong></div><?php if ($event['type'] === 'Maç'): ?><img src="<?= e($event['opponent_logo'] ? media_url($event['opponent_logo']) : placeholder_logo()) ?>" alt=""><?php endif; ?></div><div class="event-body"><span class="status"><?= e($event['status']) ?></span><h3><?= e($event['title']) ?></h3><p>◷ <?= e(date('d.m.Y · H:i', strtotime($event['start_at']))) ?></p><p>⌖ <?= e($event['location']) ?></p><?php if ($event['opponent']): ?><p><b>Rakip:</b> <?= e($event['opponent']) ?></p><?php endif; ?><p><?= e($event['description']) ?></p><?php if ($event['map_url']): ?><a href="<?= e($event['map_url']) ?>" target="_blank" rel="noopener">Haritada aç →</a><?php endif; ?></div></article><?php endforeach; ?><?php if (!$events): ?><div class="empty-state">Henüz etkinlik eklenmedi.</div><?php endif; ?></div></section></main>
    <?php
} elseif ($page === 'staff') {
    $staff = fetch_all('SELECT s.*,t.name team_name FROM staff s LEFT JOIN teams t ON t.id=s.team_id WHERE s.active=1 ORDER BY s.sort_order,s.id');
    ?>
    <main><section class="page-hero"><small>YÖNETİM & TEKNİK EKİP</small><h1>Kulübü yöneten ekip</h1></section><section class="section dark"><div class="staff-grid"><?php foreach ($staff as $person): ?><article class="staff-card reveal"><img src="<?= e($person['photo'] ? media_url($person['photo']) : silhouette()) ?>" alt=""><small><?= e(mb_strtoupper($person['role'])) ?></small><h3><?= e($person['name']) ?></h3><p><?= e($person['team_name'] ?: 'Kulüp Geneli') ?></p><p><?= e($person['bio']) ?></p></article><?php endforeach; ?><?php if (!$staff): ?><div class="empty-state dark-empty">Henüz yönetim veya teknik ekip eklenmedi.</div><?php endif; ?></div></section></main>
    <?php
} elseif ($page === 'news') {
    $news = fetch_all('SELECT * FROM news WHERE active=1 AND (published_at IS NULL OR published_at<=NOW()) ORDER BY featured DESC,published_at DESC,id DESC');
    ?>
    <main><section class="page-hero"><small>HABER & DUYURULAR</small><h1>Kulüpten son gelişmeler</h1></section><section class="section"><div class="news-grid"><?php foreach ($news as $item): ?><a class="news-card reveal" href="<?= e(url('index.php?page=news-detail&slug=' . urlencode($item['slug']))) ?>"><?php if ($item['image']): ?><img src="<?= e(media_url($item['image'])) ?>" alt=""><?php endif; ?><div><small><?= $item['published_at'] ? e(date('d.m.Y', strtotime($item['published_at']))) : 'DUYURU' ?></small><h3><?= e($item['title']) ?></h3><p><?= e($item['summary']) ?></p><span>Haberi oku →</span></div></a><?php endforeach; ?><?php if (!$news): ?><div class="empty-state">Henüz haber yayımlanmadı.</div><?php endif; ?></div></section></main>
    <?php
} elseif ($page === 'news-detail') {
    $item = fetch_one('SELECT * FROM news WHERE slug=? AND active=1', [(string) ($_GET['slug'] ?? '')]);
    if (!$item) { http_response_code(404); echo '<main class="page-hero"><h1>Haber bulunamadı</h1></main>'; } else { ?>
        <main><article class="article-page"><small><?= $item['published_at'] ? e(date('d.m.Y · H:i', strtotime($item['published_at']))) : 'DUYURU' ?></small><h1><?= e($item['title']) ?></h1><p class="lead"><?= e($item['summary']) ?></p><?php if ($item['image']): ?><img class="article-cover" src="<?= e(media_url($item['image'])) ?>" alt=""><?php endif; ?><div class="article-content"><?= nl2br(e($item['content'])) ?></div></article></main>
    <?php }
} elseif ($page === 'gallery') {
    $gallery = fetch_all('SELECT * FROM gallery_items WHERE active=1 ORDER BY sort_order,id DESC');
    ?>
    <main><section class="page-hero"><small>GALERİ</small><h1>Kulübün hafızası</h1></section><section class="section"><div class="masonry-gallery full"><?php foreach ($gallery as $item): ?><figure class="reveal"><img src="<?= e(media_url($item['image'])) ?>" alt="<?= e($item['title']) ?>"><figcaption><b><?= e($item['title']) ?></b><span><?= e($item['category']) ?></span><p><?= e($item['caption']) ?></p></figcaption></figure><?php endforeach; ?><?php if (!$gallery): ?><div class="empty-state">Henüz galeri görseli eklenmedi.</div><?php endif; ?></div></section></main>
    <?php
} elseif ($page === 'standings') {
    $rows = fetch_all('SELECT * FROM standings ORDER BY sort_order,points DESC,goals_for-goals_against DESC,id');
    ?>
    <main><section class="page-hero"><small>LİG TABLOSU</small><h1>Puan Durumu</h1></section><section class="section"><div class="table-wrap"><table class="standings"><thead><tr><th>#</th><th>Takım</th><th>O</th><th>G</th><th>B</th><th>M</th><th>AV</th><th>P</th></tr></thead><tbody><?php foreach ($rows as $i=>$row): ?><tr><td><?= $i+1 ?></td><td><span class="standing-team"><img src="<?= e($row['logo'] ? media_url($row['logo']) : placeholder_logo()) ?>" alt=""><?= e($row['team_name']) ?></span></td><td><?= e($row['played']) ?></td><td><?= e($row['won']) ?></td><td><?= e($row['drawn']) ?></td><td><?= e($row['lost']) ?></td><td><?= e($row['goals_for']-$row['goals_against']) ?></td><td><b><?= e($row['points']) ?></b></td></tr><?php endforeach; ?></tbody></table><?php if (!$rows): ?><div class="empty-state">Puan durumu henüz girilmedi.</div><?php endif; ?></div></section></main>
    <?php
} elseif ($page === 'application') {
    $type = ($_GET['type'] ?? '') === 'academy' ? 'academy' : 'first_team';
    $fields = fetch_all('SELECT * FROM form_fields WHERE form_type=? AND active=1 ORDER BY sort_order,id', [$type]);
    ?>
    <main><section class="page-hero"><small>BAŞVURU</small><h1><?= e(application_label($type)) ?></h1><p><?= $type === 'academy' ? 'Çocuğunuzun veya genç sporcunun bilgilerini kulüp yönetimiyle paylaşın.' : 'Futbol geçmişinizi ve oynadığınız mevkileri kulüp yönetimine iletin.' ?></p></section><section class="section"><form class="application-form reveal" method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="application_submit"><input type="hidden" name="form_type" value="<?= e($type) ?>"><?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?><?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?><?php foreach ($fields as $field): $options = array_values(array_filter(array_map('trim', preg_split('~\r?\n~', (string) $field['options'])))); ?><label><span><?= e($field['label']) ?><?= $field['required'] ? ' *' : '' ?></span><?php if ($field['field_type'] === 'textarea'): ?><textarea name="<?= e($field['name']) ?>" placeholder="<?= e($field['placeholder']) ?>" <?= $field['required'] ? 'required' : '' ?>></textarea><?php elseif ($field['field_type'] === 'select'): ?><select name="<?= e($field['name']) ?>" <?= $field['required'] ? 'required' : '' ?>><option value="">Seçiniz</option><?php foreach ($options as $option): ?><option><?= e($option) ?></option><?php endforeach; ?></select><?php elseif ($field['field_type'] === 'multiselect'): ?><div class="choice-grid"><?php foreach ($options as $option): ?><label class="choice"><input type="checkbox" name="<?= e($field['name']) ?>[]" value="<?= e($option) ?>"><span><?= e($option) ?></span></label><?php endforeach; ?></div><?php else: ?><input type="<?= e($field['field_type']) ?>" name="<?= e($field['name']) ?>" placeholder="<?= e($field['placeholder']) ?>" <?= $field['required'] ? 'required' : '' ?>><?php endif; ?><?php if ($field['help_text']): ?><small><?= e($field['help_text']) ?></small><?php endif; ?></label><?php endforeach; ?><button class="btn" type="submit">Başvuruyu Gönder</button></form></section></main>
    <?php
} else {
    http_response_code(404);
    ?><main><section class="page-hero"><small>404</small><h1>Sayfa bulunamadı</h1><a class="btn" href="<?= e(url('index.php')) ?>">Ana sayfaya dön</a></section></main><?php
}

public_footer($socials, $siteLogo);
