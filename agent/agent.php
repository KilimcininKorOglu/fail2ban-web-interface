#!/usr/bin/env php
<?php
/**
 * Fail2Ban Agent - Lightweight data collector
 *
 * This script runs on each fail2ban server and sends data to central database.
 * It's much lighter than the full web interface.
 *
 * Usage:
 *   php agent.php                    # Sync data once
 *   php agent.php --apply-global     # Apply global bans
 *   php agent.php --daemon           # Run as daemon (not implemented yet)
 *
 * Cron setup:
 *   5 * * * * /usr/bin/php /opt/fail2ban-agent/agent.php >> /var/log/fail2ban_agent.log 2>&1
 */

// Load configuration
$config_file = __DIR__ . '/agent.conf.php';
if (!file_exists($config_file)) {
    die("Error: Configuration file not found: $config_file\n");
}

require_once($config_file);

// Validate configuration
if (!isset($agent_config['server_name']) || empty($agent_config['server_name'])) {
    die("Error: server_name not configured in agent.conf.php\n");
}

if (!isset($agent_config['db']) || empty($agent_config['db']['host'])) {
    die("Error: Database configuration missing in agent.conf.php\n");
}

// Parse command line arguments
$options = getopt('', array('apply-global', 'help', 'test'));

if (isset($options['help'])) {
    echo "Fail2Ban Agent - Lightweight data collector\n\n";
    echo "Usage: php agent.php [options]\n\n";
    echo "Options:\n";
    echo "  --apply-global    Apply global bans from database to local fail2ban\n";
    echo "  --test            Test database connection and fail2ban access\n";
    echo "  --help            Show this help\n\n";
    exit(0);
}

// Test mode
if (isset($options['test'])) {
    echo "Testing configuration...\n";
    echo "Server: {$agent_config['server_name']}\n";
    echo "Database: {$agent_config['db']['host']}/{$agent_config['db']['database']}\n";

    // Test DB connection
    try {
        $db = get_db_connection($agent_config);
        echo "✓ Database connection: OK\n";
    } catch (Exception $e) {
        echo "✗ Database connection: FAILED - " . $e->getMessage() . "\n";
        exit(1);
    }

    // Test fail2ban access
    $test = @exec('fail2ban-client ping 2>&1', $output, $return_code);
    if ($return_code === 0 && strpos($test, 'pong') !== false) {
        echo "✓ Fail2ban access: OK\n";
    } else {
        echo "✗ Fail2ban access: FAILED\n";
        exit(1);
    }

    echo "\nAll tests passed!\n";
    exit(0);
}

/**
 * Get database connection
 */
