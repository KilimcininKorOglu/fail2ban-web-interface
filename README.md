# Fail2Ban Web Interface

Modern, güvenli ve performanslı Fail2Ban yönetim arayüzü. Bootstrap 5 dark mode, CSRF koruması, caching ve opsiyonel çoklu sunucu merkezi yönetimi ile.

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.2-blue)
![License](https://img.shields.io/badge/license-MIT-green)

## 📑 İçindekiler

- [Özellikler](#-özellikler)
- [Hızlı Başlangıç](#-hızlı-başlangıç)
- [Gereksinimler](#-gereksinimler)
- [Kurulum](#-kurulum)
- [Konfigürasyon](#-konfigürasyon)
- [Kullanım](#-kullanım)
- [Çoklu Sunucu Kurulumu](#-çoklu-sunucu-kurulumu)
- [Güvenlik](#-güvenlik)
- [Performans](#-performans)
- [Sorun Giderme](#-sorun-giderme)
- [Bakım](#-bakım)

## ✨ Özellikler

### Temel Özellikler

- 🎨 **Modern UI**: Bootstrap 5.3 dark mode, glass-morphism tasarım
- 🔒 **Güvenlik**: CSRF koruması, XSS koruması, bcrypt password hashing
- ⚡ **Performans**: APCu/File hybrid caching, DNS lookup devre dışı
- 🌍 **GeoIP**: IP'lerin ülke bilgisi (opsiyonel)
- 📊 **Dashboard**: Tüm jail'leri ve banned IP'leri tek ekranda görüntüle

### İleri Özellikler (Opsiyonel)

- 🖥️ **Multi-Server**: Birden fazla bağımsız fail2ban sunucusunu merkezi MySQL ile yönet
- 🌐 **Global Ban**: Bir IP'yi tüm sunucularda otomatik olarak banla
- 📝 **Audit Log**: Tüm ban/unban işlemlerinin detaylı kaydı
- 📈 **İstatistikler**: Server bazlı ve global istatistikler
- 🔄 **Auto Sync**: Cron ile otomatik senkronizasyon

## 🚀 Hızlı Başlangıç

### Tek Sunucu Kurulumu (En Basit)

```bash
# 1. Dosyaları kopyala
sudo cp -r fail2ban/ /var/www/html/
cd /var/www/html/fail2ban/

# 2. Config dosyasını oluştur
cp config.example.php config.inc.php
nano config.inc.php

# 3. Şifre hash'i oluştur
php -r "echo password_hash('your_password', PASSWORD_DEFAULT) . PHP_EOL;"

# 4. config.inc.php'de şifreyi güncelle
# $login['native'] = array(
#     array('user' => 'admin', 'password_hash' => '$2y$10$...')
# );

# 5. Fail2ban socket izinlerini ayarla
sudo chmod 777 /var/run/fail2ban/fail2ban.sock

# 6. Tarayıcıdan erişim
# http://your-server/fail2ban/
```

## 📋 Gereksinimler

### Zorunlu

- PHP >= 7.2
- fail2ban kurulu ve çalışıyor
- Apache/Nginx web server
- PHP exec() fonksiyonu aktif

### Opsiyonel

- php-apcu (caching için)
- php-mysql + MySQL (çoklu sunucu için)
- composer (GeoIP için)

## 📦 Kurulum

### 1. PHP Bağımlılıkları

```bash
# APCu (performans için önerilir)
sudo apt-get install php-apcu

# MySQL (sadece çoklu sunucu için)
sudo apt-get install php-mysql

# GeoIP (opsiyonel)
composer install
```

### 2. Fail2ban İzinleri

```bash
# Seçenek 1: Socket'e direkt erişim (en kolay)
sudo chmod 777 /var/run/fail2ban/fail2ban.sock

# Seçenek 2: Grup izni (daha güvenli)
sudo usermod -a -G fail2ban www-data
sudo chmod 660 /var/run/fail2ban/fail2ban.sock

# Seçenek 3: fail2ban-client kullan (config.inc.php'de)
# $f2b['use_socket_check'] = false;
```

### 3. Web Server Güvenlik (Opsiyonel)

Apache `.htaccess` ile hassas dosyaları koru:

```apache
<Files ~ "^(config|engine|db|cache|csrf)\.inc\.php$">
    Require all denied
</Files>

# IP kısıtlaması (opsiyonel)
<RequireAll>
    Require ip 192.168.1.0/24
</RequireAll>
```

## ⚙️ Konfigürasyon

### Temel Ayarlar (config.inc.php)

```php
// Environment (production'da mutlaka değiştir)
$config['environment'] = 'production';

// Application title
$config['title'] = 'Fail2Ban Dashboard';

// Güvenli şifre (hash oluştur)
php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"

$login['native'] = array(
    array(
        'user' => 'admin',
        'password_hash' => '$2y$10$...'  // Yukarıdaki komuttan çıkan hash
    )
);

// Fail2ban ayarları
$f2b['socket'] = '/var/run/fail2ban/fail2ban.sock';
$f2b['use_socket_check'] = false;  // Socket erişim sorunu varsa false
$f2b['usedns'] = false;            // Performans için false önerilir
$f2b['noempt'] = true;             // Boş jail'leri gizle
$f2b['jainfo'] = true;             // Jail bilgilerini göster
```

### Tek Sunucu Modu (Default)

```php
// Single server setup
$config['server_name'] = 'my-server';
$config['server_ip'] = '127.0.0.1';
$config['use_central_db'] = false;  // Merkezi DB kullanma
```

## 📊 Kullanım

### Ban İşlemi

1. Dashboard'dan "Manually Ban IP Address" bölümüne git
2. Jail seç
3. IP adresini gir
4. "Ban IP" butonuna tıkla

### Unban İşlemi

1. Banned IPs listesinden IP'yi bul
2. "Unban" butonuna tıkla
3. Onay ver

### Refresh

- Dashboard üst kısmındaki "Refresh" butonuna tıkla
- Cache temizlenir ve güncel veriler çekilir

---

## 🖥️ Çoklu Sunucu Kurulumu

Birden fazla bağımsız fail2ban sunucusunu merkezi bir MySQL veritabanı ile yönetin.

### 📦 İki Kurulum Seçeneği

#### Seçenek 1: Lightweight Agent (ÖNERİLEN)

- ✅ Yan sunucularda sadece agent çalışır (PHP CLI yeterli)
- ✅ Web server gerekmez
- ✅ Minimal resource kullanımı
- ✅ Kolay kurulum
- [Agent Dokümantasyonu →](agent/README.md)

#### Seçenek 2: Full Interface (Her Sunucuda)

- Her sunucuda full web interface
- Daha fazla resource kullanımı
- Her sunucudan yönetim imkanı

### Mimari (Agent Kullanarak - Önerilen)

```
┌─────────────────────────┐
│   Central Server        │
│   ┌─────────────────┐   │
│   │ Web Interface   │   │◀──── Yönetim (Tarayıcı)
│   └─────────────────┘   │
│   ┌─────────────────┐   │
│   │  MySQL Database │   │
│   └─────────────────┘   │
└─────────────────────────┘
            ▲
            │ MySQL (3306)
            │
  ┌─────────┼─────────┐
  │         │         │
  │         │         │
┌─┴──┐    ┌─┴──┐    ┌─┴──┐
│Web │    │Mail│    │DB  │   Yan Sunucular
│Srv │    │Srv │    │Srv │   (Sadece Agent)
├────┤    ├────┤    ├────┤
│f2b │    │f2b │    │f2b │   fail2ban running
│    │    │    │    │    │
│agt │    │agt │    │agt │   agent.php (cron)
└────┘    └────┘    └────┘
```

**Avantajlar:**

- ✅ Yan sunucularda web server gerekmez
- ✅ Minimal kurulum (3 dosya)
- ✅ Düşük resource kullanımı
- ✅ Kolay yönetim

### Özellikler

✅ **Merkezi Ban Yönetimi**: Tüm sunuculardaki banları tek yerden görüntüle
✅ **Global Ban List**: Bir IP'yi tüm sunucularda otomatik banla
✅ **Audit Log**: Tüm ban/unban işlemlerini takip et
✅ **İstatistikler**: Server bazlı veya global istatistikler
✅ **Bağımsız Çalışma**: Her sunucu kendi fail2ban'ını bağımsız çalıştırır

### 1. Merkezi MySQL Sunucusu Kurulumu

```bash
# MySQL'e root olarak giriş
mysql -u root -p

# Veritabanı ve kullanıcı oluştur
CREATE DATABASE fail2ban_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fail2ban_user'@'%' IDENTIFIED BY 'güçlü_bir_şifre';
GRANT ALL PRIVILEGES ON fail2ban_central.* TO 'fail2ban_user'@'%';
FLUSH PRIVILEGES;
EXIT;

# Şema dosyasını import et
mysql -u fail2ban_user -p fail2ban_central < database.sql
```

#### MySQL Uzaktan Erişim

```bash
# /etc/mysql/mysql.conf.d/mysqld.cnf düzenle
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# bind-address satırını değiştir:
bind-address = 0.0.0.0

# MySQL'i yeniden başlat
sudo systemctl restart mysql

# Firewall'da 3306 portunu aç
sudo ufw allow 3306/tcp
```

### 2. Yan Sunucularda Agent Kurulumu (Önerilen)

**Çok daha basit ve hafif!**

```bash
# 1. Agent dosyalarını kopyala
cd /path/to/fail2ban/
sudo cp -r agent/ /opt/fail2ban-agent/

# 2. Kurulum scriptini çalıştır
cd /opt/fail2ban-agent/
sudo ./install.sh

# 3. Config düzenle (her sunucuda farklı server_name!)
sudo nano /opt/fail2ban-agent/agent.conf.php

# 4. Test et
php /opt/fail2ban-agent/agent.php --test

# 5. Cron ekle
sudo crontab -e
# */5 * * * * /usr/bin/php /opt/fail2ban-agent/agent.php >> /var/log/fail2ban_agent.log 2>&1
```

**Gereksinimler (Agent için):**

- PHP CLI (php-cli)
- PHP MySQL extension (php-mysql)
- fail2ban kurulu
- Web server GEREKMİYOR!

Detaylı agent dokümantasyonu: [agent/README.md](agent/README.md)

---

### 2b. Alternatif: Full Interface (Her Sunucuda)

Eğer her sunucuda web interface istiyorsanız:

```bash
# Web dizinine kopyala
sudo cp -r fail2ban/ /var/www/html/fail2ban/
sudo chown -R www-data:www-data /var/www/html/fail2ban/
cd /var/www/html/fail2ban/
```

#### Adım 2: Config Ayarları

```bash
# Config dosyasını oluştur
cp config.example.php config.inc.php
nano config.inc.php
```

**ÖNEMLI:** Her sunucuda farklı `server_name` kullanın!

```php
// Server identification (HER SUNUCUDA FARKLI OLMALI)
$config['server_name'] = 'web-server-1';  // web-server-1, mail-server-1, db-server-1 ...
$config['server_ip'] = '192.168.1.10';    // Bu sunucunun IP'si

// Merkezi database'i aktif et
$config['use_central_db'] = true;

// Database bağlantı bilgileri (TÜM SUNUCULARDA AYNI)
$db_config = array(
    'host' => '192.168.1.100',         // MySQL sunucusunun IP'si
    'port' => 3306,
    'database' => 'fail2ban_central',
    'username' => 'fail2ban_user',
    'password' => 'güçlü_bir_şifre',
    'charset' => 'utf8mb4'
);
```

**UYARI:**

- `$db_config` değişkeni **mutlaka** `config.inc.php` içinde tanımlanmalı
- `$config['use_central_db'] = false` ise sync.php çalışmaz (sadece local mod)
- Her sunucunun `server_name`'i benzersiz (unique) olmalı

#### Adım 3: PHP MySQL Extension

```bash
sudo apt-get install php-mysql
sudo systemctl restart apache2  # veya php-fpm
```

#### Adım 4: Sync Testi

```bash
# Manuel sync testi
php sync.php

# Çıktıda hata olmamalı
# Örnek çıktı:
# [2025-01-15 10:30:00] Starting sync for server: web-server-1 (ID: 1)
# [2025-01-15 10:30:01] Syncing local bans to database...
# [2025-01-15 10:30:02] Sync completed successfully
```

#### Adım 5: Otomatik Sync (Cron)

```bash
# Crontab düzenle
sudo crontab -e

# Her 5 dakikada bir local bans'ları database'e sync et
*/5 * * * * /usr/bin/php /var/www/html/fail2ban/sync.php >> /var/log/fail2ban_sync.log 2>&1

# Her 10 dakikada bir global banları uygula
*/10 * * * * /usr/bin/php /var/www/html/fail2ban/sync.php --apply-global >> /var/log/fail2ban_sync.log 2>&1
```

### 3. Kullanım Senaryoları

#### Senaryo 1: Sadece Görüntüleme

Merkezi veritabanını sadece raporlama için kullan. Her sunucu kendi fail2ban'ını yönetir, sadece veriler database'e aktarılır.

```php
$config['use_central_db'] = true;
$config['db_mode'] = 'readonly';  // Sadece okuma
```

#### Senaryo 2: Global Ban Yönetimi

Bir IP'yi tüm sunucularda banlamak için:

**SQL ile manuel:**

```sql
INSERT INTO global_bans (ip_address, reason, banned_by, permanent)
VALUES ('123.45.67.89', 'Brute force attack', 'admin', 0);
```

**PHP ile (db.inc.php fonksiyonu kullanarak):**

```php
db_add_global_ban('123.45.67.89', 'Brute force attack', 'admin', false);
```

Sync script otomatik olarak bu IP'yi tüm sunucularda banlayacak.

#### Senaryo 3: Merkezi Dashboard

Tüm sunucuların verilerini database'den çek ve merkezi dashboard oluştur:

```php
require_once('db.inc.php');

// Tüm sunucuları getir
$all_servers = db_get_servers();

// Tüm banned IP'leri getir
$all_banned_ips = db_get_banned_ips();

// Belirli bir sunucunun banned IP'leri
$server1_bans = db_get_banned_ips($server_id);

// İstatistikler
$stats = db_get_statistics(null, 30); // Son 30 gün
```

### 4. Veritabanı Tabloları

| Tablo | Açıklama |
|-------|----------|
| `servers` | Her fail2ban sunucusunu takip eder |
| `jails` | Her sunucudaki jail'leri takip eder |
| `banned_ips` | Tüm sunuculardaki banned IP'leri saklar |
| `global_bans` | Tüm sunuculara uygulanması gereken IP'ler |
| `audit_log` | Tüm ban/unban işlemlerinin log'u |
| `statistics` | Günlük istatistikler |
| `users` | Web interface kullanıcıları (gelecek sürümler için) |

Detaylı şema için `database.sql` dosyasına bakın.

### 5. Manuel Sync Komutları

```bash
# Tüm banned IP'leri database'e gönder
php sync.php

# Global ban'ları local fail2ban'a uygula
php sync.php --apply-global

# Belirli bir sunucu için
php sync.php --server=mail-server-1

# Yardım
php sync.php --help
```

---

## 🔒 Güvenlik

### Mevcut Korumalar

✅ CSRF koruması (token-based)
✅ XSS koruması (htmlspecialchars)
✅ Command injection koruması (escapeshellarg)
✅ Bcrypt password hashing
✅ Session regeneration
✅ Input validation
✅ Audit logging (çoklu sunucu modu)

### Öneriler

**Zorunlu (Production için):**

- ✅ HTTPS kullanın (Let's Encrypt ücretsiz)
- ✅ Güçlü şifreler kullanın (bcrypt hash)
- ✅ `$config['environment'] = 'production'` yapın
- ✅ `.htaccess` ile hassas dosyaları koruyun

**Opsiyonel (İleri Seviye):**

- IP kısıtlaması yapın (.htaccess veya firewall)
- Database kullanıcısına minimum yetki verin
- MySQL bağlantılarını SSL/TLS ile şifreleyin
- VPN kullanın (sunucular arası iletişim için)
- Firewall'da sadece gerekli portları açın

### MySQL Güvenlik

```bash
# SSL/TLS bağlantı zorla
GRANT ALL PRIVILEGES ON fail2ban_central.* TO 'fail2ban_user'@'%' REQUIRE SSL;

# Specific IP'den bağlantı izni
CREATE USER 'fail2ban_user'@'192.168.1.%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON fail2ban_central.* TO 'fail2ban_user'@'192.168.1.%';
```

---

## 📈 Performans

### Cache Stratejisi

- **APCu**: Memory cache (en hızlı)
- **File Cache**: Fallback (APCu yoksa)
- **TTL**: 30 saniye (jail data için)
- **GeoIP**: Static array cache (request süresince)

### Optimizasyonlar

- DNS lookups devre dışı (`$f2b['usedns'] = false`)
- sleep() çağrıları kaldırıldı
- Database query'leri optimize edildi
- Index'ler eklendi (database.sql)

### Beklenen Performans

- **İlk yükleme** (cache miss): 1-3 saniye
- **Cache hit** ile yükleme: < 0.5 saniye
- **APCu** ile: Neredeyse anında

### Cache Kontrol

```bash
# APCu kurulu mu?
php -m | grep apcu

# Cache temizle (web interface'den)
# "Refresh" butonuna tıkla

# Manuel cache temizle
php -r "if(function_exists('apcu_clear_cache')) apcu_clear_cache();"
```

---

## 🐛 Sorun Giderme

### Socket Permission Denied

**Sorun:** `Permission denied to socket: /var/run/fail2ban/fail2ban.sock`

**Çözüm:**

```bash
# Seçenek 1: Full erişim (en kolay)
sudo chmod 777 /var/run/fail2ban/fail2ban.sock

# Seçenek 2: Grup izni (daha güvenli)
sudo usermod -a -G fail2ban www-data
sudo chmod 660 /var/run/fail2ban/fail2ban.sock
sudo systemctl restart apache2

# Seçenek 3: Socket bypass (config.inc.php)
$f2b['use_socket_check'] = false;
```

### Database Connection Failed

**Sorun:** `Database connection failed`

**Kontroller:**

```bash
# MySQL'e bağlanabildiğinizi test edin
mysql -h 192.168.1.100 -u fail2ban_user -p fail2ban_central

# Firewall kontrolü
telnet 192.168.1.100 3306

# MySQL loglarını kontrol et
sudo tail -f /var/log/mysql/error.log

# Kullanıcı izinlerini kontrol et
mysql -u root -p
SHOW GRANTS FOR 'fail2ban_user'@'%';
```

### Sync Script Hataları

**Sorun:** Sync script çalışmıyor veya hata veriyor

**Debug:**

```bash
# Manuel çalıştır ve hataları gör
php sync.php

# PHP error log kontrolü
tail -f /var/log/apache2/error.log

# Sync log kontrolü
tail -f /var/log/fail2ban_sync.log

# Database bağlantısını test et
php -r "
require_once('config.inc.php');
require_once('db.inc.php');
\$db = get_db_connection();
echo \$db ? 'DB OK' : 'DB FAIL';
"
```

### Slow Page Load

**Sorun:** Sayfa yüklenmesi çok yavaş

**Kontroller:**

```bash
# Cache çalışıyor mu?
php -r "
require_once('cache.inc.php');
cache_set('test', 'value', 60);
echo cache_get('test') === 'value' ? 'Cache OK' : 'Cache FAIL';
"

# DNS lookup'ı kapat (config.inc.php)
$f2b['usedns'] = false;

# APCu kur
sudo apt-get install php-apcu
sudo systemctl restart apache2
```

### GeoIP Warnings

**Sorun:** Deprecation warnings from GeoIP2

**Çözüm:** Warnings zaten suppress edilmiş (`@` operator). Eğer hala görüyorsan:

```bash
# GeoIP'yi devre dışı bırak (fail2ban.php'de comment out)
# if (file_exists('vendor/autoload.php')) {
#   @require_once 'vendor/autoload.php';
# }

# Veya GeoIP2 güncellemesi
composer update
```

---

## 🔧 Bakım

### Log Yönetimi

```bash
# Sync loglarını kontrol et
tail -f /var/log/fail2ban_sync.log

# Log rotation (logrotate)
sudo nano /etc/logrotate.d/fail2ban-sync

# İçerik:
# /var/log/fail2ban_sync.log {
#     weekly
#     rotate 4
#     compress
#     missingok
#     notifempty
# }
```

### Database Bakımı

**Eski kayıtları temizle:**

```sql
-- 90 günden eski inactive ban kayıtlarını sil
DELETE FROM banned_ips
WHERE is_active = 0 AND unban_time < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Eski audit log kayıtlarını sil (180 gün)
DELETE FROM audit_log
WHERE action_time < DATE_SUB(NOW(), INTERVAL 180 DAY);

-- Tabloları optimize et
OPTIMIZE TABLE banned_ips;
OPTIMIZE TABLE audit_log;
```

**Database boyut kontrolü:**

```sql
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'fail2ban_central'
ORDER BY (data_length + index_length) DESC;
```

### Backup

```bash
# Manuel backup
mysqldump -u fail2ban_user -p fail2ban_central | gzip > fail2ban_backup_$(date +%Y%m%d).sql.gz

# Otomatik günlük backup (cron)
0 2 * * * mysqldump -u fail2ban_user -p'password' fail2ban_central | gzip > /backup/fail2ban_$(date +\%Y\%m\%d).sql.gz

# Backup retention (7 gün)
find /backup/fail2ban_*.sql.gz -mtime +7 -delete
```

### MySQL Performance Tuning

```bash
# Slow query log aktif et
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Ekle:
slow_query_log = 1
long_query_time = 2
slow_query_log_file = /var/log/mysql/slow.log

# Restart
sudo systemctl restart mysql

# Yavaş sorguları kontrol et
sudo mysqldumpslow -t 10 /var/log/mysql/slow.log
```

### Index Kontrolü

```sql
-- Eksik index'leri kontrol et
SHOW INDEX FROM banned_ips;

-- Kullanılmayan index'leri bul
SELECT * FROM sys.schema_unused_indexes WHERE object_schema = 'fail2ban_central';
```

---

## 📁 Dosya Yapısı

```
fail2ban/
├── index.php              # Login işleme
├── login.php              # Login sayfası (Bootstrap 5 dark)
├── fail2ban.php           # Ana dashboard
├── logout.php             # Logout
├── protected.php          # Örnek protected sayfa
├── engine.inc.php         # Fail2ban işlemleri
├── cache.inc.php          # Cache sistemi (APCu/File)
├── csrf.inc.php           # CSRF koruması
├── config.inc.php         # Konfigürasyon (gitignore)
├── config.example.php     # Örnek config
├── db.inc.php             # Database fonksiyonları
├── sync.php               # Sync script (cron için)
├── database.sql           # MySQL şeması
├── README.md              # Bu dosya
├── CLAUDE.md              # AI dokümantasyonu
├── .gitignore             # Git ignore rules
└── agent/                 # Lightweight agent (yan sunucular için)
    ├── agent.php          # Agent script
    ├── agent.conf.php     # Config (gitignore)
    ├── agent.conf.example.php  # Örnek config
    ├── install.sh         # Otomatik kurulum
    └── README.md          # Agent dokümantasyonu
```

---

## 🤝 Katkıda Bulunma

Katkılar memnuniyetle karşılanır! Lütfen pull request göndermeden önce:

1. Kodu test edin
2. Güvenlik açığı kontrolü yapın
3. Dokümantasyonu güncelleyin

---

## 📄 Lisans

MIT License - Detaylar için LICENSE dosyasına bakın.

---

## 🙏 Teşekkürler

- [Bootstrap 5](https://getbootstrap.com/) - UI framework
- [Bootstrap Icons](https://icons.getbootstrap.com/) - Icon set
- [MaxMind GeoIP2](https://www.maxmind.com/) - IP geolocation
- [Fail2ban](https://www.fail2ban.org/) - Intrusion prevention

---

## 📞 Destek

- **Issues**: GitHub Issues
- **Email**: <kerem@keremgok.com>
- **Dokümantasyon**: CLAUDE.md (AI assistant için)

---

**Not**: Bu proje bağımsız bir web interface'dir ve resmi fail2ban projesi ile doğrudan ilişkili değildir.
