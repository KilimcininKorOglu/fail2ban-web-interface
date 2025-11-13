<?php

/**
 * Fail2Ban Web Interface - Secure JSON-based Configuration Loader
 *
 * SECURITY: This file only READS config.json (does not execute user input)
 * Web server should have READ access to config.json but NOT WRITE access to .php files
 *
 * @version 0.4 (2025)
 */

// Load configuration from JSON file
$config_file = __DIR__ . '/config.json';

if (!file_exists($config_file)) {
    die('Configuration file not found. Please copy config.example.json to config.json');
}

$json_content = file_get_contents($config_file);
if ($json_content === false) {
    die('Failed to read configuration file. Check file permissions.');
}

$json_config = json_decode($json_content, true);
if ($json_config === null) {
    die('Invalid JSON in configuration file: ' . json_last_error_msg());
}

// Initialize configuration arrays
$config = [];
$login = [];
$f2b = [];
$db_config = [];

// Environment configuration
$config['environment'] = $json_config['environment'] ?? 'production';

if ($config['environment'] === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', '/var/log/fail2ban_web_errors.log');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL & ~E_NOTICE);
}

// Application title
$config['title'] = $json_config['title'] ?? 'Fail2Ban Dashboard';

// Server identification
$config['server_name'] = $json_config['server']['name'] ?? 'my-server';
$config['server_ip'] = $json_config['server']['ip'] ?? '127.0.0.1';

// Central database configuration
$config['use_central_db'] = $json_config['database']['enabled'] ?? false;

if ($config['use_central_db']) {
    $db_config = [
        'host' => $json_config['database']['host'] ?? 'localhost',
        'port' => $json_config['database']['port'] ?? 3306,
        'database' => $json_config['database']['name'] ?? 'fail2ban_central',
        'username' => $json_config['database']['username'] ?? 'fail2ban_user',
        'password' => $json_config['database']['password'] ?? '',
        'charset' => $json_config['database']['charset'] ?? 'utf8mb4'
    ];
} else {
    // Empty config for compatibility
    $db_config = [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'fail2ban_central',
        'username' => 'fail2ban_user',
        'password' => '',
        'charset' => 'utf8mb4'
    ];
}

// Authentication configuration
if (isset($json_config['authentication']['users']) && is_array($json_config['authentication']['users'])) {
    $login['native'] = [];
    foreach ($json_config['authentication']['users'] as $user) {
        if (isset($user['username'])) {
            $login_entry = ['user' => $user['username']];

            // Prefer password_hash over plain password
            if (isset($user['password_hash']) && !empty($user['password_hash'])) {
                $login_entry['password_hash'] = $user['password_hash'];
            } elseif (isset($user['password'])) {
                // Legacy plaintext support (insecure)
                $login_entry['password'] = $user['password'];
            }

            $login['native'][] = $login_entry;
        }
    }
} else {
    // Default admin user if none configured
    $login['native'] = [
        ['user' => 'admin', 'password' => 'changeme']
    ];
}

// Fail2Ban settings
$f2b['socket'] = $json_config['fail2ban']['socket'] ?? '/var/run/fail2ban/fail2ban.sock';
$f2b['use_socket_check'] = $json_config['fail2ban']['use_socket_check'] ?? false;
$f2b['usedns'] = $json_config['fail2ban']['usedns'] ?? false;
$f2b['dns_async'] = $json_config['fail2ban']['dns_async'] ?? true;
$f2b['dns_timeout'] = $json_config['fail2ban']['dns_timeout'] ?? 2;
$f2b['dns_cache_ttl'] = $json_config['fail2ban']['dns_cache_ttl'] ?? 86400;

// Security settings (rate limiting, session management)
$config['security'] = $json_config['security'] ?? [
    'rate_limit' => [
        'max_attempts' => 5,
        'lockout_time' => 900,
        'window' => 1800
    ],
    'session' => [
        'timeout' => 1800,
        'regeneration_interval' => 600
    ]
];
$f2b['noempt'] = $json_config['fail2ban']['noempt'] ?? true;
$f2b['jainfo'] = $json_config['fail2ban']['jainfo'] ?? true;

// Version
$f2b['version'] = $json_config['version'] ?? '0.4 (2025)';
