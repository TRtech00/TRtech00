# Sivas Gençlerbirliği Spor Kulüp Sitesi V5

PHP 8.1+ ve MySQL/MariaDB ile çalışan, kaynak kodu açık kulüp web sitesi ve yönetim panelidir.

## Local kurulum

1. Klasörü `C:\xampp\htdocs\sivas-genclerbirligi-v5` konumuna kopyalayın.
2. XAMPP'ta Apache ve MySQL'i başlatın.
3. phpMyAdmin içinde `sivas_genclerbirligi_v5` adlı boş bir veritabanı oluşturun.
4. `http://localhost/sivas-genclerbirligi-v5/install/` adresini açın.
5. XAMPP varsayılanları: sunucu `127.0.0.1`, port `3306`, kullanıcı `root`, şifre boş.
6. Yönetici e-postası ve en az 8 karakterli şifre belirleyin.

Admin: `http://localhost/sivas-genclerbirligi-v5/admin/`

## Önemli özellikler

- Apple benzeri temiz boşluk, cam yüzey ve kaydırma animasyonları
- Futbol kulübü odaklı dinamik kartlar ve yatay oyuncu vitrini
- A Takım ve altyapı için ayrı dinamik başvuru formları
- Başvurularda mevki, önceki kulüp, boy, ayak ve özel alanlar
- Her başvuru için ayrı detay, durum ve yönetici notu
- Forma numarası 1-99 ve takım içinde benzersiz kullanım
- Oyuncu fotoğrafı yoksa otomatik silüet
- Futbol sahasında aktif mevkilerin koyu yeşil gösterimi
- Takım, oyuncu, etkinlik, personel, haber, galeri, sponsor, sosyal medya ve puan durumu yönetimi
- Görsel dosyası veya URL kullanımı
- Bütün önemli düzenleme ekranlarında anlık canlı önizleme
- Maçta iki logo, rakip logosu yoksa nötr futbol arması
- Takım etkinliklerinde takım logosunun otomatik kullanılması
- Sponsor görsel ölçü ve dosya türü rehberi
- İçerik yoksa boş galeri, haber veya sponsor alanının görünmemesi
- Klasör adı değişse bile URL/CSS/JS yollarının otomatik algılanması
- CSRF koruması, PDO prepared statements, güvenli şifre ve işlem kayıtları

## Sponsor görsel rehberi

- Sponsor logosu: 600x240 PNG/WEBP, şeffaf arka plan
- Ana sayfa banner: 1600x400 JPG/PNG/WEBP
- Dikey reklam: 600x900 JPG/WEBP
- Footer sponsor alanı: 800x300 PNG/WEBP

Dosya yükleme ekranı seçilen görselin gerçek piksel ölçüsünü ve MB boyutunu gösterir.

## Hosting

- PHP 8.1, 8.2 veya 8.3
- PDO MySQL ve Fileinfo açık olmalı
- `uploads` klasörü yazılabilir olmalı (`755` çoğu sunucuda yeterlidir)
- Dosyaları `public_html` içine çıkartıp alan adınızdan `/install/` açın

Kurulumdan sonra `config.php` dosyasını yedekleyin. Gerçek yayına geçerken `/install` klasörünü silmek veya yeniden adlandırmak ek güvenlik sağlar.
