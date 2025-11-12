<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('config.inc.php');
require_once('ratelimit.inc.php');

// Initialize login error message
$login_error = '';

//process logins: native
if (isset($_POST['username']) && isset($_POST['password'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $client_ip = ratelimit_get_client_ip();

    // Check if IP or username is locked out
    $lock_status = ratelimit_is_locked($client_ip, $username);

    if ($lock_status['locked']) {
        // Account or IP is locked out
        $_SESSION['login_error'] = $lock_status['message'];
        $_SESSION['login_error_type'] = 'lockout';
        header("Location: login.php");
        exit;
    }

    // Track authentication attempt
    $auth_success = false;

    foreach ($login['native'] as $v) {
        // Check if password is hashed (starts with $2y$ for bcrypt)
        $isPasswordValid = false;
        if (isset($v['password_hash'])) {
            // New hashed password format
            $isPasswordValid = password_verify($password, $v['password_hash']);
        } elseif (isset($v['password'])) {
            // Legacy plaintext password (for backward compatibility)
            // WARNING: This is insecure and should be migrated to password_hash
            $isPasswordValid = hash_equals($v['password'], $password);
        }

        if ($v['user'] == $username && $isPasswordValid) {
            $auth_success = true;

            // Clear any previous failed attempts
            ratelimit_reset($client_ip, $username);

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['active'] = true;
            $_SESSION['method'] = 'native';
            $_SESSION['user'] = $username;
            $_SESSION['login_time'] = time();
            break;
        }
    }

    if (!$auth_success) {
        // Record failed login attempt
        $lock_status = ratelimit_record_failure($client_ip, $username);

        if ($lock_status['locked']) {
            // Account just got locked
            $_SESSION['login_error'] = $lock_status['message'];
            $_SESSION['login_error_type'] = 'lockout';
        } else {
            // Show generic error with attempts remaining
            $attempts_info = ratelimit_get_attempts($client_ip, $username);
            $remaining = $attempts_info['remaining_attempts'];

            if ($remaining > 0) {
                $_SESSION['login_error'] = "Invalid username or password. $remaining attempt(s) remaining before temporary lockout.";
            } else {
                $_SESSION['login_error'] = "Invalid username or password.";
            }
            $_SESSION['login_error_type'] = 'invalid';
        }

        // Security: Add small delay to slow down brute-force attacks
        usleep(500000); // 0.5 second delay

        header("Location: login.php");
        exit;
    }
}

if (!isset($_SESSION['active']) || $_SESSION['active'] == false) {
    header("Location: login.php");
    exit;
} else {
    //include('fail2ban.php');
    header("Location: fail2ban.php");
    exit;
}
