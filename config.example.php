<?php
/**
 * Example configuration file for Fail2Ban Web Interface
 *
 * Copy this file to config.inc.php and customize for your environment
 */

// Environment configuration
// Set to 'production' when deploying to live server
$config['environment'] = 'development'; // Options: 'development', 'production'

// Error reporting based on environment
if ($config['environment'] === 'production') {
    // Production: Hide errors from users, log to file
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', '/var/log/fail2ban_web_errors.log');
    error_reporting(E_ALL);
} else {
    // Development: Show errors for debugging
    ini_set('display_errors', 1);
    error_reporting(E_ALL & ~E_NOTICE);
}

// Application title
$config['title'] = 'Fail2Ban Dashboard';

// =============================================================================
// SINGLE SERVER MODE (Default)
// =============================================================================
// For single server, keep use_central_db = false
// This is the simplest setup - just manage local fail2ban

$config['server_name'] = 'my-server';
$config['server_ip'] = '127.0.0.1';
$config['use_central_db'] = false;

// =============================================================================
// MULTI-SERVER MODE (Optional)
// =============================================================================
// For multiple independent fail2ban servers with centralized monitoring:
// 1. Setup central MySQL server (see SETUP.md)
// 2. Import database.sql
// 3. Set use_central_db = true on ALL servers
// 4. Configure unique server_name for EACH server
// 5. Configure db_config to point to central MySQL

/*
// Example multi-server configuration:

// Server identification (MUST BE UNIQUE for each server)
$config['server_name'] = 'web-server-1';    // Change: web-server-1, mail-server-1, db-server-1, etc.
$config['server_ip'] = '192.168.1.10';      // This server's IP address

// Enable centralized database
$config['use_central_db'] = true;

// Centralized MySQL database configuration
$db_config = array(
    'host' => '192.168.1.100',              // Central MySQL server IP
    'port' => 3306,
    'database' => 'fail2ban_central',
    'username' => 'fail2ban_user',
    'password' => 'CHANGE_THIS_PASSWORD',   // Use strong password
    'charset' => 'utf8mb4'
);
*/

// For single server mode, define empty db_config (required by db.inc.php)
if (!isset($db_config)) {
    $db_config = array(
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'fail2ban_central',
        'username' => 'fail2ban_user',
        'password' => 'your_secure_password',
        'charset' => 'utf8mb4'
    );
}

// =============================================================================
// AUTHENTICATION
// =============================================================================

// SECURITY WARNING: Use 'password_hash' instead of 'password' for production!
// Generate hash: php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
//
// Example with hashed password (RECOMMENDED):
// $login['native'] = array(
//     array('user' => 'admin', 'password_hash' => '$2y$10$...')
// );
//
// Legacy plaintext format (INSECURE - for backward compatibility only):
// $login['native'] = array(
//     array('user' => 'user1', 'password' => 'password1')
// );

// TODO: Replace this with hashed password before production deployment
$login['native'] = array(
    array(
        'user' => 'admin',
        'password' => 'changeme'  // INSECURE: Change to password_hash
    )
);

// =============================================================================
// FAIL2BAN SETTINGS
// =============================================================================

// Socket path options (choose based on your server configuration):
// Option 1: Default system location (requires open_basedir modification)
// $f2b['socket'] = '/var/run/fail2ban/fail2ban.sock';
//
// Option 2: Symlink in allowed path (recommended for shared hosting)
// $f2b['socket'] = '/var/www/vhosts/keremgok.tr/vcsim.keremgok.tr/fail2ban.sock';
//
// Option 3: Use fail2ban-client without direct socket access
$f2b['socket'] = '/var/run/fail2ban/fail2ban.sock'; # path to the Fail2Ban socket file
$f2b['use_socket_check'] = false; # Set to false to skip socket file check (use fail2ban-client directly)

$f2b['usedns'] = false; # show hostnames per banned IP [true|false] - Set to false for faster page load
$f2b['noempt'] = true; # do not show jails without banned clients [true|false]
$f2b['jainfo'] = true; # show jail information in table headers [true|false]

// =============================================================================
// DO NOT EDIT BELOW THIS LINE
// =============================================================================
$f2b['version'] = '0.3 (2025)';
