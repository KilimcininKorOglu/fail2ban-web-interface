<?php
/**
 * Fail2Ban Agent Configuration
 *
 * Copy this file to agent.conf.php and configure for your server
 */

$agent_config = array(
    // Server identification (MUST BE UNIQUE for each server)
    'server_name' => 'web-server-1',    // Change: web-server-1, mail-server-1, db-server-1
    'server_ip' => '192.168.1.10',      // This server's IP address

    // Central MySQL database connection
    'db' => array(
        'host' => '192.168.1.100',      // Central MySQL server IP
        'port' => 3306,
        'database' => 'fail2ban_central',
        'username' => 'fail2ban_user',
        'password' => 'your_secure_password',
        'charset' => 'utf8mb4'
    ),

    // Optional: Logging
    'log_file' => '/var/log/fail2ban_agent.log',
    'debug' => false
);
