<?php
/**
 * AJAX Hostname Resolver
 *
 * Resolves hostnames asynchronously to prevent blocking the main UI
 * Usage: resolve_hostname.php?ip=192.168.1.1
 */

session_start();

// Security: Check if user is authenticated
if (!isset($_SESSION['active']) || $_SESSION['active'] !== true) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

require_once('config.inc.php');
require_once('engine.inc.php');

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
