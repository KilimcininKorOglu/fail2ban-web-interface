# Fail2Ban Agent

Hafif Fail2Ban veri toplama agent'ı. Yan sunucularda full web interface yerine sadece bu agent çalışır.

## 🎯 Ne İşe Yarar?

Agent, fail2ban çalıştıran sunucularda minimal kurulum ile:

- ✅ Local banned IP'leri merkezi database'e gönderir
- ✅ Global ban listesini alıp local fail2ban'a uygular
- ✅ Minimal resource kullanımı (sadece PHP CLI gerekli)
- ✅ Web server gerekmez

## 📦 Kurulum

### Otomatik Kurulum (Önerilen)

```bash
# Agent klasörüne git
cd agent/

# Kurulum scriptini çalıştır
sudo ./install.sh
```

Script otomatik olarak:

- ✅ PHP ve gerekli extension'ları kontrol eder
- ✅ Agent'ı `/opt/fail2ban-agent/` altına kurar
- ✅ Config dosyasını oluşturur
- ✅ Log dosyasını hazırlar

### Manuel Kurulum

```bash
# 1. Kurulum dizini oluştur
sudo mkdir -p /opt/fail2ban-agent

# 2. Dosyaları kopyala
sudo cp agent.php /opt/fail2ban-agent/
sudo cp agent.conf.example.php /opt/fail2ban-agent/agent.conf.php

# 3. Executable yap
sudo chmod +x /opt/fail2ban-agent/agent.php

# 4. Log dosyası oluştur
sudo touch /var/log/fail2ban_agent.log
```

## ⚙️ Konfigürasyon

Config dosyasını düzenle:

```bash
sudo nano /opt/fail2ban-agent/agent.conf.php
```

**ÖNEMLI:** Her sunucuda `server_name` farklı olmalı!

```php
$agent_config = array(
    // Server identification (HER SUNUCUDA FARKLI)
    'server_name' => 'web-server-1',    // web-server-1, mail-server-1, db-server-1
    'server_ip' => '192.168.1.10',      // Bu sunucunun IP'si

    // Central database
    'db' => array(
        'host' => '192.168.1.100',      // MySQL server IP
        'port' => 3306,
        'database' => 'fail2ban_central',
        'username' => 'fail2ban_user',
        'password' => 'your_secure_password',
        'charset' => 'utf8mb4'
    )
);
```

## 🧪 Test

Kurulum sonrası test et:

```bash
# Bağlantı testi
php /opt/fail2ban-agent/agent.php --test

# Beklenen çıktı:
# Testing configuration...
# Server: web-server-1
# Database: 192.168.1.100/fail2ban_central
# ✓ Database connection: OK
# ✓ Fail2ban access: OK
#
# All tests passed!
```

## 🚀 Kullanım

### Manuel Çalıştırma

```bash
# Local bans'ları database'e sync et
php /opt/fail2ban-agent/agent.php

# Global ban'ları local fail2ban'a uygula
php /opt/fail2ban-agent/agent.php --apply-global

# Yardım
php /opt/fail2ban-agent/agent.php --help
```

### Otomatik Çalıştırma (Cron)

```bash
# Crontab düzenle
sudo crontab -e

# Bu satırları ekle:

# Her 5 dakikada local bans'ları sync et
*/5 * * * * /usr/bin/php /opt/fail2ban-agent/agent.php >> /var/log/fail2ban_agent.log 2>&1

# Her 10 dakikada global ban'ları uygula
*/10 * * * * /usr/bin/php /opt/fail2ban-agent/agent.php --apply-global >> /var/log/fail2ban_agent.log 2>&1
```

## 📊 Log İzleme

```bash
# Real-time log
tail -f /var/log/fail2ban_agent.log

# Son 50 satır
tail -50 /var/log/fail2ban_agent.log

# Hata ara
grep ERROR /var/log/fail2ban_agent.log
```

## 🔄 Ana Sunucu vs Agent

### Ana Sunucu (Full Interface)

- ✅ Web interface
- ✅ Dashboard
- ✅ Manuel ban/unban
- ✅ Tüm jails görüntüleme
- ✅ Database write/read
- Gereksinimler: Apache/Nginx, PHP, MySQL client

