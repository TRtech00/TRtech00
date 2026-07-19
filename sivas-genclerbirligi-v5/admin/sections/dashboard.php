<?php
$stats = [
    ['Aktif Oyuncu', (int) (fetch_one('SELECT COUNT(*) total FROM players WHERE active=1')['total'] ?? 0), 'players', '●'],
    ['Yaklaşan Etkinlik', (int) (fetch_one('SELECT COUNT(*) total FROM events WHERE active=1 AND start_at>=NOW()')['total'] ?? 0), 'events', '◷'],
    ['Yeni Başvuru', (int) (fetch_one("SELECT COUNT(*) total FROM applications WHERE status='Yeni'")['total'] ?? 0), 'applications', '✉'],
    ['Yayındaki Haber', (int) (fetch_one('SELECT COUNT(*) total FROM news WHERE active=1')['total'] ?? 0), 'news', '▤'],
];
$recentApplications = fetch_all('SELECT * FROM applications ORDER BY created_at DESC LIMIT 6');
$nextEvents = fetch_all('SELECT e.*,t.name team_name FROM events e LEFT JOIN teams t ON t.id=e.team_id WHERE e.active=1 AND e.start_at>=NOW() ORDER BY e.start_at LIMIT 5');
$logs = fetch_all('SELECT a.*,ad.name admin_name FROM audit_logs a LEFT JOIN admins ad ON ad.id=a.admin_id ORDER BY a.id DESC LIMIT 8');
?>
<div class="dashboard-hero"><div><small>KULÜP OPERASYON MERKEZİ</small><h2>Bugün neyi yönetiyoruz?</h2><p>Takım, içerik, başvuru ve fikstür akışını tek ekrandan izleyin.</p></div><div class="quick-actions"><a href="?section=players">+ Oyuncu Ekle</a><a href="?section=events">+ Etkinlik Ekle</a><a href="?section=news">+ Haber Ekle</a></div></div>
<div class="stat-grid"><?php foreach($stats as [$label,$value,$target,$icon]): ?><a class="stat-card" href="?section=<?= e($target) ?>"><i><?= e($icon) ?></i><b><?= $value ?></b><span><?= e($label) ?></span><em>Yönet →</em></a><?php endforeach; ?></div>
<div class="dashboard-grid">
<section class="panel"><div class="panel-head"><div><small>SON BAŞVURULAR</small><h2>Yeni oyuncu adayları</h2></div><a href="?section=applications">Tümünü gör →</a></div><?php if($recentApplications): ?><div class="compact-list"><?php foreach($recentApplications as $app): $data=json_decode($app['data_json'],true)?:[]; ?><a href="?section=applications&view=<?= $app['id'] ?>"><span class="status-dot <?= e(strtolower(str_replace(' ','-',$app['status']))) ?>"></span><div><b><?= e($data['Ad Soyad'] ?? $data['Ad Soyad *'] ?? 'Başvuru') ?></b><small><?= e(application_label($app['form_type'])) ?> · <?= e(date('d.m.Y H:i',strtotime($app['created_at']))) ?></small></div><em><?= e($app['status']) ?></em></a><?php endforeach; ?></div><?php else: ?><div class="empty-admin">Henüz başvuru yok.</div><?php endif; ?></section>
<section class="panel"><div class="panel-head"><div><small>YAKLAŞAN TAKVİM</small><h2>Sıradaki programlar</h2></div><a href="?section=events">Takvimi yönet →</a></div><?php if($nextEvents): ?><div class="timeline"><?php foreach($nextEvents as $event): ?><div><time><?= e(date('d.m',strtotime($event['start_at']))) ?><small><?= e(date('H:i',strtotime($event['start_at']))) ?></small></time><span></span><p><b><?= e($event['title']) ?></b><small><?= e($event['team_name'] ?: 'Kulüp Geneli') ?> · <?= e($event['location']) ?></small></p></div><?php endforeach; ?></div><?php else: ?><div class="empty-admin">Yaklaşan etkinlik yok.</div><?php endif; ?></section>
</div>
<section class="panel"><div class="panel-head"><div><small>İŞLEM GEÇMİŞİ</small><h2>Son yönetici hareketleri</h2></div><a href="?section=system">Sistem kontrolü →</a></div><div class="audit-list"><?php foreach($logs as $log): ?><div><span><?= e($log['action']) ?></span><small><?= e($log['admin_name'] ?: 'Sistem') ?> · <?= e(date('d.m.Y H:i',strtotime($log['created_at']))) ?></small></div><?php endforeach; ?><?php if(!$logs): ?><div class="empty-admin">Henüz işlem kaydı yok.</div><?php endif; ?></div></section>