function get_db_connection($config)
{
    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $config['db']['host'],
        $config['db']['port'],
        $config['db']['database'],
        $config['db']['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    return new PDO($dsn, $config['db']['username'], $config['db']['password'], $options);
}

/**
 * Get or create server ID
 */
function get_server_id($db, $server_name, $server_ip)
{
    try {
        $stmt = $db->prepare("SELECT id FROM servers WHERE server_name = ?");
        $stmt->execute([$server_name]);
        $result = $stmt->fetch();

        if ($result) {
            $stmt = $db->prepare("UPDATE servers SET last_sync = NOW(), server_ip = ? WHERE id = ?");
            $stmt->execute([$server_ip, $result['id']]);
            return $result['id'];
        }

        $stmt = $db->prepare("INSERT INTO servers (server_name, server_ip, last_sync) VALUES (?, ?, NOW())");
        $stmt->execute([$server_name, $server_ip]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        log_message("ERROR: get_server_id failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Get or create jail ID
 */
function get_jail_id($db, $server_id, $jail_name, $jail_info = array())
{
    try {
        $stmt = $db->prepare("SELECT id FROM jails WHERE server_id = ? AND jail_name = ?");
        $stmt->execute([$server_id, $jail_name]);
        $result = $stmt->fetch();

        if ($result) {
            if (!empty($jail_info)) {
                $stmt = $db->prepare("
                    UPDATE jails
                    SET findtime = ?, bantime = ?, maxretry = ?, last_sync = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $jail_info['findtime'] ?? 600,
                    $jail_info['bantime'] ?? 3600,
                    $jail_info['maxretry'] ?? 5,
                    $result['id']
                ]);
            }
            return $result['id'];
        }

        $stmt = $db->prepare("
            INSERT INTO jails (server_id, jail_name, findtime, bantime, maxretry, last_sync)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $server_id,
            $jail_name,
            $jail_info['findtime'] ?? 600,
            $jail_info['bantime'] ?? 3600,
            $jail_info['maxretry'] ?? 5
        ]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        log_message("ERROR: get_jail_id failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Sync banned IP to database
 */
function sync_banned_ip($db, $server_id, $jail_id, $ip_address)
{
    try {
        $stmt = $db->prepare("
            SELECT id, ban_count FROM banned_ips
            WHERE server_id = ? AND jail_id = ? AND ip_address = ? AND is_active = 1
        ");
        $stmt->execute([$server_id, $jail_id, $ip_address]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE banned_ips
                SET ban_count = ban_count + 1, last_attempt = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$existing['id']]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO banned_ips
                (server_id, jail_id, ip_address, ban_time, is_active, ban_count)
                VALUES (?, ?, ?, NOW(), 1, 1)
            ");
            $stmt->execute([$server_id, $jail_id, $ip_address]);
        }

        return true;
    } catch (PDOException $e) {
        log_message("ERROR: sync_banned_ip failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get list of jails
 */
function list_jails()
{
    $jails = array();
    $erg = @exec('fail2ban-client status | grep "Jail list:" | sed "s/ //g" | awk \'{split($2,a,","); for(i in a) print a[i]}\'', $output);

    if (is_array($output)) {
        foreach ($output as $jail) {
            if (!empty($jail)) {
                $jails[] = trim($jail);
            }
        }
    }

    return $jails;
}

/**
 * Get banned IPs for a jail
 */
function list_banned($jail)
{
    $banned = array();
    $jail_escaped = escapeshellarg($jail);
    $erg = @exec("fail2ban-client status $jail_escaped | grep \"Banned IP list:\" | awk -F ':' '{print\$2}' | awk '{\$1=\$1;print}'", $output);

    if (!empty($erg)) {
        $ips = explode(' ', trim($erg));
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                $banned[] = $ip;
            }
        }
    }

    return $banned;
}

/**
 * Get jail info
 */
function jail_info($jail)
{
    $info = array();
    $jail_escaped = escapeshellarg($jail);

    $findtime = @exec("fail2ban-client get $jail_escaped findtime 2>/dev/null");
    $bantime = @exec("fail2ban-client get $jail_escaped bantime 2>/dev/null");
    $maxretry = @exec("fail2ban-client get $jail_escaped maxretry 2>/dev/null");

    if ($findtime !== false && is_numeric($findtime)) $info['findtime'] = intval($findtime);
    if ($bantime !== false && is_numeric($bantime)) $info['bantime'] = intval($bantime);
    if ($maxretry !== false && is_numeric($maxretry)) $info['maxretry'] = intval($maxretry);

    return $info;
}

/**
 * Get global bans from database
 */
function get_global_bans($db)
{
    try {
        $stmt = $db->prepare("
            SELECT ip_address, reason FROM global_bans
            WHERE is_active = 1
            AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY ban_time DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_message("ERROR: get_global_bans failed: " . $e->getMessage());
        return array();
    }
}

/**
 * Ban IP in fail2ban
 */
function ban_ip($jail, $ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }

    $jail_escaped = escapeshellarg($jail);
    $ip_escaped = escapeshellarg($ip);

    @exec("fail2ban-client set $jail_escaped banip $ip_escaped 2>&1", $output, $return_code);

    return $return_code === 0;
}

/**
 * Log message
 */
function log_message($message)
{
    echo "[" . date('Y-m-d H:i:s') . "] $message\n";
}

// Main execution
try {
    log_message("Starting Fail2Ban Agent: {$agent_config['server_name']}");

    // Connect to database
    $db = get_db_connection($agent_config);
    log_message("Database connected");

    // Get or create server ID
    $server_id = get_server_id($db, $agent_config['server_name'], $agent_config['server_ip']);
    if (!$server_id) {
        die("Error: Could not get server ID\n");
    }
    log_message("Server ID: $server_id");

    // Apply global bans if requested
    if (isset($options['apply-global'])) {
        log_message("Applying global bans...");
        $global_bans = get_global_bans($db);

        if (!empty($global_bans)) {
            $jails = list_jails();
            log_message("Found " . count($global_bans) . " global bans");

            foreach ($global_bans as $ban) {
                $ip = $ban['ip_address'];
                log_message("Applying global ban for: $ip");

                foreach ($jails as $jail) {
                    if (ban_ip($jail, $ip)) {
                        log_message("  ✓ Applied to jail: $jail");
                    }
                }
            }
        } else {
            log_message("No global bans found");
        }
    }

    // Sync local bans to database (default behavior)
    log_message("Syncing local bans to database...");

    $jails = list_jails();
    if (empty($jails)) {
        log_message("No jails found");
    } else {
        log_message("Found " . count($jails) . " jails");

        $total_synced = 0;
        foreach ($jails as $jail) {
            log_message("Processing jail: $jail");

            // Get jail info
            $jail_info = jail_info($jail);

            // Get or create jail ID
            $jail_id = get_jail_id($db, $server_id, $jail, $jail_info);
            if (!$jail_id) {
                log_message("  ERROR: Could not get jail ID");
                continue;
            }

            // Get banned IPs
            $banned_ips = list_banned($jail);
            log_message("  Found " . count($banned_ips) . " banned IPs");

            foreach ($banned_ips as $ip) {
                if (sync_banned_ip($db, $server_id, $jail_id, $ip)) {
                    $total_synced++;
                }
            }
        }

        log_message("Total IPs synced: $total_synced");
    }

    log_message("Sync completed successfully");
} catch (Exception $e) {
    log_message("FATAL ERROR: " . $e->getMessage());
    exit(1);
}
