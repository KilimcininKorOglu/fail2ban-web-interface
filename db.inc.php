<?php
/**
 * Database connection and helper functions for centralized fail2ban system
 */

/**
 * Get PDO database connection
 */
function get_db_connection() {
    global $db_config, $config;

    // Check if centralized database is enabled
    if (isset($config['use_central_db']) && $config['use_central_db'] === false) {
        return null;
    }

    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            $db_config['host'],
            $db_config['port'],
            $db_config['database'],
            $db_config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $db_config['username'], $db_config['password'], $options);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Get or create server ID
 */
function get_server_id($server_name, $server_ip = null) {
    $db = get_db_connection();
    if (!$db) return null;

    try {
        // Try to get existing server
        $stmt = $db->prepare("SELECT id FROM servers WHERE server_name = ?");
        $stmt->execute([$server_name]);
        $result = $stmt->fetch();

        if ($result) {
            // Update last_sync
            $stmt = $db->prepare("UPDATE servers SET last_sync = NOW() WHERE id = ?");
            $stmt->execute([$result['id']]);
            return $result['id'];
        }

        // Create new server if IP provided
        if ($server_ip) {
            $stmt = $db->prepare("INSERT INTO servers (server_name, server_ip, last_sync) VALUES (?, ?, NOW())");
            $stmt->execute([$server_name, $server_ip]);
            return $db->lastInsertId();
        }

        return null;
    } catch (PDOException $e) {
        error_log("get_server_id error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get or create jail ID
 */
function get_jail_id($server_id, $jail_name, $jail_info = array()) {
    $db = get_db_connection();
    if (!$db) return null;

    try {
        // Try to get existing jail
        $stmt = $db->prepare("SELECT id FROM jails WHERE server_id = ? AND jail_name = ?");
        $stmt->execute([$server_id, $jail_name]);
        $result = $stmt->fetch();

        if ($result) {
            // Update jail info if provided
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

        // Create new jail
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
        error_log("get_jail_id error: " . $e->getMessage());
        return null;
    }
}

/**
 * Sync banned IP to database
 */
function db_sync_banned_ip($server_id, $jail_id, $ip_address, $hostname = null, $country = null) {
    $db = get_db_connection();
    if (!$db) return false;

    try {
        // Check if IP already banned for this server/jail
        $stmt = $db->prepare("
            SELECT id, ban_count FROM banned_ips
            WHERE server_id = ? AND jail_id = ? AND ip_address = ? AND is_active = 1
        ");
        $stmt->execute([$server_id, $jail_id, $ip_address]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update ban count and last attempt
            $stmt = $db->prepare("
                UPDATE banned_ips
                SET ban_count = ban_count + 1, last_attempt = NOW(), hostname = ?, country = ?
                WHERE id = ?
            ");
            $stmt->execute([$hostname, $country, $existing['id']]);
        } else {
            // Insert new ban
            $stmt = $db->prepare("
                INSERT INTO banned_ips
                (server_id, jail_id, ip_address, hostname, country, ban_time, is_active, ban_count)
                VALUES (?, ?, ?, ?, ?, NOW(), 1, 1)
            ");
            $stmt->execute([$server_id, $jail_id, $ip_address, $hostname, $country]);
        }

        return true;
    } catch (PDOException $e) {
        error_log("db_sync_banned_ip error: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark IP as unbanned in database
 */
function db_unban_ip($server_id, $jail_id, $ip_address) {
    $db = get_db_connection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("
            UPDATE banned_ips
            SET is_active = 0, unban_time = NOW()
            WHERE server_id = ? AND jail_id = ? AND ip_address = ? AND is_active = 1
        ");
        $stmt->execute([$server_id, $jail_id, $ip_address]);
        return true;
    } catch (PDOException $e) {
        error_log("db_unban_ip error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all banned IPs from database (for a specific server or all servers)
 */
function db_get_banned_ips($server_id = null, $jail_name = null) {
    $db = get_db_connection();
    if (!$db) return array();

    try {
        $sql = "
            SELECT b.*, s.server_name, j.jail_name
            FROM banned_ips b
            JOIN servers s ON b.server_id = s.id
            JOIN jails j ON b.jail_id = j.id
            WHERE b.is_active = 1
        ";
        $params = array();

        if ($server_id) {
            $sql .= " AND b.server_id = ?";
            $params[] = $server_id;
        }

        if ($jail_name) {
            $sql .= " AND j.jail_name = ?";
            $params[] = $jail_name;
        }

        $sql .= " ORDER BY b.ban_time DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("db_get_banned_ips error: " . $e->getMessage());
        return array();
    }
}

/**
 * Get global bans that should be applied to all servers
 */
function db_get_global_bans() {
    $db = get_db_connection();
    if (!$db) return array();

    try {
        $stmt = $db->prepare("
            SELECT * FROM global_bans
            WHERE is_active = 1
            AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY ban_time DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("db_get_global_bans error: " . $e->getMessage());
        return array();
    }
}

/**
 * Add IP to global ban list
 */
function db_add_global_ban($ip_address, $reason, $banned_by, $permanent = false, $expires_at = null) {
    $db = get_db_connection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("
            INSERT INTO global_bans (ip_address, reason, banned_by, permanent, expires_at, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
            reason = VALUES(reason),
            banned_by = VALUES(banned_by),
            permanent = VALUES(permanent),
            expires_at = VALUES(expires_at),
            is_active = 1,
            ban_time = NOW()
        ");
        $stmt->execute([$ip_address, $reason, $banned_by, $permanent ? 1 : 0, $expires_at]);
        return true;
    } catch (PDOException $e) {
        error_log("db_add_global_ban error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log audit action
 */
function db_log_action($server_id, $action_type, $ip_address, $jail_name, $performed_by, $details = null) {
    $db = get_db_connection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("
            INSERT INTO audit_log (server_id, action_type, ip_address, jail_name, performed_by, details)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$server_id, $action_type, $ip_address, $jail_name, $performed_by, $details]);
        return true;
    } catch (PDOException $e) {
        error_log("db_log_action error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all active servers
 */
function db_get_servers() {
    $db = get_db_connection();
    if (!$db) return array();

    try {
        $stmt = $db->prepare("SELECT * FROM servers WHERE is_active = 1 ORDER BY server_name");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("db_get_servers error: " . $e->getMessage());
        return array();
    }
}

/**
 * Get statistics for dashboard
 */
function db_get_statistics($server_id = null, $days = 7) {
    $db = get_db_connection();
    if (!$db) return array();

    try {
        $sql = "
            SELECT
                DATE(ban_time) as date,
                COUNT(*) as total_bans,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM banned_ips
            WHERE ban_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        $params = [$days];

        if ($server_id) {
            $sql .= " AND server_id = ?";
            $params[] = $server_id;
        }

        $sql .= " GROUP BY DATE(ban_time) ORDER BY date DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("db_get_statistics error: " . $e->getMessage());
        return array();
    }
}
