<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('config.inc.php');
require_once('session.inc.php');
require_once('ratelimit.inc.php');

// Check remember me token first
if (check_remember_me()) {
    // User was automatically logged in via remember me
    header("Location: fail2ban.php");
    exit;
}

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

            // Handle "Remember Me" functionality
            if (isset($_POST['remember_me']) && $_POST['remember_me'] == '1') {
                // Generate secure random token
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);

                // Set cookie for 30 days (2592000 seconds)
                $expire = time() + (30 * 24 * 60 * 60);

                // Store hashed token in session for validation
                $_SESSION['remember_token'] = $token_hash;
                $_SESSION['remember_expires'] = $expire;

                // Set secure cookie with httponly and samesite flags
                setcookie(
                    'remember_token',
                    $token,
                    [
                        'expires' => $expire,
                        'path' => '/',
                        'domain' => '',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]
                );
                setcookie(
                    'remember_user',
                    $username,
                    [
                        'expires' => $expire,
                        'path' => '/',
                        'domain' => '',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]
                );
            }

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
