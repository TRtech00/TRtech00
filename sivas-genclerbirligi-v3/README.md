# Sivas Gençlerbirliği Spor Kulübü Sitesi V3

PHP 8.1+ ve MySQL/MariaDB ile çalışan, Composer veya Node.js gerektirmeyen kulüp sitesi.

## Local kurulum

1. Klasörü `C:\xampp\htdocs\sivas-genclerbirligi-v3` içine kopyalayın.
2. phpMyAdmin'de `sivas_genclerbirligi` adlı boş veritabanı oluşturun.
3. Apache ve MySQL'i başlatın.
4. `http://localhost/sivas-genclerbirligi-v3/install/` adresini açın.
5. XAMPP varsayılan bilgileri: sunucu `127.0.0.1`, port `3306`, kullanıcı `root`, şifre boş.
6. Kurulumdan sonra yönetim paneli: `http://localhost/sivas-genclerbirligi-v3/admin/`

## Özellikler

- A Takım ve altyapı için ayrı başvuru formları
- 1-99 forma numarası ve takım içinde benzersizlik kontrolü
- Bir oyuncuya birden çok mevki atama
- Oyuncu arşivlendiğinde forma numarasını serbest bırakma
- Oyuncu fotoğrafı yoksa otomatik silüet
- Oyuncu fotoğrafının arkasında yarı saydam takım arması
- Oyuncu detayında futbol sahası ve aktif mevkilerin yeşil gösterimi
- A Takım, U18 ve U16 için ayrı logo, renk ve açıklama
- Maç, antrenman, toplantı, seçme ve diğer etkinlikler
- Takım logosunu etkinliğe otomatik alma
- Dosyadan veya URL ile logo/görsel ekleme
- Rakip logosu yoksa otomatik nötr futbol logosu
- Başkan, teknik direktör ve yardımcı antrenör yönetimi
- Sosyal medya ve iletişim bağlantıları
- Kulübe Katıl açılır penceresi
- Oyuncu, takım ve etkinlik formlarında canlı önizleme
- Mobil uyumlu, animasyonlu arayüz

## Güvenlik

- PDO hazırlanmış sorgular
- CSRF koruması
- Şifrelerin `password_hash` ile saklanması
- Görsel MIME ve boyut kontrolü
- Dizin listelemenin kapatılması

## Not

`config.php` kurulum sırasında oluşturulur. ZIP paketindeki `config.example.php` yalnızca örnektir.
