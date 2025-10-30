#!/bin/bash
#
# Fail2Ban Agent Installation Script
#
# This script installs the lightweight Fail2Ban agent on a remote server
# Usage: sudo ./install.sh
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}Fail2Ban Agent Installer${NC}"
echo -e "${GREEN}================================${NC}"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: This script must be run as root${NC}"
    echo "Please run: sudo ./install.sh"
    exit 1
fi

# Check if fail2ban is installed
if ! command -v fail2ban-client &> /dev/null; then
    echo -e "${RED}Error: fail2ban-client not found${NC}"
    echo "Please install fail2ban first:"
    echo "  sudo apt-get install fail2ban"
    exit 1
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: PHP not found${NC}"
    echo "Installing PHP..."
    apt-get update
    apt-get install -y php-cli php-mysql
fi

# Check PHP MySQL extension
php -m | grep -q pdo_mysql
if [ $? -ne 0 ]; then
    echo -e "${YELLOW}Installing PHP MySQL extension...${NC}"
    apt-get install -y php-mysql
fi

# Installation directory
INSTALL_DIR="/opt/fail2ban-agent"

echo -e "${GREEN}Installing agent to: $INSTALL_DIR${NC}"

# Create installation directory
mkdir -p $INSTALL_DIR

# Copy agent files
echo "Copying agent files..."
cp agent.php $INSTALL_DIR/
cp agent.conf.example.php $INSTALL_DIR/

# Check if config already exists
if [ ! -f "$INSTALL_DIR/agent.conf.php" ]; then
    cp agent.conf.example.php $INSTALL_DIR/agent.conf.php
    echo -e "${YELLOW}Configuration file created at: $INSTALL_DIR/agent.conf.php${NC}"
    echo -e "${YELLOW}IMPORTANT: You must edit this file before running the agent!${NC}"
fi

# Make agent executable
chmod +x $INSTALL_DIR/agent.php

# Create log file
touch /var/log/fail2ban_agent.log
chmod 644 /var/log/fail2ban_agent.log

echo ""
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}Installation Complete!${NC}"
echo -e "${GREEN}================================${NC}"
echo ""
echo "Next steps:"
echo ""
echo "1. Edit configuration:"
echo "   nano $INSTALL_DIR/agent.conf.php"
echo ""
echo "2. Test the agent:"
echo "   php $INSTALL_DIR/agent.php --test"
echo ""
echo "3. Manual sync test:"
echo "   php $INSTALL_DIR/agent.php"
echo ""
echo "4. Add to crontab for automatic sync:"
echo "   crontab -e"
echo ""
echo "   Add these lines:"
echo "   # Sync local bans every 5 minutes"
echo "   */5 * * * * /usr/bin/php $INSTALL_DIR/agent.php >> /var/log/fail2ban_agent.log 2>&1"
echo ""
echo "   # Apply global bans every 10 minutes"
echo "   */10 * * * * /usr/bin/php $INSTALL_DIR/agent.php --apply-global >> /var/log/fail2ban_agent.log 2>&1"
echo ""
echo "5. Monitor logs:"
echo "   tail -f /var/log/fail2ban_agent.log"
echo ""
echo -e "${YELLOW}IMPORTANT: Remember to configure unique server_name for each server!${NC}"
echo ""
