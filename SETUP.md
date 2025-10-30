# Merkezi MySQL Kurulum Rehberi

Bu rehber, birden fazla bağımsız fail2ban sunucusunu merkezi bir MySQL veritabanı ile yönetmek için gereken adımları açıklar.

## Mimari Genel Bakış

```
┌─────────────────┐
│   Web Server 1  │
│   (fail2ban)    │──┐
└─────────────────┘  │
                     │
┌─────────────────┐  │     ┌─────────────────────┐
│   Mail Server   │  │     │  Central MySQL DB   │
│   (fail2ban)    │──┼────▶│                     │
└─────────────────┘  │     │  - servers          │
                     │     │  - jails            │
┌─────────────────┐  │     │  - banned_ips       │
│   DB Server     │  │     │  - global_bans      │
│   (fail2ban)    │──┘     │  - audit_log        │
└─────────────────┘        └─────────────────────┘
```

### Özellikler

1. **Merkezi Ban Yönetimi**: Tüm sunuculardaki banları tek bir yerden görüntüle
2. **Global Ban List**: Bir IP'yi tüm sunucularda otomatik olarak banla
3. **Audit Log**: Tüm ban/unban işlemlerini takip et
4. **İstatistikler**: Server bazlı veya global istatistikler
5. **Bağımsız Çalışma**: Her sunucu kendi fail2ban'ını bağımsız çalıştırır

## 1. Merkezi MySQL Sunucusu Kurulumu

### Adım 1: MySQL Veritabanı Oluştur

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

### Adım 2: MySQL Uzaktan Erişimi Aktif Et

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

## 2. Her Fail2Ban Sunucusunda Yapılacaklar

### Adım 1: Dosyaları Kopyala

Her sunucuda fail2ban web interface dosyalarını kopyala:

```bash
# Web dizinine kopyala
sudo cp -r fail2ban/ /var/www/html/fail2ban/
sudo chown -R www-data:www-data /var/www/html/fail2ban/
```

### Adım 2: config.inc.php Ayarları

