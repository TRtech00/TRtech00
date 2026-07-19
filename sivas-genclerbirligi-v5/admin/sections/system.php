<?php
$checks=[
 ['PHP sürümü >= 8.1',version_compare(PHP_VERSION,'8.1.0','>='),PHP_VERSION],
 ['PDO MySQL eklentisi',extension_loaded('pdo_mysql'),extension_loaded('pdo_mysql')?'Yüklü':'Eksik'],
 ['Fileinfo eklentisi',extension_loaded('fileinfo'),extension_loaded('fileinfo')?'Yüklü':'Eksik'],
 ['Config dosyası',is_file(ROOT.'/config.php'),ROOT.'/config.php'],
 ['Uploads klasörü',is_dir(UPLOAD_DIR),UPLOAD_DIR],
 ['Uploads yazma izni',is_dir(UPLOAD_DIR)&&is_writable(UPLOAD_DIR),is_dir(UPLOAD_DIR)&&is_writable(UPLOAD_DIR)?'Yazılabilir':'Yazma izni gerekli'],
 ['Veritabanı bağlantısı',true,cfg()['db_name']??'-'],
 ['Otomatik site adresi',true,detected_base_url()],
 ['Public CSS',is_file(ROOT.'/assets/style.css'),'assets/style.css'],
 ['Admin CSS',is_file(ROOT.'/assets/admin.css'),'assets/admin.css'],
 ['Public JavaScript',is_file(ROOT.'/assets/app.js'),'assets/app.js'],
 ['Admin JavaScript',is_file(ROOT.'/assets/admin.js'),'assets/admin.js'],
];
$tables=['settings','admins','teams','players','events','staff','news','gallery_items','sponsors','socials','form_fields','applications','standings','audit_logs'];
$tableChecks=[];foreach($tables as $table){try{db()->query("SELECT 1 FROM `{$table}` LIMIT 1");$tableChecks[]=[$table,true,'Hazır'];}catch(Throwable $e){$tableChecks[]=[$table,false,$e->getMessage()];}}
$logs=fetch_all('SELECT a.*,ad.name admin_name FROM audit_logs a LEFT JOIN admins ad ON ad.id=a.admin_id ORDER BY a.id DESC LIMIT 30');
?>
<div class="system-summary"><div><small>SİSTEM SAĞLIĞI</small><h2><?= count(array_filter($checks,fn($c)=>$c[1]))+count(array_filter($tableChecks,fn($c)=>$c[1])) ?>/<?= count($checks)+count($tableChecks) ?> kontrol başarılı</h2><p>Kurulum, dosya yolları ve veritabanı tabloları bu ekranda doğrulanır.</p></div><a href="?section=system">Kontrolleri Yenile ↻</a></div>
<div class="system-grid"><section class="panel"><div class="panel-head"><div><small>SUNUCU & DOSYALAR</small><h2>Teknik gereksinimler</h2></div></div><div class="check-list"><?php foreach($checks as [$label,$ok,$detail]):?><div><i class="<?= $ok?'ok':'fail' ?>"><?= $ok?'✓':'!' ?></i><span><b><?= e($label) ?></b><small><?= e($detail) ?></small></span></div><?php endforeach; ?></div></section><section class="panel"><div class="panel-head"><div><small>VERİTABANI</small><h2>Tablo kontrolleri</h2></div></div><div class="check-list"><?php foreach($tableChecks as [$label,$ok,$detail]):?><div><i class="<?= $ok?'ok':'fail' ?>"><?= $ok?'✓':'!' ?></i><span><b><?= e($label) ?></b><small><?= e($detail) ?></small></span></div><?php endforeach; ?></div></section></div>
<section class="panel"><div class="panel-head"><div><small>GÜVENLİK & İŞLEM KAYDI</small><h2>Son yönetici hareketleri</h2></div><span><?= count($logs) ?> kayıt</span></div><div class="audit-table"><?php foreach($logs as $log):?><div><time><?= e(date('d.m.Y H:i:s',strtotime($log['created_at']))) ?></time><b><?= e($log['action']) ?></b><span><?= e($log['entity']) ?><?= $log['entity_id']?' #'.$log['entity_id']:'' ?></span><small><?= e($log['admin_name']?:'Sistem') ?> · <?= e($log['ip_address']) ?></small></div><?php endforeach; ?><?php if(!$logs):?><div class="empty-admin">Henüz işlem kaydı oluşmadı.</div><?php endif; ?></div></section>
