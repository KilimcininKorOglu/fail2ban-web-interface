# Fail2Ban Web Interface

Modern, güvenli ve performanslı Fail2Ban yönetim arayüzü. Bootstrap 5 dark mode, CSRF koruması, caching ve opsiyonel çoklu sunucu merkezi yönetimi ile.

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.2-blue)
![License](https://img.shields.io/badge/license-MIT-green)

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

### Çoklu Sunucu Kurulumu

Detaylı kurulum için [SETUP.md](SETUP.md) dosyasına bakın.

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

### 3. Web Server

```bash
# Apache için .htaccess (opsiyonel - IP kısıtlama)
<Files ~ "^(config|engine|db|cache|csrf)\.inc\.php$">
    Require all denied
</Files>
```

## 🔧 Konfigürasyon

### Temel Ayarlar (config.inc.php)

```php
// Environment (production'da mutlaka değiştir)
$config['environment'] = 'production';

// Güvenli şifre (hash oluştur: php -r "echo password_hash('pass', PASSWORD_DEFAULT);")
$login['native'] = array(
    array('user' => 'admin', 'password_hash' => '$2y$10$...')
);

// Fail2ban ayarları
$f2b['socket'] = '/var/run/fail2ban/fail2ban.sock';
$f2b['use_socket_check'] = false;  // Socket erişim sorunu varsa false
$f2b['usedns'] = false;            // Performans için false önerilir
```

### Çoklu Sunucu Ayarları

```php
// Her sunucuda FARKLI olmalı
$config['server_name'] = 'web-server-1';
$config['server_ip'] = '192.168.1.10';

// Merkezi database'i aktif et
$config['use_central_db'] = true;

$db_config = array(
    'host' => 'mysql.example.com',
    'database' => 'fail2ban_central',
    'username' => 'fail2ban_user',
    'password' => 'secure_password'
);
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

### Global Ban (Çoklu Sunucu)
1. Herhangi bir sunucudan IP'yi ban et
2. `global_bans` tablosuna ekle
3. Tüm sunucularda sync.php otomatik olarak bu IP'yi banlayacak

## 🔄 Senkronizasyon (Çoklu Sunucu)

### Manuel Sync

```bash
# Tüm banned IP'leri database'e gönder
php sync.php

# Global ban'ları local fail2ban'a uygula
php sync.php --apply-global

# Belirli bir sunucu için
php sync.php --server=mail-server-1
```

### Otomatik Sync (Cron)

```bash
# Crontab düzenle
sudo crontab -e

# Her 5 dakikada sync
*/5 * * * * /usr/bin/php /var/www/html/fail2ban/sync.php >> /var/log/fail2ban_sync.log 2>&1

# Global ban'ları her 10 dakikada uygula
*/10 * * * * /usr/bin/php /var/www/html/fail2ban/sync.php --apply-global >> /var/log/fail2ban_sync.log 2>&1
```

## 🗃️ Veritabanı Yapısı (Çoklu Sunucu)

```
servers          # Her fail2ban sunucusu
jails            # Her sunucudaki jail'ler
banned_ips       # Tüm banned IP'ler
global_bans      # Global ban listesi
audit_log        # Tüm işlem logları
statistics       # İstatistikler
users            # Web interface kullanıcıları
```

Detaylı şema için `database.sql` dosyasına bakın.

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
- HTTPS kullanın (Let's Encrypt ücretsiz)
- Güçlü şifreler kullanın
- IP kısıtlaması yapın (.htaccess veya firewall)
- Production'da `$config['environment'] = 'production'` yapın
- Database kullanıcısına minimum yetki verin

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
- Index'ler eklendi

### Beklenen Performans
- İlk yükleme (cache miss): 1-3 saniye
- Cache hit ile yükleme: < 0.5 saniye
- APCu ile: Neredeyse anında

## 🐛 Sorun Giderme

### Socket Permission Denied
```bash
sudo chmod 777 /var/run/fail2ban/fail2ban.sock
# veya
sudo usermod -a -G fail2ban www-data
```

### Database Connection Failed
```bash
# MySQL'e bağlanabildiğinizi test edin
mysql -h mysql_host -u fail2ban_user -p

# Firewall kontrolü
telnet mysql_host 3306
```

### Sync Script Hataları
```bash
# Manuel çalıştırıp hataları görün
php sync.php

# PHP error log kontrolü
tail -f /var/log/apache2/error.log
```

## 📁 Dosya Yapısı

```
fail2ban/
├── index.php              # Login işleme
├── login.php              # Login sayfası
├── fail2ban.php           # Ana dashboard
├── logout.php             # Logout
├── protected.php          # Örnek protected sayfa
├── engine.inc.php         # Fail2ban işlemleri
├── cache.inc.php          # Cache sistemi
├── csrf.inc.php           # CSRF koruması
├── config.inc.php         # Konfigürasyon
├── config.example.php     # Örnek config
├── db.inc.php             # Database fonksiyonları
├── sync.php               # Sync script
├── database.sql           # MySQL şeması
├── SETUP.md               # Detaylı kurulum
├── CLAUDE.md              # AI dokümantasyonu
└── README.md              # Bu dosya
```

## 🤝 Katkıda Bulunma

Katkılar memnuniyetle karşılanır! Lütfen pull request göndermeden önce:
1. Kodu test edin
2. Güvenlik açığı kontrolü yapın
3. Dokümantasyonu güncelleyin

## 📄 Lisans

MIT License - Detaylar için LICENSE dosyasına bakın.

## 🙏 Teşekkürler

- Bootstrap 5 framework
- Bootstrap Icons
- MaxMind GeoIP2
- Fail2ban project

## 📞 Destek

Sorunlar için GitHub Issues kullanın veya SETUP.md dosyasındaki troubleshooting bölümüne bakın.

---

**Not**: Bu proje bağımsız bir web interface'dir ve resmi fail2ban projesi ile doğrudan ilişkili değildir.
