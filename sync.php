<?php

/**
 * Sync script: Synchronizes local fail2ban data with central MySQL database
 *
 * Usage:
 *   php sync.php                    # Sync current server
 *   php sync.php --server=web-1     # Sync specific server
 *   php sync.php --apply-global     # Apply global bans to local fail2ban
 *
 * Add to cron for automatic sync:
 *   5 * * * * /usr/bin/php /path/to/sync.php >> /var/log/fail2ban_sync.log 2>&1
 */

require_once('config.inc.php');
require_once('engine.inc.php');
require_once('db.inc.php');

// Check if centralized database is enabled
if (!isset($config['use_central_db']) || $config['use_central_db'] === false) {
    die("Error: Centralized database is not enabled. Set \$config['use_central_db'] = true in config.inc.php\n");
}

// Configuration
$sync_config = array(
    'server_name' => $config['server_name'] ?? gethostname(),
    'server_ip' => $config['server_ip'] ?? '127.0.0.1',
    'sync_to_db' => true,        // Push local bans to database
    'apply_global' => true,      // Apply global bans from database
    'log_actions' => true,       // Log all sync actions
);

// Parse command line arguments
$options = getopt('', array('server:', 'apply-global', 'help'));

if (isset($options['help'])) {
    echo "Usage: php sync.php [options]\n";
    echo "Options:\n";
    echo "  --server=NAME     Set server name\n";
    echo "  --apply-global    Apply global bans to local fail2ban\n";
    echo "  --help            Show this help\n";
    exit(0);
}

if (isset($options['server'])) {
    $sync_config['server_name'] = $options['server'];
}

if (isset($options['apply-global'])) {
    $sync_config['apply_global'] = true;
    $sync_config['sync_to_db'] = false;
}

// Get or create server ID
$server_id = get_server_id($sync_config['server_name'], $sync_config['server_ip']);
if (!$server_id) {
    die("Error: Could not get server ID. Check database connection.\n");
}

echo "[" . date('Y-m-d H:i:s') . "] Starting sync for server: {$sync_config['server_name']} (ID: $server_id)\n";

// Check fail2ban socket
$socket_check = check_socket();
if ($socket_check != 'OK') {
    die("Error: Fail2ban not accessible: $socket_check\n");
}

/**
 * Sync local bans to database
 */
if ($sync_config['sync_to_db']) {
    echo "[" . date('Y-m-d H:i:s') . "] Syncing local bans to database...\n";

    $jails = list_jails();
    if (empty($jails)) {
        echo "[" . date('Y-m-d H:i:s') . "] No jails found\n";
    } else {
        foreach ($jails as $jail_name => $value) {
            echo "[" . date('Y-m-d H:i:s') . "] Processing jail: $jail_name\n";

            // Get jail info
            $jail_info_array = jail_info($jail_name);
            $jail_info = array();
            foreach ($jail_info_array as $info) {
                if (preg_match('/findtime:\s*(\d+)/', $info, $matches)) {
                    $jail_info['findtime'] = intval($matches[1]);
                }
                if (preg_match('/bantime:\s*(\d+)/', $info, $matches)) {
                    $jail_info['bantime'] = intval($matches[1]);
                }
                if (preg_match('/maxretry:\s*(\d+)/', $info, $matches)) {
                    $jail_info['maxretry'] = intval($matches[1]);
                }
            }

            // Get or create jail ID
            $jail_id = get_jail_id($server_id, $jail_name, $jail_info);
            if (!$jail_id) {
                echo "[" . date('Y-m-d H:i:s') . "] Error: Could not get jail ID for $jail_name\n";
                continue;
            }

            // Get banned IPs
            $banned_ips = list_banned($jail_name);
            if (!is_array($banned_ips)) {
                echo "[" . date('Y-m-d H:i:s') . "] No banned IPs in $jail_name\n";
                continue;
            }

            echo "[" . date('Y-m-d H:i:s') . "] Found " . count($banned_ips) . " banned IPs in $jail_name\n";

            foreach ($banned_ips as $ban_entry) {
                // Parse IP from entry (format: "ip.add.re.ss (hostname)")
                $ip = trim(strstr($ban_entry, '(', true) ?: $ban_entry);
                $hostname = '';
                if (preg_match('/\((.*?)\)/', $ban_entry, $matches)) {
                    $hostname = $matches[1];
                }

                // Get country (if GeoIP available)
                $country = '';
                if (function_exists('getCountryFromIP')) {
                    $country = getCountryFromIP($ip);
                }

                // Sync to database
                if (db_sync_banned_ip($server_id, $jail_id, $ip, $hostname, $country)) {
                    echo "[" . date('Y-m-d H:i:s') . "]   Synced: $ip\n";
                } else {
                    echo "[" . date('Y-m-d H:i:s') . "]   Error syncing: $ip\n";
                }
            }
        }
    }
}

/**
 * Apply global bans to local fail2ban
 */
if ($sync_config['apply_global']) {
    echo "[" . date('Y-m-d H:i:s') . "] Applying global bans...\n";

    $global_bans = db_get_global_bans();
    if (empty($global_bans)) {
        echo "[" . date('Y-m-d H:i:s') . "] No global bans found\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Found " . count($global_bans) . " global bans\n";

        // Get all jails to apply bans
        $jails = list_jails();
        if (empty($jails)) {
            echo "[" . date('Y-m-d H:i:s') . "] No jails found to apply global bans\n";
        } else {
            foreach ($global_bans as $ban) {
                $ip = $ban['ip_address'];
                echo "[" . date('Y-m-d H:i:s') . "] Applying global ban for: $ip (Reason: {$ban['reason']})\n";

                // Apply to all jails (or specific jails if configured)
                foreach ($jails as $jail_name => $value) {
                    $result = ban_ip($jail_name, $ip);
                    if ($result == 'OK') {
                        echo "[" . date('Y-m-d H:i:s') . "]   Applied to jail: $jail_name\n";
                    } else {
                        // IP might already be banned, which is OK
                        if (strpos($result, 'already') === false) {
                            echo "[" . date('Y-m-d H:i:s') . "]   Warning: $result\n";
                        }
                    }
                }
            }
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Sync completed successfully\n";
