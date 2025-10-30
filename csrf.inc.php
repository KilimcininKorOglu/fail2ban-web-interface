<?php
/**
 * CSRF Protection Helper Functions
 *
 * Usage:
 * 1. In forms: echo csrf_token_field();
 * 2. Before processing: csrf_verify() or die('CSRF validation failed');
 */

/**
 * Generate CSRF token and store in session
 */
function csrf_generate_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time'] > 3600)) {
        // Generate new token or refresh if older than 1 hour
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }

    return $_SESSION['csrf_token'];
}

/**
 * Get current CSRF token
 */
function csrf_get_token() {
    return csrf_generate_token();
}

/**
 * Generate hidden input field with CSRF token
 */
function csrf_token_field() {
    $token = csrf_get_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF token from POST/GET request
 *
 * @return bool True if valid, False otherwise
 */
function csrf_verify() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Get token from request
    $token = null;
    if (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    } elseif (isset($_GET['csrf_token'])) {
        $token = $_GET['csrf_token'];
    }

    // Check if token exists in session
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    // Use hash_equals to prevent timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF token or die with error message
 */
function csrf_verify_or_die() {
    if (!csrf_verify()) {
        http_response_code(403);
        die('CSRF validation failed. Please refresh the page and try again.');
    }
}

/**
 * Generate CSRF token for URLs (GET requests)
 */
function csrf_url_token() {
    return 'csrf_token=' . urlencode(csrf_get_token());
}
