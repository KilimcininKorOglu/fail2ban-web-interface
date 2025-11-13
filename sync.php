<?php

/**
 * Sync script: Synchronizes local fail2ban data with central MySQL database
 *
 * Usage (CLI):
 *   php sync.php                    # Sync current server
 *   php sync.php --server=web-1     # Sync specific server
 *   php sync.php --apply-global     # Apply global bans to local fail2ban
 *
 * Usage (HTTP API):
 *   POST /sync.php
 *   Headers: X-API-Key: your_api_key
 *   Body: {"action":"sync","server_name":"web-1","server_ip":"1.2.3.4","jails":[...]}
 *
 * Add to cron for automatic sync:
 *   5 * * * * /usr/bin/php /path/to/sync.php >> /var/log/fail2ban_sync.log 2>&1
 */

require_once('config.inc.php');
require_once('engine.inc.php');
require_once('db.inc.php');

// HTTP API Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    // Set JSON response header
    header('Content-Type: application/json');

    // Check API key from database
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';

    if (empty($api_key)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Missing API key']);
        exit;
    }

    // Validate API key format (64 hex characters)
    if (!preg_match('/^[a-f0-9]{64}$/i', $api_key)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid API key format']);
        exit;
    }

    // Verify API key exists in database
    try {
        $db = get_db_connection();
        if (!$db) {
            throw new Exception('Database connection failed');
        }

        $stmt = $db->prepare("SELECT id, server_name, is_active FROM servers WHERE api_key = ? LIMIT 1");
        $stmt->execute([$api_key]);
        $server_auth = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$server_auth) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid API key']);
            exit;
        }

        if ($server_auth['is_active'] != 1) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden', 'message' => 'Server is not active']);
            exit;
        }

        // Store authenticated server info for use later
        $authenticated_server_id = $server_auth['id'];
        $authenticated_server_name = $server_auth['server_name'];

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server Error', 'message' => 'Authentication failed: ' . $e->getMessage()]);
        exit;
    }

    // Parse JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }

    $action = $data['action'] ?? '';

    // Handle ping action
    if ($action === 'ping') {
        echo json_encode([
            'status' => 'ok',
            'message' => 'pong',
            'server_id' => $authenticated_server_id,
            'server_name' => $authenticated_server_name,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // Handle sync action
    if ($action === 'sync') {
        $server_name = $data['server_name'] ?? '';
        $server_ip = $data['server_ip'] ?? '';
        $jails = $data['jails'] ?? [];

        if (empty($server_name) || empty($server_ip)) {
            http_response_code(400);
            echo json_encode(['error' => 'Bad Request', 'message' => 'server_name and server_ip are required']);
            exit;
        }

        try {
            // Use authenticated server ID (already verified via API key)
            $server_id = $authenticated_server_id;

            // Update server IP if changed
            $stmt = $db->prepare("UPDATE servers SET server_ip = ?, last_sync = NOW() WHERE id = ?");
            $stmt->execute([$server_ip, $server_id]);

            $total_synced = 0;
            $errors = [];

            foreach ($jails as $jail_data) {
                $jail_name = $jail_data['name'] ?? '';
                $jail_info = $jail_data['info'] ?? [];
                $banned_ips = $jail_data['banned_ips'] ?? [];

                if (empty($jail_name)) {
                    continue;
                }

                // Get or create jail ID
                $jail_id = get_jail_id($server_id, $jail_name, $jail_info);
                if (!$jail_id) {
                    $errors[] = "Could not get jail ID for $jail_name";
                    continue;
                }

                // Sync banned IPs
                foreach ($banned_ips as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        if (db_sync_banned_ip($server_id, $jail_id, $ip, '', '')) {
                            $total_synced++;
                        }
                    }
                }
            }

            $response = [
                'status' => 'success',
                'message' => 'Data synced successfully',
                'server_id' => $server_id,
                'server_name' => $server_name,
                'jails_processed' => count($jails),
                'ips_synced' => $total_synced,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }

            http_response_code(200);
            echo json_encode($response);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server Error', 'message' => $e->getMessage()]);
            exit;
        }
    }

    // Unknown action
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'message' => 'Unknown action: ' . $action]);
    exit;
}

// Check if centralized database is enabled (for CLI mode)
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
