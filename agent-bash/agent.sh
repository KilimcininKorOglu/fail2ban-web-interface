#!/bin/bash
#
# Fail2Ban Bash Agent - Lightweight data collector
#
# This script runs on each fail2ban server and sends data to central sync.php endpoint via HTTP.
# It's much lighter than the PHP agent and doesn't require PHP installation.
#
# Usage:
#   ./agent.sh                    # Sync data once
#   ./agent.sh --apply-global     # Apply global bans (not implemented yet)
#   ./agent.sh --test             # Test configuration
#
# Cron setup:
#   */5 * * * * /opt/fail2ban-agent-bash/agent.sh >> /var/log/fail2ban_agent.log 2>&1
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Load configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="${SCRIPT_DIR}/agent.conf.sh"

if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${RED}Error: Configuration file not found: $CONFIG_FILE${NC}"
    echo "Please copy agent.conf.example.sh to agent.conf.sh and configure it"
    exit 1
fi

source "$CONFIG_FILE"

# Validate configuration
if [ -z "$SERVER_NAME" ]; then
    echo -e "${RED}Error: SERVER_NAME not configured in agent.conf.sh${NC}"
    exit 1
fi

if [ -z "$SYNC_URL" ]; then
    echo -e "${RED}Error: SYNC_URL not configured in agent.conf.sh${NC}"
    exit 1
fi

if [ -z "$API_KEY" ]; then
    echo -e "${RED}Error: API_KEY not configured in agent.conf.sh${NC}"
    exit 1
fi

# Parse command line arguments
TEST_MODE=0
APPLY_GLOBAL=0
HELP=0

for arg in "$@"; do
    case $arg in
        --test)
            TEST_MODE=1
            ;;
        --apply-global)
            APPLY_GLOBAL=1
            ;;
        --help)
            HELP=1
            ;;
    esac
done

if [ $HELP -eq 1 ]; then
    echo "Fail2Ban Bash Agent - Lightweight data collector"
    echo ""
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  --test            Test configuration and connectivity"
    echo "  --apply-global    Apply global bans from server (not implemented yet)"
    echo "  --help            Show this help"
    exit 0
fi

# Logging function
log_message() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1"
}

# Check if fail2ban-client is available
check_fail2ban() {
    if ! command -v fail2ban-client &> /dev/null; then
        echo -e "${RED}Error: fail2ban-client not found${NC}"
        return 1
    fi

    # Test connection
    if ! fail2ban-client ping &> /dev/null; then
        echo -e "${RED}Error: fail2ban-client ping failed${NC}"
        return 1
    fi

    return 0
}

# Get list of jails
get_jails() {
    fail2ban-client status | grep "Jail list:" | sed 's/.*Jail list:\s*//' | tr ',' '\n' | sed 's/^[ \t]*//;s/[ \t]*$//'
}

# Get banned IPs for a jail
get_banned_ips() {
    local jail=$1
    fail2ban-client status "$jail" 2>/dev/null | grep "Banned IP list:" | sed 's/.*Banned IP list:\s*//' | tr ' ' '\n' | grep -v '^$'
}

# Get jail info (findtime, bantime, maxretry)
get_jail_info() {
    local jail=$1
    local findtime=$(fail2ban-client get "$jail" findtime 2>/dev/null || echo "600")
    local bantime=$(fail2ban-client get "$jail" bantime 2>/dev/null || echo "3600")
    local maxretry=$(fail2ban-client get "$jail" maxretry 2>/dev/null || echo "5")

    echo "{\"findtime\":$findtime,\"bantime\":$bantime,\"maxretry\":$maxretry}"
}

# Test mode
if [ $TEST_MODE -eq 1 ]; then
    log_message "Testing configuration..."
    log_message "Server: $SERVER_NAME"
    log_message "Server IP: $SERVER_IP"
    log_message "Sync URL: $SYNC_URL"
    log_message "API Key: ${API_KEY:0:10}..."
    echo ""

    # Test fail2ban
    log_message "Testing fail2ban connection..."
    if check_fail2ban; then
        echo -e "${GREEN}✓ Fail2ban access: OK${NC}"
    else
        echo -e "${RED}✗ Fail2ban access: FAILED${NC}"
        exit 1
    fi

    # Test HTTP connection
    log_message "Testing HTTP connection to sync.php..."
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -H "X-API-Key: $API_KEY" \
        -d '{"action":"ping"}' \
        "$SYNC_URL" 2>/dev/null || echo "000")

    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "${GREEN}✓ HTTP connection: OK (HTTP $HTTP_CODE)${NC}"
    else
        echo -e "${RED}✗ HTTP connection: FAILED (HTTP $HTTP_CODE)${NC}"
        exit 1
    fi

    echo ""
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
fi

# Main execution
log_message "Starting Fail2Ban Bash Agent: $SERVER_NAME"

# Check fail2ban
if ! check_fail2ban; then
    log_message "ERROR: Fail2ban not accessible"
    exit 1
fi

# Collect jail data
log_message "Collecting jail data..."

JAILS_JSON="["
FIRST_JAIL=1

while IFS= read -r jail; do
    if [ -z "$jail" ]; then
        continue
    fi

    log_message "Processing jail: $jail"

    # Get jail info
    JAIL_INFO=$(get_jail_info "$jail")

    # Get banned IPs
    BANNED_IPS_JSON="["
    FIRST_IP=1

    while IFS= read -r ip; do
        if [ -z "$ip" ]; then
            continue
        fi

        # Validate IP address
        if [[ ! $ip =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            continue
        fi

        if [ $FIRST_IP -eq 0 ]; then
            BANNED_IPS_JSON+=","
        fi
        FIRST_IP=0

        BANNED_IPS_JSON+="\"$ip\""
    done < <(get_banned_ips "$jail")

    BANNED_IPS_JSON+="]"

    # Build jail JSON
    if [ $FIRST_JAIL -eq 0 ]; then
        JAILS_JSON+=","
    fi
    FIRST_JAIL=0

    JAILS_JSON+="{\"name\":\"$jail\",\"info\":$JAIL_INFO,\"banned_ips\":$BANNED_IPS_JSON}"
done < <(get_jails)

JAILS_JSON+="]"

# Build final JSON payload
PAYLOAD=$(cat <<EOF
{
    "action": "sync",
    "server_name": "$SERVER_NAME",
    "server_ip": "$SERVER_IP",
    "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "jails": $JAILS_JSON
}
EOF
)

# Send data to sync.php
log_message "Sending data to sync.php..."

RESPONSE=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "X-API-Key: $API_KEY" \
    -d "$PAYLOAD" \
    "$SYNC_URL" 2>&1)

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" = "200" ]; then
    log_message "✓ Data sent successfully (HTTP $HTTP_CODE)"
    if [ -n "$RESPONSE_BODY" ]; then
        log_message "Response: $RESPONSE_BODY"
    fi
else
    log_message "ERROR: Failed to send data (HTTP $HTTP_CODE)"
    if [ -n "$RESPONSE_BODY" ]; then
        log_message "Error response: $RESPONSE_BODY"
    fi
    exit 1
fi

log_message "Sync completed successfully"