### Yan Sunucu (Agent)

- ✅ Sadece veri gönderme
- ✅ Global ban uygulama
- ✅ Minimal resource
- ✅ Web server gerekmez
- Gereksinimler: PHP CLI, MySQL client

## 📁 Dosya Yapısı

```
agent/
├── agent.php                   # Ana agent script
├── agent.conf.php              # Konfigürasyon (gitignore)
├── agent.conf.example.php      # Örnek config
├── install.sh                  # Otomatik kurulum scripti
└── README.md                   # Bu dosya
```

## 🐛 Sorun Giderme

### Database Connection Failed

```bash
# MySQL bağlantısını test et
mysql -h 192.168.1.100 -u fail2ban_user -p fail2ban_central

# Firewall kontrolü
telnet 192.168.1.100 3306

# PHP MySQL extension kontrolü
php -m | grep pdo_mysql
```

### Fail2ban Access Denied

```bash
# fail2ban-client test
fail2ban-client ping

# Socket izinlerini kontrol
ls -la /var/run/fail2ban/fail2ban.sock

# Web server kullanıcısı ile test (agent için gerekli değil)
sudo -u www-data fail2ban-client ping
```

### Agent Çalışmıyor

```bash
# Manuel çalıştır ve hataları gör
php /opt/fail2ban-agent/agent.php

# PHP error log
tail -f /var/log/fail2ban_agent.log

# Permissions kontrolü
ls -la /opt/fail2ban-agent/
```

## 🔒 Güvenlik

- ✅ Config dosyası sadece root tarafından okunabilir
- ✅ Database şifresi güvenli saklanmalı
- ✅ MySQL bağlantısı SSL ile şifrelenebilir
- ✅ Firewall'da sadece MySQL portuna izin ver

```bash
# Config dosyası izinleri
sudo chmod 600 /opt/fail2ban-agent/agent.conf.php
sudo chown root:root /opt/fail2ban-agent/agent.conf.php
```

## 📈 Performans

Agent çok hafiftir:

- **Memory**: ~10 MB
- **CPU**: Minimal (sadece sync sırasında)
- **Disk**: Sadece log dosyası
- **Network**: Sadece MySQL bağlantısı

5 dakikalık sync süresi:

- 10 jail + 100 banned IP: ~2 saniye
- 20 jail + 500 banned IP: ~5 saniye

## 🆚 Karşılaştırma

| Özellik          | Full Interface | Agent Only |
| ---------------- | -------------- | ---------- |
| Web Interface    | ✅             | ❌         |
| Dashboard        | ✅             | ❌         |
| Manuel Ban/Unban | ✅             | ❌         |
| Data Sync        | ✅             | ✅         |
| Global Ban Apply | ✅             | ✅         |
| Resource Usage   | Yüksek         | Çok Düşük  |
| Setup Complexity | Orta           | Çok Kolay  |

## 💡 Önerilen Kurulum

**Küçük kurulum (2-5 sunucu):**

- 1 sunucuda full interface
- Diğerlerinde agent

**Orta kurulum (5-20 sunucu):**

- 1 merkezi sunucuda full interface (sadece MySQL + Web)
- Tüm fail2ban sunucularında agent

**Büyük kurulum (20+ sunucu):**

- 1 merkezi dashboard sunucu (MySQL + Web)
- 2-3 MySQL replica (yedeklilik için)
- Tüm fail2ban sunucularında agent

## 🚀 Hızlı Start

```bash
# 1. Dosyaları kopyala
cd /path/to/fail2ban/agent/

# 2. Kur
sudo ./install.sh

# 3. Config düzenle
sudo nano /opt/fail2ban-agent/agent.conf.php

# 4. Test
php /opt/fail2ban-agent/agent.php --test

# 5. Cron ekle
sudo crontab -e
# */5 * * * * /usr/bin/php /opt/fail2ban-agent/agent.php >> /var/log/fail2ban_agent.log 2>&1

# 6. İzle
tail -f /var/log/fail2ban_agent.log
```

## 📞 Destek

Ana proje README.md dosyasına bakın veya GitHub Issues kullanın.
