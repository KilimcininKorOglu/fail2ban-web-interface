#!/bin/bash
#
# Fail2Ban Bash Agent Configuration
#
# Copy this file to agent.conf.sh and configure for your server
# chmod 600 agent.conf.sh (important for security!)
#

# Server identification (MUST BE UNIQUE for each server)
SERVER_NAME="web-server-1"    # Change: web-server-1, mail-server-1, db-server-1
SERVER_IP="192.168.1.10"       # This server's IP address

# Central sync.php endpoint URL
SYNC_URL="https://fail2ban.example.com/sync.php"

# API Key for authentication (generate a strong random key)
# Generate with: openssl rand -hex 32
API_KEY="your_secret_api_key_here_replace_this_with_random_hex"

# Optional: Custom user agent
USER_AGENT="Fail2Ban-Bash-Agent/1.0"

# Optional: Timeout for curl requests (in seconds)
CURL_TIMEOUT=30

# Optional: Enable verbose logging
DEBUG=0
