<?php

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

$config['title'] = 'TEST';

// Server identification (for multi-server setup)
$config['server_name'] = 'web-server-1';  // Unique name for this server
$config['server_ip'] = '192.168.1.10';    // IP address of this server

// Centralized MySQL database configuration
$config['use_central_db'] = false;  // Set to true to enable central database
$db_config = array(
    'host' => 'mysql.example.com',  // Central MySQL server
    'port' => 3306,
    'database' => 'fail2ban_central',
    'username' => 'fail2ban_user',
    'password' => 'your_secure_password',
    'charset' => 'utf8mb4'
);

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
        'user' => 'deneme',
        'password' => 'test'  // INSECURE: Change to password_hash
    )
); 

#####################
# FAIL2BAN SETTINGS #
#####################

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

######################
# DO NOT EDIT PLEASE #
######################
$f2b['version'] = '0.2 (2022)';
