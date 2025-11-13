<?php

/**
 * Control Panel - Multi-Server Overview
 *
 * Central dashboard for monitoring all servers in the database
 * Features: server status, ban statistics, global bans, audit log
 */

require_once('session.inc.php');
require_once('config.inc.php');
require_once('csrf.inc.php');

// Check authentication and session timeout
require_authentication(1800, 'index.php');

// Check if central database is enabled
if (!isset($config['use_central_db']) || $config['use_central_db'] === false) {
    die('<div style="padding: 2rem; text-align: center;">
        <h3>Central Database Not Enabled</h3>
        <p>Please enable central database in <a href="admin.php">Admin Panel</a> to use Control Panel.</p>
        <a href="fail2ban.php" class="btn btn-primary">Back to Dashboard</a>
    </div>');
}

require_once('db.inc.php');

$error_message = '';
$success_message = '';

// Get database connection
try {
    $db = get_db_connection();
    if (!$db) {
        throw new Exception('Failed to connect to database');
    }
} catch (Exception $e) {
    die('<div style="padding: 2rem; text-align: center;">
        <h3>Database Connection Failed</h3>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
        <a href="admin.php" class="btn btn-primary">Check Database Settings</a>
    </div>');
}

// Fetch all servers
$servers = [];
try {
    $stmt = $db->query("SELECT * FROM servers ORDER BY server_name");
    $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Failed to fetch servers: ' . $e->getMessage();
}

// Fetch statistics for each server
$server_stats = [];
foreach ($servers as $server) {
    try {
        // Count jails
        $stmt = $db->prepare("SELECT COUNT(*) as jail_count FROM jails WHERE server_id = ?");
        $stmt->execute([$server['id']]);
        $jail_count = $stmt->fetch(PDO::FETCH_ASSOC)['jail_count'];

        // Count active bans
        $stmt = $db->prepare("SELECT COUNT(*) as ban_count FROM banned_ips
                              WHERE server_id = ? AND is_active = 1");
        $stmt->execute([$server['id']]);
        $ban_count = $stmt->fetch(PDO::FETCH_ASSOC)['ban_count'];

        // Count total bans (all time)
        $stmt = $db->prepare("SELECT COUNT(*) as total_bans FROM banned_ips WHERE server_id = ?");
        $stmt->execute([$server['id']]);
        $total_bans = $stmt->fetch(PDO::FETCH_ASSOC)['total_bans'];

        $server_stats[$server['id']] = [
            'jail_count' => $jail_count,
            'ban_count' => $ban_count,
            'total_bans' => $total_bans
        ];
    } catch (Exception $e) {
        $server_stats[$server['id']] = [
            'jail_count' => 0,
            'ban_count' => 0,
            'total_bans' => 0
        ];
    }
}

// Fetch recent bans (last 100)
$recent_bans = [];
try {
    $stmt = $db->query("SELECT b.*, s.server_name, j.jail_name
                        FROM banned_ips b
                        JOIN servers s ON b.server_id = s.id
                        JOIN jails j ON b.jail_id = j.id
                        WHERE b.is_active = 1
                        ORDER BY b.ban_time DESC
                        LIMIT 100");
    $recent_bans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message .= ' Failed to fetch recent bans: ' . $e->getMessage();
}

// Fetch global bans
$global_bans = [];
try {
    $stmt = $db->query("SELECT * FROM global_bans WHERE is_active = 1 ORDER BY ban_time DESC");
    $global_bans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message .= ' Failed to fetch global bans: ' . $e->getMessage();
}

// Fetch top banned IPs
$top_ips = [];
try {
    $stmt = $db->query("SELECT ip_address, COUNT(*) as ban_count,
                        COUNT(DISTINCT server_id) as server_count
                        FROM banned_ips
                        GROUP BY ip_address
                        ORDER BY ban_count DESC
                        LIMIT 20");
    $top_ips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message .= ' Failed to fetch top IPs: ' . $e->getMessage();
}

// Calculate overall statistics
$total_servers = count($servers);
$active_servers = count(array_filter($servers, function ($s) {
    return $s['is_active'] == 1;
}));
$total_active_bans = array_sum(array_column($server_stats, 'ban_count'));
$total_all_time_bans = array_sum(array_column($server_stats, 'total_bans'));
$total_global_bans = count($global_bans);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel - Multi-Server Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #0d1117 0%, #1c2333 100%);
            min-height: 100vh;
            color: #e6edf3;
        }

        .navbar {
            background: rgba(13, 17, 23, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card {
            background: rgba(13, 17, 23, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid rgba(102, 126, 234, 0.3);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .server-card {
            transition: all 0.3s;
            cursor: pointer;
        }

        .server-card:hover {
            transform: translateY(-2px);
            border-color: rgba(102, 126, 234, 0.5) !important;
        }

        .server-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-online {
            background: #28a745;
            box-shadow: 0 0 10px #28a745;
        }

        .status-offline {
            background: #dc3545;
            box-shadow: 0 0 10px #dc3545;
        }

        .status-warning {
            background: #ffc107;
            box-shadow: 0 0 10px #ffc107;
        }

        .table-dark {
            --bs-table-bg: rgba(13, 17, 23, 0.6);
        }

        .badge-custom {
            padding: 0.5em 1em;
            font-size: 0.875rem;
        }

        .tab-content {
            padding-top: 1.5rem;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-hdd-network text-primary"></i>
                <strong>Control Panel</strong>
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="navbar-text">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['user']); ?>
                </span>
                <button class="btn btn-outline-secondary btn-sm" onclick="location.href='fail2ban.php';">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="location.href='admin.php';">
                    <i class="bi bi-gear-fill"></i> Admin
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="location.href='logout.php';">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <!-- Messages -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-server text-primary" style="font-size: 2rem;"></i>
                        <div class="stat-value"><?php echo $total_servers; ?></div>
                        <div class="text-muted">Total Servers</div>
                        <small class="text-success"><?php echo $active_servers; ?> active</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-shield-x text-danger" style="font-size: 2rem;"></i>
                        <div class="stat-value"><?php echo number_format($total_active_bans); ?></div>
                        <div class="text-muted">Active Bans</div>
                        <small class="text-info">Right now</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up text-warning" style="font-size: 2rem;"></i>
                        <div class="stat-value"><?php echo number_format($total_all_time_bans); ?></div>
                        <div class="text-muted">Total Bans</div>
                        <small class="text-info">All time</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-globe text-success" style="font-size: 2rem;"></i>
                        <div class="stat-value"><?php echo $total_global_bans; ?></div>
                        <div class="text-muted">Global Bans</div>
                        <small class="text-info">Network-wide</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="controlTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="servers-tab" data-bs-toggle="tab" data-bs-target="#servers" type="button">
                    <i class="bi bi-server"></i> Servers
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="recent-tab" data-bs-toggle="tab" data-bs-target="#recent" type="button">
                    <i class="bi bi-clock-history"></i> Recent Bans
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="global-tab" data-bs-toggle="tab" data-bs-target="#global" type="button">
                    <i class="bi bi-globe"></i> Global Bans
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="top-tab" data-bs-toggle="tab" data-bs-target="#top" type="button">
                    <i class="bi bi-bar-chart"></i> Top IPs
                </button>
            </li>
        </ul>

        <div class="tab-content" id="controlTabContent">
            <!-- Servers Tab -->
            <div class="tab-pane fade show active" id="servers" role="tabpanel">
                <div class="row">
                    <?php foreach ($servers as $server): ?>
                        <?php
                        $stats = $server_stats[$server['id']];
                        $last_sync = $server['last_sync'] ? strtotime($server['last_sync']) : null;
                        $sync_age = $last_sync ? time() - $last_sync : null;

                        // Determine status
                        if (!$server['is_active']) {
                            $status = 'offline';
                            $status_text = 'Inactive';
                        } elseif (!$last_sync || $sync_age > 1800) { // 30 minutes
                            $status = 'warning';
                            $status_text = 'No recent sync';
                        } else {
                            $status = 'online';
                            $status_text = 'Online';
                        }
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="card server-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="server-status status-<?php echo $status; ?>"></span>
                                        <strong class="ms-2"><?php echo htmlspecialchars($server['server_name']); ?></strong>
                                    </div>
                                    <span class="badge bg-<?php echo $status === 'online' ? 'success' : ($status === 'warning' ? 'warning' : 'danger'); ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2">
                                        <i class="bi bi-geo-alt text-primary"></i>
                                        <strong>IP:</strong> <code><?php echo htmlspecialchars($server['server_ip']); ?></code>
                                    </p>
                                    <p class="mb-2">
                                        <i class="bi bi-clock text-info"></i>
                                        <strong>Last Sync:</strong>
                                        <?php if ($last_sync): ?>
                                            <?php echo date('Y-m-d H:i:s', $last_sync); ?>
                                            <small class="text-muted">(<?php echo floor($sync_age / 60); ?>m ago)</small>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </p>
                                    <hr>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-primary" style="font-size: 1.5rem;"><?php echo $stats['jail_count']; ?></div>
                                            <small class="text-muted">Jails</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-danger" style="font-size: 1.5rem;"><?php echo $stats['ban_count']; ?></div>
                                            <small class="text-muted">Active</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-warning" style="font-size: 1.5rem;"><?php echo $stats['total_bans']; ?></div>
                                            <small class="text-muted">Total</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($servers)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No servers found in database.
                                Servers will appear here automatically when agents sync data.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Bans Tab -->
            <div class="tab-pane fade" id="recent" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Bans (Last 100)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Server</th>
                                        <th>Jail</th>
                                        <th>IP Address</th>
                                        <th>Hostname</th>
                                        <th>Country</th>
                                        <th>Ban Time</th>
                                        <th>Ban Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bans as $ban): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary badge-custom">
                                                    <?php echo htmlspecialchars($ban['server_name']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($ban['jail_name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($ban['ip_address']); ?></code></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $ban['hostname'] ? htmlspecialchars($ban['hostname']) : 'unknown'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($ban['country']): ?>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ban['country']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($ban['ban_time'])); ?></td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $ban['ban_count']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($recent_bans)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No recent bans found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Global Bans Tab -->
            <div class="tab-pane fade" id="global" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-globe"></i> Global Bans (Network-Wide)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>IP Address</th>
                                        <th>Reason</th>
                                        <th>Banned By</th>
                                        <th>Banned At</th>
                                        <th>Type</th>
                                        <th>Expires</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($global_bans as $ban): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($ban['ip_address']); ?></code></td>
                                            <td><?php echo htmlspecialchars($ban['reason']); ?></td>
                                            <td><?php echo htmlspecialchars($ban['banned_by']); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($ban['ban_time'])); ?></td>
                                            <td>
                                                <?php if ($ban['permanent']): ?>
                                                    <span class="badge bg-danger">Permanent</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Temporary</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($ban['permanent']): ?>
                                                    <span class="text-muted">Never</span>
                                                <?php elseif ($ban['expires_at']): ?>
                                                    <?php echo date('Y-m-d H:i', strtotime($ban['expires_at'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($global_bans)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No global bans configured</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top IPs Tab -->
            <div class="tab-pane fade" id="top" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Top 20 Most Banned IPs</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>IP Address</th>
                                        <th>Total Bans</th>
                                        <th>Affected Servers</th>
                                        <th>Severity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_ips as $index => $ip): ?>
                                        <?php
                                        $severity = $ip['ban_count'] >= 50 ? 'danger' : ($ip['ban_count'] >= 20 ? 'warning' : 'info');
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><code class="text-<?php echo $severity; ?>"><?php echo htmlspecialchars($ip['ip_address']); ?></code></td>
                                            <td>
                                                <span class="badge bg-<?php echo $severity; ?> badge-custom">
                                                    <?php echo number_format($ip['ban_count']); ?> bans
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo $ip['server_count']; ?> servers
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($ip['ban_count'] >= 50): ?>
                                                    <span class="badge bg-danger">High Risk</span>
                                                <?php elseif ($ip['ban_count'] >= 20): ?>
                                                    <span class="badge bg-warning">Medium Risk</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Low Risk</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($top_ips)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No ban data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 mb-3">
            <small class="text-muted">
                <i class="bi bi-clock"></i> Last updated: <?php echo date("Y-m-d H:i:s"); ?> |
                <i class="bi bi-hdd-network"></i> Central Database Control Panel
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>

</html>