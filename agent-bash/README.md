# Fail2Ban Bash Agent

Lightweight bash-based agent for sending fail2ban data to central sync.php endpoint via HTTP.

## 🎯 Features

- ✅ No PHP required (pure bash + curl)
- ✅ Sends data via HTTP POST to sync.php
- ✅ API key authentication
- ✅ JSON format data transmission
- ✅ Automatic jail discovery
- ✅ IP validation
- ✅ Connection testing
- ✅ Minimal dependencies (bash, curl, fail2ban-client)

## 📦 Installation

### Automatic

```bash
cd agent-bash/
sudo ./install.sh
```

### Manual

```bash
sudo mkdir -p /opt/fail2ban-agent-bash
sudo cp agent.sh /opt/fail2ban-agent-bash/
sudo cp agent.conf.example.sh /opt/fail2ban-agent-bash/agent.conf.sh
sudo chmod +x /opt/fail2ban-agent-bash/agent.sh
sudo chmod 600 /opt/fail2ban-agent-bash/agent.conf.sh
```

## ⚙️ Configuration

Edit `/opt/fail2ban-agent-bash/agent.conf.sh`:

```bash
# Server identification (UNIQUE per server)
SERVER_NAME="web-server-1"
SERVER_IP="192.168.1.10"

# Central sync.php URL
SYNC_URL="https://fail2ban.example.com/sync.php"

# API Key (generate with: openssl rand -hex 32)
API_KEY="your_secret_api_key_here"
```

**Important:** Configure the same API key in `config.inc.php`:

```php
$config['sync_api_key'] = 'your_secret_api_key_here';
```

## 🧪 Testing

```bash
# Test configuration and connectivity
/opt/fail2ban-agent-bash/agent.sh --test

# Manual sync test
/opt/fail2ban-agent-bash/agent.sh
```

## 🚀 Cron Setup

```bash
sudo crontab -e

# Add this line:
*/5 * * * * /opt/fail2ban-agent-bash/agent.sh >> /var/log/fail2ban_agent_bash.log 2>&1
```

## 📊 Monitoring

```bash
# Watch logs
tail -f /var/log/fail2ban_agent_bash.log

# Check recent sync
tail -20 /var/log/fail2ban_agent_bash.log
```

## 🔒 Security

- ✅ API key authentication
- ✅ HTTPS recommended
- ✅ Config file chmod 600
- ✅ IP validation before sending
- ⚠️ Keep API_KEY secret!

## 🆚 Comparison: Bash Agent vs PHP Agent

| Feature | Bash Agent | PHP Agent |
|---------|------------|-----------|
| **Language** | Bash | PHP |
| **Dependencies** | curl, bash | PHP CLI, php-mysql |
| **Connection** | HTTP to sync.php | Direct MySQL |
| **Setup** | Easier | More complex |
| **Network** | HTTP/HTTPS | MySQL protocol |
| **Firewall** | Port 80/443 | Port 3306 |
| **Security** | API key | MySQL credentials |

## 📁 File Structure

```
agent-bash/
├── agent.sh                   # Main script
├── agent.conf.example.sh      # Example config
├── install.sh                 # Installation script
└── README.md                  # This file
```

## 🔧 Troubleshooting

### Connection failed

```bash
# Test URL manually
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_api_key" \
  -d '{"action":"ping"}' \
  https://fail2ban.example.com/sync.php
```

### Fail2ban access denied

```bash
# Test fail2ban-client
fail2ban-client ping

# Check socket permissions
ls -la /var/run/fail2ban/fail2ban.sock
```

## 💡 Tips

- Generate secure API key: `openssl rand -hex 32`
- Use HTTPS for production
- Monitor logs regularly
- Unique SERVER_NAME per server
- Test before cron setup
