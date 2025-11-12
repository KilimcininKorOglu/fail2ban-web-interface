<?php

/**
 * AJAX Hostname Resolver
 *
 * Resolves hostnames asynchronously to prevent blocking the main UI
 * Usage: resolve_hostname.php?ip=192.168.1.1
 */

require_once('session.inc.php');
require_once('config.inc.php');
require_once('engine.inc.php');

// Check authentication and session timeout
if (!check_session_timeout(1800)) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized - Session expired']));
}

// Validate input
if (!isset($_GET['ip']) || empty($_GET['ip'])) {
    http_response_code(400);
    die(json_encode(['error' => 'IP address required']));
}

$ip = trim($_GET['ip']);

// Validate IP format
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid IP address']));
}

// Resolve hostname with caching
$hostname = resolve_hostname($ip);

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'ip' => $ip,
    'hostname' => $hostname,
    'resolved' => ($hostname !== $ip)
]);