Her sunucuda `config.inc.php` dosyasını düzenle (veya `config.example.php`'yi kopyalayıp düzenle):

```bash
# İlk kurulumda example dosyasını kopyala
cp config.example.php config.inc.php
nano config.inc.php
```

```php
// Server identification (HER SUNUCUDA FARKLI OLMALI)
$config['server_name'] = 'web-server-1';  // UNIQUE: web-server-1, mail-server-1, etc.
$config['server_ip'] = '192.168.1.10';    // Bu sunucunun IP'si

// Centralized MySQL database configuration
$config['use_central_db'] = true;  // false ise sadece local çalışır, sync.php çalışmaz
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

### Adım 3: PHP PDO MySQL Extension Yükle

```bash
sudo apt-get install php-mysql
sudo systemctl restart apache2  # veya php-fpm
```

### Adım 4: Sync Script'i Test Et

```bash
# Manuel sync testi
php /var/www/html/fail2ban/sync.php

# Çıktı kontrol et - hata olmamalı
# Başarılı ise cron'a ekle
```

### Adım 5: Otomatik Senkronizasyon (Cron)

Her sunucuda cron job ekle:

```bash
# Crontab düzenle
sudo crontab -e

# Her 5 dakikada bir sync yap
*/5 * * * * /usr/bin/php /var/www/html/fail2ban/sync.php >> /var/log/fail2ban_sync.log 2>&1

# Global banları her 10 dakikada bir uygula
*/10 * * * * /usr/bin/php /var/www/html/fail2ban/sync.php --apply-global >> /var/log/fail2ban_sync.log 2>&1
```

## 3. Kullanım Senaryoları

### Senaryo 1: Sadece Görüntüleme (Read-Only)

Merkezi veritabanını sadece raporlama için kullan:

```php
// config.inc.php
$config['use_central_db'] = true;
$config['db_mode'] = 'readonly';  // Sadece okuma
```

Her sunucu kendi fail2ban'ını yönetir, sadece veriler database'e aktarılır.

### Senaryo 2: Global Ban Yönetimi

Bir IP'yi tüm sunucularda banla:

1. Web interface'den herhangi bir sunucuya gir
2. "Global Ban" butonuna tıkla
3. IP adresini ve sebebini gir
4. Tüm sunucularda otomatik olarak sync scripti bu IP'yi banlayacak

### Senaryo 3: Merkezi Dashboard

Tüm sunucuları tek bir dashboard'dan izle:

```php
// Özel bir merkezi dashboard oluştur (isteğe bağlı)
// Tüm sunucuların verilerini database'den çek
$all_servers = db_get_servers();
$all_banned_ips = db_get_banned_ips(); // Tüm sunucular
```

## 4. Veritabanı Tabloları

### servers
Her fail2ban sunucusunu takip eder.

### jails
Her sunucudaki jail'leri takip eder.

### banned_ips
Tüm sunuculardaki banned IP'leri saklar.

### global_bans
Tüm sunuculara uygulanması gereken IP'ler.

### audit_log
Tüm ban/unban işlemlerinin log'u.

### statistics
Günlük istatistikler.

## 5. Bakım ve İzleme

### Log Kontrolü

```bash
# Sync log'larını kontrol et
tail -f /var/log/fail2ban_sync.log

# MySQL slow query log'u aktif et
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
# slow_query_log = 1
# long_query_time = 2
```

### Database Boyutu Yönetimi

Eski kayıtları temizlemek için:

```sql
-- 90 günden eski inactive ban kayıtlarını sil
DELETE FROM banned_ips
WHERE is_active = 0 AND unban_time < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Eski audit log kayıtlarını sil
DELETE FROM audit_log
WHERE action_time < DATE_SUB(NOW(), INTERVAL 180 DAY);

-- İstatistikleri optimize et
OPTIMIZE TABLE banned_ips;
OPTIMIZE TABLE audit_log;
```

### Backup

```bash
# Günlük backup
mysqldump -u fail2ban_user -p fail2ban_central | gzip > fail2ban_backup_$(date +%Y%m%d).sql.gz

# Cron ile otomatik backup
0 2 * * * mysqldump -u fail2ban_user -p'password' fail2ban_central | gzip > /backup/fail2ban_$(date +\%Y\%m\%d).sql.gz
```

## 6. Güvenlik Önerileri

1. **SSL/TLS Kullan**: MySQL bağlantılarını şifrele
2. **Güçlü Şifre**: Database kullanıcısı için güçlü şifre
3. **Firewall**: MySQL portunu sadece gerekli IP'lere aç
4. **Yetkilendirme**: Database kullanıcısına minimum gerekli yetkileri ver
5. **VPN**: Mümkünse sunucular arası iletişimi VPN üzerinden yap

## 7. Sorun Giderme

### Bağlantı Hatası

```bash
# MySQL bağlantısını test et
mysql -h 192.168.1.100 -u fail2ban_user -p fail2ban_central

# Ping testi
ping 192.168.1.100

# Port testi
telnet 192.168.1.100 3306
```

### Sync Çalışmıyor

```bash
# Manuel çalıştır ve hata mesajlarını kontrol et
php /var/www/html/fail2ban/sync.php

# PHP error log'u kontrol et
tail -f /var/log/apache2/error.log
```

### Performans Sorunları

```sql
-- Index'leri kontrol et
SHOW INDEX FROM banned_ips;

-- Yavaş sorguları bul
SELECT * FROM mysql.slow_log ORDER BY query_time DESC LIMIT 10;
```

## 8. İleri Seviye: Master-Master Replication (İsteğe Bağlı)

Yüksek erişilebilirlik için MySQL master-master replication kullanabilirsiniz. Bu durumda her bölgede bir MySQL master olur ve birbirleriyle senkronize çalışır.

## Sonuç

Bu kurulum ile:
- ✅ Tüm sunucuları tek bir yerden izle
- ✅ Global ban listesi oluştur
- ✅ Merkezi audit log ve raporlama
- ✅ Her sunucu bağımsız çalışır (database down olsa bile)
- ✅ Ölçeklenebilir mimari
