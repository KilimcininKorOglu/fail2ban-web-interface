<?php

/**
 * Session Management with Timeout Protection
 *
 * Prevents session hijacking by implementing automatic session timeout
 * Default timeout: 30 minutes of inactivity
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is authenticated and session is still valid
 *
 * @param int $timeout Session timeout in seconds (default: 1800 = 30 minutes)
 * @return bool True if session is valid, false otherwise
 */
function check_session_timeout($timeout = 1800)
{
    // Check if user is logged in
    if (!isset($_SESSION['active']) || $_SESSION['active'] !== true) {
        return false;
    }

    // Initialize last activity time if not set
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }

    // Calculate time since last activity
    $elapsed_time = time() - $_SESSION['last_activity'];

    // Check if session has timed out
    if ($elapsed_time > $timeout) {
        // Session expired - destroy it
        session_destroy_safely();
        return false;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    // Regenerate session ID periodically for security (every 10 minutes)
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 600) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }

    return true;
}

/**
 * Safely destroy session and clean up
 */
function session_destroy_safely()
{
    $_SESSION = array();

    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_destroy();
}

/**
 * Require authentication or redirect to login
 *
 * @param int $timeout Session timeout in seconds
 * @param string $login_page Login page URL
 */
function require_authentication($timeout = 1800, $login_page = 'index.php')
{
    if (!check_session_timeout($timeout)) {
        // Store attempted URL for redirect after login
        if (!isset($_SESSION['redirect_after_login'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }

        // Redirect to login
        header("Location: $login_page");
        exit;
    }
}

/**
 * Get remaining session time in seconds
 *
 * @param int $timeout Total session timeout
 * @return int Remaining time in seconds, or 0 if expired
 */
function get_session_remaining_time($timeout = 1800)
{
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }

    $elapsed = time() - $_SESSION['last_activity'];
    $remaining = $timeout - $elapsed;

    return max(0, $remaining);
}

/**
 * Format remaining time in human-readable format
 *
 * @param int $seconds Seconds remaining
 * @return string Formatted time (e.g., "25 minutes")
 */
function format_session_time($seconds)
{
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') .
            ($minutes > 0 ? ' ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '') : '');
    }
}
