<?php
declare(strict_types=1);

function post_value(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

try {
    verify_csrf();
    $action = post_value('action');

    if ($action === 'settings_save') {
        $keys = ['site_name','site_short_name','site_tagline','league_name','hero_eyebrow','hero_title','hero_text','about_title','about_text','vision_text','mission_text','contact_city','contact_email','contact_phone','footer_text','primary_color','dark_color','surface_color'];
        foreach ($keys as $key) {
            set_setting($key, post_value($key));
        }
        set_setting('site_logo', media_value('site_logo_file', 'site_logo_url', setting('site_logo')));
        set_setting('hero_image', media_value('hero_image_file', 'hero_image_url', setting('hero_image')));
        audit('Site ayarları güncellendi', 'settings');
        $notice = 'Site kimliği, renkler ve kurumsal metinler güncellendi.';
    }

    if ($action === 'team_save') {
        $id = (int) post_value('id', '0');
        $logo = media_value('logo_file', 'logo_url', post_value('old_logo'));
        $cover = media_value('cover_file', 'cover_url', post_value('old_cover'));
        $data = [post_value('name'), post_value('slug') ?: slugify(post_value('name')), post_value('category'), post_value('description'), $logo, $cover, post_value('accent', '#e3062f'), isset($_POST['active']) ? 1 : 0, (int) post_value('sort_order', '0')];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE teams SET name=?,slug=?,category=?,description=?,logo=?,cover_image=?,accent=?,active=?,sort_order=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO teams(name,slug,category,description,logo,cover_image,accent,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('Takım kaydedildi', 'teams', $id);
        $notice = 'Takım bilgileri ve görsel kimliği kaydedildi.';
    }

    if ($action === 'player_save') {
        $id = (int) post_value('id', '0');
        $teamId = (int) post_value('team_id');
        $active = isset($_POST['active']) ? 1 : 0;
        $jersey = (int) post_value('jersey_no', '0');
        if ($active) {
            if ($jersey < 1 || $jersey > 99) {
                throw new RuntimeException('Aktif oyuncunun forma numarası 1 ile 99 arasında olmalıdır.');
            }
            if (!unique_jersey($teamId, $jersey, $id)) {
                throw new RuntimeException('Bu forma numarası seçilen takımda başka bir aktif oyuncuya verilmiş.');
            }
        }
        $photo = media_value('photo_file', 'photo_url', post_value('old_photo'));
        $positions = array_values(array_intersect(positions(), (array) ($_POST['positions'] ?? [])));
        if (!$positions) {
            throw new RuntimeException('Oyuncu için en az bir mevki seçin.');
        }
        $data = [$teamId, post_value('name'), post_value('slug') ?: slugify(post_value('name')), $active ? $jersey : null, json_encode($positions, JSON_UNESCAPED_UNICODE), post_value('birth_date') ?: null, (int) post_value('height', '0') ?: null, post_value('preferred_foot'), post_value('nationality'), post_value('previous_clubs'), post_value('bio'), $photo, $active, isset($_POST['featured']) ? 1 : 0, (int) post_value('sort_order', '0')];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE players SET team_id=?,name=?,slug=?,jersey_no=?,positions=?,birth_date=?,height=?,preferred_foot=?,nationality=?,previous_clubs=?,bio=?,photo=?,active=?,featured=?,sort_order=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO players(team_id,name,slug,jersey_no,positions,birth_date,height,preferred_foot,nationality,previous_clubs,bio,photo,active,featured,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('Oyuncu kaydedildi', 'players', $id);
        $notice = 'Oyuncu profili, mevkiler ve forma numarası kaydedildi.';
    }

    if ($action === 'player_archive') {
        $id = (int) post_value('id');
        db()->prepare('UPDATE players SET active=0, jersey_no=NULL WHERE id=?')->execute([$id]);
        audit('Oyuncu arşivlendi', 'players', $id);
        $notice = 'Oyuncu arşivlendi; forma numarası yeniden kullanılabilir.';
    }

    if ($action === 'event_save') {
        $id = (int) post_value('id', '0');
        $teamId = (int) post_value('team_id', '0') ?: null;
        $type = post_value('type');
        $clubLogo = media_value('club_logo_file', 'club_logo_url', post_value('old_club_logo'));
        if (!$clubLogo && $teamId) {
            $team = fetch_one('SELECT logo FROM teams WHERE id=?', [$teamId]);
            $clubLogo = $team['logo'] ?? '';
        }
        $opponentLogo = media_value('opponent_logo_file', 'opponent_logo_url', post_value('old_opponent_logo'));
        if ($type === 'Maç' && post_value('opponent') === '') {
            throw new RuntimeException('Maç etkinliğinde rakip takım adı zorunludur.');
        }
        $data = [$teamId, $type, post_value('title'), post_value('description'), post_value('start_at'), post_value('end_at') ?: null, post_value('location'), post_value('map_url'), post_value('opponent'), post_value('home_away'), $clubLogo, $opponentLogo, post_value('status', 'Planlandı'), isset($_POST['active']) ? 1 : 0];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE events SET team_id=?,type=?,title=?,description=?,start_at=?,end_at=?,location=?,map_url=?,opponent=?,home_away=?,club_logo=?,opponent_logo=?,status=?,active=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO events(team_id,type,title,description,start_at,end_at,location,map_url,opponent,home_away,club_logo,opponent_logo,status,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('Etkinlik kaydedildi', 'events', $id);
        $notice = 'Etkinlik ve fikstür bilgileri kaydedildi.';
    }

    if ($action === 'staff_save') {
        $id = (int) post_value('id', '0');
        $photo = media_value('photo_file', 'photo_url', post_value('old_photo'));
        $data = [post_value('name'), post_value('role'), (int) post_value('team_id', '0') ?: null, $photo, post_value('bio'), post_value('start_date') ?: null, post_value('end_date') ?: null, isset($_POST['active']) ? 1 : 0, (int) post_value('sort_order', '0')];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE staff SET name=?,role=?,team_id=?,photo=?,bio=?,start_date=?,end_date=?,active=?,sort_order=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO staff(name,role,team_id,photo,bio,start_date,end_date,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('Görevli kaydedildi', 'staff', $id);
        $notice = 'Yönetim veya teknik ekip üyesi kaydedildi.';
    }

    if ($action === 'news_save') {
        $id = (int) post_value('id', '0');
        $image = media_value('image_file', 'image_url', post_value('old_image'));
        $data = [post_value('title'), post_value('slug') ?: slugify(post_value('title')), post_value('summary'), post_value('content'), $image, post_value('published_at') ?: null, isset($_POST['active']) ? 1 : 0, isset($_POST['featured']) ? 1 : 0];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE news SET title=?,slug=?,summary=?,content=?,image=?,published_at=?,active=?,featured=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO news(title,slug,summary,content,image,published_at,active,featured) VALUES(?,?,?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('Haber kaydedildi', 'news', $id);
        $notice = 'Haber veya duyuru kaydedildi.';
    }

    if ($action === 'gallery_save') {
        $id = (int) post_value('id', '0');
        $image = media_value('image_file', 'image_url', post_value('old_image'));
        if (!$image) {
            throw new RuntimeException('Galeri görseli zorunludur.');
        }
        $data = [post_value('title'), post_value('category'), post_value('caption'), $image, isset($_POST['active']) ? 1 : 0, (int) post_value('sort_order', '0')];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE gallery_items SET title=?,category=?,caption=?,image=?,active=?,sort_order=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO gallery_items(title,category,caption,image,active,sort_order) VALUES(?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('Galeri görseli kaydedildi', 'gallery_items', $id);
        $notice = 'Galeri görseli kaydedildi.';
    }

    if ($action === 'sponsor_save') {
        $id = (int) post_value('id', '0');
        $image = media_value('image_file', 'image_url', post_value('old_image'), 5);
        if (!$image) {
            throw new RuntimeException('Sponsor veya reklam görseli zorunludur.');
        }
        $data = [post_value('name'), post_value('website'), post_value('placement', 'logo'), $image, (int) post_value('width', '0') ?: null, (int) post_value('height', '0') ?: null, post_value('starts_at') ?: null, post_value('ends_at') ?: null, isset($_POST['active']) ? 1 : 0, (int) post_value('sort_order', '0')];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE sponsors SET name=?,website=?,placement=?,image=?,width=?,height=?,starts_at=?,ends_at=?,active=?,sort_order=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO sponsors(name,website,placement,image,width,height,starts_at,ends_at,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('Sponsor kaydedildi', 'sponsors', $id);
        $notice = 'Sponsor veya reklam alanı kaydedildi.';
    }

    if ($action === 'social_save') {
        $id = (int) post_value('id', '0');
        $data = [post_value('label'), post_value('type'), post_value('value'), post_value('icon'), isset($_POST['active']) ? 1 : 0, (int) post_value('sort_order', '0')];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE socials SET label=?,type=?,value=?,icon=?,active=?,sort_order=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO socials(label,type,value,icon,active,sort_order) VALUES(?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('İletişim bağlantısı kaydedildi', 'socials', $id);
        $notice = 'İletişim veya sosyal medya kaydı kaydedildi.';
    }

    if ($action === 'form_field_save') {
        $id = (int) post_value('id', '0');
        $data = [post_value('form_type'), post_value('label'), post_value('name') ?: slugify(post_value('label')), post_value('field_type'), post_value('options'), post_value('placeholder'), post_value('help_text'), isset($_POST['required']) ? 1 : 0, isset($_POST['active']) ? 1 : 0, (int) post_value('sort_order', '0')];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE form_fields SET form_type=?,label=?,name=?,field_type=?,options=?,placeholder=?,help_text=?,required=?,active=?,sort_order=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO form_fields(form_type,label,name,field_type,options,placeholder,help_text,required,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('Başvuru alanı kaydedildi', 'form_fields', $id);
        $notice = 'Başvuru formu alanı kaydedildi.';
    }

    if ($action === 'application_update') {
        $id = (int) post_value('id');
        db()->prepare('UPDATE applications SET status=?, admin_note=? WHERE id=?')->execute([post_value('status'), post_value('admin_note'), $id]);
        audit('Başvuru güncellendi', 'applications', $id);
        $notice = 'Başvuru durumu ve yönetici notu güncellendi.';
    }

    if ($action === 'standing_save') {
        $id = (int) post_value('id', '0');
        $logo = media_value('logo_file', 'logo_url', post_value('old_logo'));
        $played = (int) post_value('played', '0');
        $won = (int) post_value('won', '0');
        $drawn = (int) post_value('drawn', '0');
        $lost = (int) post_value('lost', '0');
        $points = post_value('points') !== '' ? (int) post_value('points') : $won * 3 + $drawn;
        $data = [post_value('team_name'), $played, $won, $drawn, $lost, (int) post_value('goals_for', '0'), (int) post_value('goals_against', '0'), $points, $logo, (int) post_value('sort_order', '0')];
        if ($id > 0) {
            $data[] = $id;
            db()->prepare('UPDATE standings SET team_name=?,played=?,won=?,drawn=?,lost=?,goals_for=?,goals_against=?,points=?,logo=?,sort_order=? WHERE id=?')->execute($data);
        } else {
            db()->prepare('INSERT INTO standings(team_name,played,won,drawn,lost,goals_for,goals_against,points,logo,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($data);
            $id = (int) db()->lastInsertId();
        }
        audit('Puan durumu satırı kaydedildi', 'standings', $id);
        $notice = 'Puan durumu satırı kaydedildi.';
    }

    if ($action === 'delete_generic') {
        $table = post_value('table');
        $allowed = ['teams','events','staff','news','gallery_items','sponsors','socials','form_fields','applications','standings'];
        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException('Geçersiz silme işlemi.');
        }
        $id = (int) post_value('id');
        db()->prepare("DELETE FROM `{$table}` WHERE id=?")->execute([$id]);
        audit('Kayıt silindi', $table, $id);
        $notice = 'Kayıt silindi.';
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
