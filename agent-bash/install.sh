#!/bin/bash
#
# Fail2Ban Bash Agent Installation Script
#
# This script installs the lightweight Fail2Ban bash agent on a remote server
# Usage: sudo ./install.sh
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}Fail2Ban Bash Agent Installer${NC}"
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

# Check if curl is installed
if ! command -v curl &> /dev/null; then
    echo -e "${YELLOW}curl not found, installing...${NC}"
    apt-get update
    apt-get install -y curl
fi

# Installation directory
INSTALL_DIR="/opt/fail2ban-agent-bash"

echo -e "${GREEN}Installing agent to: $INSTALL_DIR${NC}"

# Create installation directory
mkdir -p $INSTALL_DIR

# Copy agent files
echo "Copying agent files..."
cp agent.sh $INSTALL_DIR/
cp agent.conf.example.sh $INSTALL_DIR/

# Check if config already exists
if [ ! -f "$INSTALL_DIR/agent.conf.sh" ]; then
    cp agent.conf.example.sh $INSTALL_DIR/agent.conf.sh
    echo -e "${YELLOW}Configuration file created at: $INSTALL_DIR/agent.conf.sh${NC}"
    echo -e "${YELLOW}IMPORTANT: You must edit this file before running the agent!${NC}"
fi

# Make agent executable
chmod +x $INSTALL_DIR/agent.sh

# Secure configuration file
chmod 600 $INSTALL_DIR/agent.conf.sh
chown root:root $INSTALL_DIR/agent.conf.sh

# Create log file
touch /var/log/fail2ban_agent_bash.log
chmod 644 /var/log/fail2ban_agent_bash.log

echo ""
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}Installation Complete!${NC}"
echo -e "${GREEN}================================${NC}"
echo ""
echo "Next steps:"
echo ""
echo "1. Edit configuration:"
echo "   nano $INSTALL_DIR/agent.conf.sh"
echo ""
echo "   Required settings:"
echo "   - SERVER_NAME (unique identifier)"
echo "   - SERVER_IP (this server's IP)"
echo "   - SYNC_URL (https://your-server.com/sync.php)"
echo "   - API_KEY (generate with: openssl rand -hex 32)"
echo ""
echo "2. Test the agent:"
echo "   $INSTALL_DIR/agent.sh --test"
echo ""
echo "3. Manual sync test:"
echo "   $INSTALL_DIR/agent.sh"
echo ""
echo "4. Add to crontab for automatic sync:"
echo "   crontab -e"
echo ""
echo "   Add this line:"
echo "   # Sync fail2ban data every 5 minutes"
echo "   */5 * * * * $INSTALL_DIR/agent.sh >> /var/log/fail2ban_agent_bash.log 2>&1"
echo ""
echo "5. Monitor logs:"
echo "   tail -f /var/log/fail2ban_agent_bash.log"
echo ""
echo -e "${YELLOW}IMPORTANT: Configure unique SERVER_NAME for each server!${NC}"
echo -e "${YELLOW}IMPORTANT: Keep API_KEY secret and secure!${NC}"
echo ""
