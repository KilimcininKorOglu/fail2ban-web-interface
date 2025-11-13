# Fail2Ban Web Interface

Modern, secure, and high-performance web administration interface for Fail2Ban with optional centralized multi-server management. Features Bootstrap 5 dark mode, JSON-based configuration, comprehensive security protections, and production-ready architecture.

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.2-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Security](https://img.shields.io/badge/security-hardened-green)

## 📑 Table of Contents

- [Features](#-features)
- [Quick Start](#-quick-start)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Multi-Server Setup](#-multi-server-setup)
- [Security](#-security)
- [Performance](#-performance)
- [Maintenance](#-maintenance)
- [Troubleshooting](#-troubleshooting)
- [Architecture](#-architecture)

## ✨ Features

### Core Features

- 🎨 **Modern UI**: Bootstrap 5.3 dark mode with glass-morphism design
- 🔐 **Security Hardened**:
  - CSRF protection (token-based)
  - XSS protection (output encoding)
  - Command injection prevention
  - Bcrypt password hashing
  - Session timeout & regeneration
  - Brute-force protection (rate limiting)
  - Remember me with secure tokens
- ⚡ **High Performance**:
  - Hybrid caching (APCu with file fallback)
  - DNS lookups disabled by default
  - Optimized database queries
- 🌍 **GeoIP Support**: IP geolocation with auto-updater script (optional)
- 📊 **Dashboard**: View all jails and banned IPs in one place
- ⚙️ **JSON Configuration**: Secure, non-executable configuration format

### Advanced Features (Optional)

- 🖥️ **Multi-Server Management**: Centralized MySQL-based monitoring
- 🌐 **Global Ban List**: Ban IPs across all servers simultaneously
- 🔑 **Server Management Panel**: Web-based API key management
- 🔄 **Bidirectional Sync**: Upload bans and download global bans
- 📝 **Audit Logging**: Detailed tracking of all ban/unban actions
- 📈 **Statistics**: Server-based and global analytics
- 🚀 **Lightweight Agent**: Bash or PHP agent options for remote servers
- 🔧 **Admin Panel**: Web-based configuration management

## 🚀 Quick Start

### Single Server Setup (Simplest)

```bash
# 1. Copy files to web directory
sudo cp -r fail2ban/ /var/www/html/
cd /var/www/html/fail2ban/

# 2. Create configuration file
cp config.example.json config.json
nano config.json

# 3. Generate password hash
php -r "echo password_hash('your_password', PASSWORD_DEFAULT) . PHP_EOL;"

# 4. Update config.json with the hash
# Edit authentication.users[0].password_hash

# 5. Set fail2ban socket permissions
sudo chmod 777 /var/run/fail2ban/fail2ban.sock

# 6. Access via browser
# https://your-server/fail2ban/
```

## 📋 Requirements

### Mandatory

- PHP >= 7.2
- fail2ban installed and running
- Apache/Nginx web server
- PHP exec() function enabled
- wget and tar (for GeoIP updates)

### Optional

- php-apcu (for caching)
- php-mysql + MySQL (for multi-server mode)
- composer (for GeoIP support)

## 📦 Installation

### 1. Install PHP Dependencies

```bash
# APCu (recommended for performance)
sudo apt-get install php-apcu

# MySQL (only for multi-server setup)
sudo apt-get install php-mysql

# GeoIP (optional)
composer install
```

### 2. Configure Fail2ban Permissions

Choose one of three options:

```bash
# Option 1: Direct socket access (easiest)
sudo chmod 777 /var/run/fail2ban/fail2ban.sock

# Option 2: Group permissions (more secure)
sudo usermod -a -G fail2ban www-data
sudo chmod 660 /var/run/fail2ban/fail2ban.sock
sudo systemctl restart apache2

# Option 3: Use fail2ban-client (set in config.json)
# "use_socket_check": false
```

### 3. Web Server Security

**⚠️ IMPORTANT:** The application includes `.htaccess` for Apache that automatically protects:

- ✅ Configuration files (config.json, config.example.json)
- ✅ Include files (*.inc.php)
- ✅ Composer files and vendor directory
- ✅ Documentation files (README.md, CLAUDE.md, *.sql)
- ✅ Backup and log files
- ✅ Disables directory listing

**Test protection:**

```bash
# These should return 403 Forbidden:
curl -I https://yourdomain.com/config.json
curl -I https://yourdomain.com/engine.inc.php
```

**Additional IP restrictions (optional):**

Add to `.htaccess`:

```apache
<RequireAll>
    Require ip 192.168.1.0/24
    Require ip 10.0.0.0/8
</RequireAll>
```

## ⚙️ Configuration

### JSON-Based Configuration (config.json)

**⚠️ CRITICAL:** This application uses **non-executable JSON** configuration to prevent RCE vulnerabilities.

```bash
# 1. Copy example config
cp config.example.json config.json

# 2. Generate password hash
php -r "echo password_hash('your_password', PASSWORD_DEFAULT) . PHP_EOL;"

# 3. Edit config.json
nano config.json
```

### Configuration Structure

```json
{
  "environment": "production",
  "title": "Fail2Ban Dashboard",
  "server": {
    "name": "my-server",
    "ip": "127.0.0.1"
  },
  "database": {
    "enabled": false,
    "host": "localhost",
    "port": 3306,
    "name": "fail2ban_central",
    "username": "fail2ban_user",
    "password": "your_secure_password"
  },
  "authentication": {
    "users": [
      {
        "username": "admin",
        "password_hash": "$2y$10$..."
      }
    ]
  },
  "fail2ban": {
    "socket": "/var/run/fail2ban/fail2ban.sock",
    "use_socket_check": false,
    "usedns": false,
    "noempt": true,
    "jainfo": true
  },
  "security": {
    "rate_limit": {
      "max_attempts": 5,
      "lockout_time": 900,
      "window": 1800
    },
    "session": {
      "timeout": 1800,
      "regeneration_interval": 600
    }
  }
}
```

### Key Settings

**Environment:**
- `development`: Shows errors for debugging
- `production`: Logs errors to `/var/log/fail2ban_web_errors.log`

**Security Settings:**
- `rate_limit.max_attempts`: Failed login attempts before lockout (default: 5)
- `rate_limit.lockout_time`: Lockout duration in seconds (default: 900 = 15 min)
- `session.timeout`: Inactivity timeout in seconds (default: 1800 = 30 min)
- `session.regeneration_interval`: Session ID regeneration interval (default: 600 = 10 min)

**Fail2ban Settings:**
- `use_socket_check`: Set to `false` to bypass open_basedir restrictions
- `usedns`: Disable for better performance (recommended: `false`)
- `noempt`: Hide empty jails (recommended: `true`)

## 🖥️ Multi-Server Setup

Manage multiple independent fail2ban servers from a centralized web interface with MySQL database.

### Architecture Overview

```
┌─────────────────────────────────────┐
│     Central Server                   │
│  ┌────────────────────────────────┐  │
│  │   Web Interface (control.php)  │  │ ◄──── Management (Browser)
│  └────────────────────────────────┘  │
│  ┌────────────────────────────────┐  │
│  │      MySQL Database            │  │
│  └────────────────────────────────┘  │
└─────────────────────────────────────┘
              ▲
              │ HTTP/HTTPS (API)
              │
   ┌──────────┼──────────┐
   │          │          │
┌──┴───┐  ┌──┴───┐  ┌──┴───┐
│Web-1 │  │Mail-1│  │ DB-1 │   Remote Servers
├──────┤  ├──────┤  ├──────┤
│ f2b  │  │ f2b  │  │ f2b  │   fail2ban running
│ bash │  │ bash │  │ bash │   bash-agent (cron)
│ agt  │  │ agt  │  │ agt  │
└──────┘  └──────┘  └──────┘
```

### Two Agent Options

#### Option 1: Bash Agent (RECOMMENDED)

**Advantages:**
- ✅ No PHP required on remote servers
- ✅ HTTP/HTTPS communication only (port 80/443)
- ✅ Firewall-friendly (no MySQL port exposure)
- ✅ Minimal dependencies (bash, curl, fail2ban-client)
- ✅ Per-server unique API keys
- ✅ Works through reverse proxies
- ✅ Bidirectional sync (upload + download global bans)

**Installation:**

```bash
# On remote server
cd agent-bash/
sudo ./install.sh

# Configure
sudo nano /opt/fail2ban-agent-bash/agent.conf.sh
# Set: SERVER_NAME, SERVER_IP, SYNC_URL, API_KEY

# Test
/opt/fail2ban-agent-bash/agent.sh --test

# Apply global bans
/opt/fail2ban-agent-bash/agent.sh --apply-global

# Setup cron
sudo crontab -e
# */5 * * * * /opt/fail2ban-agent-bash/agent.sh >> /var/log/fail2ban_agent.log 2>&1
# */10 * * * * /opt/fail2ban-agent-bash/agent.sh --apply-global >> /var/log/fail2ban_agent.log 2>&1
```

[Full Bash Agent Documentation →](agent-bash/README.md)

#### Option 2: PHP Agent (Legacy)

**Use when:**
- Direct database access is required
- PHP is already installed on all servers
- Private network environment

**Installation:**

```bash
# On remote server
cd agent/
sudo ./install.sh

# Configure
sudo nano /opt/fail2ban-agent/agent.conf.php
# Set database credentials

# Test
php /opt/fail2ban-agent/agent.php --test

# Setup cron
sudo crontab -e
# */5 * * * * /usr/bin/php /opt/fail2ban-agent/agent.php >> /var/log/fail2ban_agent.log 2>&1
```

[Full PHP Agent Documentation →](agent/README.md)

### Central Server Setup

#### 1. Setup MySQL Database

```bash
# Connect to MySQL
mysql -u root -p

# Create database and user
CREATE DATABASE fail2ban_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fail2ban_user'@'%' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON fail2ban_central.* TO 'fail2ban_user'@'%';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u fail2ban_user -p fail2ban_central < 001_database.sql
```

#### 2. Enable Remote Access

```bash
# Edit MySQL config
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Change bind-address
bind-address = 0.0.0.0

# Restart MySQL
sudo systemctl restart mysql

# Open firewall (if needed)
sudo ufw allow 3306/tcp
```

#### 3. Configure Web Interface

```bash
# Edit config.json
nano config.json
```

```json
{
  "database": {
    "enabled": true,
    "host": "localhost",
    "port": 3306,
    "name": "fail2ban_central",
    "username": "fail2ban_user",
    "password": "strong_password_here"
  }
}
```

#### 4. Register Remote Servers

1. Navigate to `control.php` in your browser
2. Click "Register New Server"
3. Enter server name, IP, and description
4. System auto-generates 64-character API key
5. Copy API key to remote server's `agent.conf.sh`
6. Server appears in multi-server dashboard

### Features

✅ **Centralized Dashboard**: View all servers and bans in one place
✅ **API Key Management**: Generate, regenerate, and revoke keys via web interface
✅ **Global Ban List**: Ban IPs across all servers simultaneously
✅ **Bidirectional Sync**: Upload local bans and download global bans
✅ **Audit Logging**: Track all ban/unban actions with timestamps
✅ **Statistics**: Server-based and global analytics
✅ **Independent Operation**: Each server runs its own fail2ban instance

## 🔒 Security

### Implemented Protections

✅ **JSON Configuration**: Non-executable format prevents RCE
✅ **CSRF Protection**: Token-based validation on all forms
✅ **XSS Protection**: Output encoding with htmlspecialchars
✅ **Command Injection**: Input validation and escapeshellarg()
✅ **Password Hashing**: Bcrypt with salt
✅ **Session Security**:
  - 30-minute inactivity timeout
  - Automatic ID regeneration every 10 minutes
  - Session fixation prevention
✅ **Brute-Force Protection**:
  - 5 failed attempts → 15-minute lockout
  - IP-based and username-based tracking
✅ **Remember Me**: Secure token-based persistent login (30 days)
✅ **Rate Limiting**: Configurable login attempt limits
✅ **Error Logging**: Environment-based error handling
✅ **File Protection**: .htaccess blocks config and include files

### Production Security Checklist

**Mandatory:**

- [ ] Use HTTPS (Let's Encrypt free)
- [ ] Set `"environment": "production"` in config.json
- [ ] Use bcrypt password hashes (never plaintext)
- [ ] Verify .htaccess is protecting config files
- [ ] Test fail2ban socket permissions
- [ ] Review and adjust rate limiting settings
- [ ] Configure session timeout appropriately

**Recommended:**

- [ ] Add IP restrictions (.htaccess or firewall)
- [ ] Use strong database passwords
- [ ] Enable MySQL SSL/TLS for remote connections
- [ ] Use VPN for server-to-server communication
- [ ] Limit MySQL user privileges to minimum required
- [ ] Monitor error logs regularly
- [ ] Keep PHP and dependencies updated

### MySQL Security

```bash
# Enforce SSL/TLS connections
GRANT ALL PRIVILEGES ON fail2ban_central.* TO 'fail2ban_user'@'%' REQUIRE SSL;

# Restrict to specific IP range
CREATE USER 'fail2ban_user'@'192.168.1.%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON fail2ban_central.* TO 'fail2ban_user'@'192.168.1.%';
```

## 📈 Performance

### Caching System

- **APCu**: In-memory cache (fastest, recommended)
- **File Cache**: Fallback when APCu unavailable
- **TTL**: 30 seconds for jail data
- **GeoIP**: Static array cache per request
- **Session-based keys**: Security through isolation

### Optimizations

- DNS lookups disabled by default
- sleep() delays removed
- Database queries optimized with proper indexing
- Batch operations for multi-server sync
- Minimal resource usage

### Expected Performance

- **First load** (cache miss): 1-3 seconds
- **Cached load**: < 0.5 seconds
- **With APCu**: Near-instant response
- **Dashboard refresh**: Automatic cache invalidation

### Enable APCu (Recommended)

```bash
sudo apt-get install php-apcu
sudo systemctl restart apache2  # or php-fpm

# Verify
php -m | grep apcu
```

## 🔧 Maintenance

### GeoIP Database Updates

Automatic updater script for MaxMind GeoLite2 databases:

```bash
# Set license key (get free key from maxmind.com)
export MAXMIND_LICENSE_KEY="your_license_key"

# Manual update
php update_geoip.php

# Setup automatic daily updates (8:00 AM)
sudo crontab -e
# 0 8 * * * /usr/bin/php /var/www/html/fail2ban/update_geoip.php >> /var/log/geoip_update.log 2>&1
```

Features:
- Downloads GeoLite2-ASN, GeoLite2-City, GeoLite2-Country
- Creates timestamped backups
- Rotates old backups (keeps 3 most recent)
- Comprehensive logging
- Error handling with proper exit codes

### Log Management

```bash
# View sync logs
tail -f /var/log/fail2ban_agent.log

# Setup log rotation
sudo nano /etc/logrotate.d/fail2ban-sync
```

```
/var/log/fail2ban_agent.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
}
```

### Database Maintenance

```sql
-- Clean old inactive bans (90+ days)
DELETE FROM banned_ips
WHERE is_active = 0 AND unban_time < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Clean old audit logs (180+ days)
DELETE FROM audit_log
WHERE action_time < DATE_SUB(NOW(), INTERVAL 180 DAY);

-- Optimize tables
OPTIMIZE TABLE banned_ips;
OPTIMIZE TABLE audit_log;

-- Check database size
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'fail2ban_central'
ORDER BY (data_length + index_length) DESC;
```

### Backup Strategy

```bash
# Manual database backup
mysqldump -u fail2ban_user -p fail2ban_central | gzip > fail2ban_backup_$(date +%Y%m%d).sql.gz

# Automated daily backup (cron at 2:00 AM)
0 2 * * * mysqldump -u fail2ban_user -p'password' fail2ban_central | gzip > /backup/fail2ban_$(date +\%Y\%m\%d).sql.gz

# Retention policy (keep 7 days)
find /backup/fail2ban_*.sql.gz -mtime +7 -delete
```

## 🐛 Troubleshooting

### Socket Permission Denied

**Problem:** `Permission denied to socket`

**Solutions:**

```bash
# Option 1: Full access
sudo chmod 777 /var/run/fail2ban/fail2ban.sock

# Option 2: Group permissions
sudo usermod -a -G fail2ban www-data
sudo chmod 660 /var/run/fail2ban/fail2ban.sock
sudo systemctl restart apache2

# Option 3: Bypass socket check
# Set in config.json: "use_socket_check": false
```

### Database Connection Failed

**Problem:** Cannot connect to MySQL

**Debug steps:**

```bash
# Test connection
mysql -h 192.168.1.100 -u fail2ban_user -p fail2ban_central

# Check firewall
telnet 192.168.1.100 3306

# Check MySQL logs
sudo tail -f /var/log/mysql/error.log

# Verify user permissions
mysql -u root -p
SHOW GRANTS FOR 'fail2ban_user'@'%';
```

### Slow Page Load

**Problem:** Dashboard loads slowly

**Solutions:**

```bash
# Check if cache is working
php -r "
require_once('cache.inc.php');
cache_set('test', 'value', 60);
echo cache_get('test') === 'value' ? 'Cache OK' : 'Cache FAIL';
"

# Install APCu
sudo apt-get install php-apcu
sudo systemctl restart apache2

# Disable DNS lookups (config.json)
# "usedns": false

# Verify temp directory is writable
ls -ld /tmp
```

### Agent Not Syncing

**Problem:** Remote agent not syncing data

**Debug bash agent:**

```bash
# Test configuration
/opt/fail2ban-agent-bash/agent.sh --test

# Manual sync with output
/opt/fail2ban-agent-bash/agent.sh

# Check logs
tail -f /var/log/fail2ban_agent.log

# Test HTTP endpoint
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_api_key" \
  -d '{"action":"ping"}' \
  https://central.example.com/sync.php
```

## 📁 Architecture

### File Structure

```
fail2ban/
├── index.php                # Entry point with authentication
├── login.php                # Bootstrap 5 dark mode login page
├── fail2ban.php             # Main dashboard (local server)
├── control.php              # Multi-server management panel
├── admin.php                # Configuration management panel
├── logout.php               # Session cleanup
├── protected.php            # Example protected page
├── engine.inc.php           # Fail2ban command abstraction
├── cache.inc.php            # Hybrid caching (APCu/File)
├── csrf.inc.php             # CSRF protection library
├── session.inc.php          # Session timeout & security
├── ratelimit.inc.php        # Brute-force protection
├── config.inc.php           # Configuration loader (READ-ONLY)
├── config.json              # JSON configuration (gitignored)
├── config.example.json      # Configuration template
├── db.inc.php               # MySQL database functions
├── sync.php                 # Sync script (HTTP API + CLI)
├── update_geoip.php         # GeoIP database updater
├── 001_database.sql         # MySQL schema
├── README.md                # This file
├── CLAUDE.md                # AI assistant documentation
├── .htaccess                # Apache security rules
├── .gitignore               # Git ignore rules
├── agent/                   # PHP agent (legacy)
│   ├── agent.php
│   ├── agent.conf.php
│   ├── agent.conf.example.php
│   ├── install.sh
│   └── README.md
└── agent-bash/              # Bash agent (recommended)
    ├── agent.sh
    ├── agent.conf.sh
    ├── agent.conf.example.sh
    ├── install.sh
    └── README.md
```

### Database Schema

| Table         | Purpose                                    |
| ------------- | ------------------------------------------ |
| `servers`     | Tracks all fail2ban servers with API keys |
| `jails`       | Tracks jails across all servers            |
| `banned_ips`  | Central repository of all banned IPs       |
| `global_bans` | IPs to ban across all servers              |
| `audit_log`   | Log of all ban/unban actions               |
| `statistics`  | Aggregated daily statistics                |
| `users`       | Web interface users (future feature)       |

See `001_database.sql` for complete schema.

### Key Technologies

- **Backend**: PHP 7.2+, MySQL 5.7+
- **Frontend**: Bootstrap 5.3, Bootstrap Icons 1.11.0
- **Security**: bcrypt, CSRF tokens, session management
- **Performance**: APCu, file caching, query optimization
- **GeoIP**: MaxMind GeoLite2 (optional)
- **Communication**: HTTP API (JSON), MySQL protocol

## 🤝 Contributing

Contributions welcome! Before submitting a pull request:

1. Test your code thoroughly
2. Check for security vulnerabilities
3. Update documentation
4. Follow existing code style
5. Add comments for complex logic

## 📄 License

MIT License - See LICENSE file for details.

## 🙏 Credits

- [Bootstrap 5](https://getbootstrap.com/) - UI framework
- [Bootstrap Icons](https://icons.getbootstrap.com/) - Icon set
- [MaxMind GeoIP2](https://www.maxmind.com/) - IP geolocation
- [Fail2ban](https://www.fail2ban.org/) - Intrusion prevention

## 📞 Support

- **Issues**: GitHub Issues
- **Email**: <kerem@keremgok.com>
- **Documentation**:
  - README.md (this file)
  - CLAUDE.md (AI assistant guide)
  - agent-bash/README.md (bash agent docs)
  - agent/README.md (PHP agent docs)

---

**Note**: This is an independent web interface and is not officially affiliated with the fail2ban project.

**Security Notice**: Always use HTTPS in production. Never expose this interface to the public internet without proper security measures (IP restrictions, VPN, firewall rules).
