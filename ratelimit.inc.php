<?php

/**
 * Rate Limiting and Brute-Force Protection
 *
 * Prevents brute-force login attacks by tracking failed login attempts
 * and temporarily locking out accounts after too many failures.
 *
 * Features:
 * - IP-based rate limiting (prevents attacks from same IP)
 * - Username-based rate limiting (prevents distributed attacks on same account)
 * - Configurable lockout duration and attempt thresholds (via config.json)
 * - Automatic cleanup of old attempts
 * - Persistent storage via file-based system
 */

// Load configuration from global config
function ratelimit_get_config()
{
    global $config;

    // Default values (fallback if config not loaded)
    $defaults = [
        'max_attempts' => 5,
        'lockout_time' => 900,
        'window' => 1800
    ];

    // Override with config.json values if available
    if (isset($config['security']['rate_limit'])) {
        return array_merge($defaults, $config['security']['rate_limit']);
    }

    return $defaults;
}

// Configuration constants (loaded from config.json)
$ratelimit_config = ratelimit_get_config();
define('RATE_LIMIT_MAX_ATTEMPTS', $ratelimit_config['max_attempts']);
define('RATE_LIMIT_LOCKOUT_TIME', $ratelimit_config['lockout_time']);
define('RATE_LIMIT_WINDOW', $ratelimit_config['window']);
define('RATE_LIMIT_STORAGE_PATH', __DIR__ . '/tmp/login_attempts.json');

/**
 * Initialize rate limit storage
 */
function ratelimit_init()
{
    $dir = dirname(RATE_LIMIT_STORAGE_PATH);
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

/**
 * Load rate limit data from storage
 *
 * @return array Rate limit data structure
 */
function ratelimit_load()
{
    ratelimit_init();

    if (!file_exists(RATE_LIMIT_STORAGE_PATH)) {
        return [
            'ip' => [],
            'username' => [],
            'last_cleanup' => time()
        ];
    }

    $json = @file_get_contents(RATE_LIMIT_STORAGE_PATH);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        return [
            'ip' => [],
            'username' => [],
            'last_cleanup' => time()
        ];
    }

    return $data;
}

/**
 * Save rate limit data to storage
 *
 * @param array $data Rate limit data to save
 */
function ratelimit_save($data)
{
    ratelimit_init();
    $json = json_encode($data, JSON_PRETTY_PRINT);
    @file_put_contents(RATE_LIMIT_STORAGE_PATH, $json, LOCK_EX);
}

/**
 * Clean up old entries from rate limit data
 *
 * @param array $data Rate limit data
 * @return array Cleaned data
 */
function ratelimit_cleanup($data)
{
    $now = time();
    $cutoff_time = $now - RATE_LIMIT_WINDOW;

    // Clean IP-based attempts
    foreach ($data['ip'] as $ip => $info) {
        if ($info['locked_until'] > 0 && $info['locked_until'] < $now) {
            // Lockout expired, reset
            unset($data['ip'][$ip]);
        } elseif ($info['locked_until'] == 0) {
            // Not locked, clean old attempts
            $data['ip'][$ip]['attempts'] = array_filter(
                $info['attempts'],
                function ($timestamp) use ($cutoff_time) {
                    return $timestamp > $cutoff_time;
                }
            );

            // Remove entry if no recent attempts
            if (empty($data['ip'][$ip]['attempts'])) {
                unset($data['ip'][$ip]);
            }
        }
    }

    // Clean username-based attempts
    foreach ($data['username'] as $username => $info) {
        if ($info['locked_until'] > 0 && $info['locked_until'] < $now) {
            // Lockout expired, reset
            unset($data['username'][$username]);
        } elseif ($info['locked_until'] == 0) {
            // Not locked, clean old attempts
            $data['username'][$username]['attempts'] = array_filter(
                $info['attempts'],
                function ($timestamp) use ($cutoff_time) {
                    return $timestamp > $cutoff_time;
                }
            );

            // Remove entry if no recent attempts
            if (empty($data['username'][$username]['attempts'])) {
                unset($data['username'][$username]);
            }
        }
    }

    $data['last_cleanup'] = $now;
    return $data;
}

/**
 * Check if IP or username is currently locked out
 *
 * @param string $ip Client IP address
 * @param string $username Username attempting to login
 * @return array ['locked' => bool, 'reason' => string, 'unlock_time' => int]
 */
function ratelimit_is_locked($ip, $username)
{
    $data = ratelimit_load();
    $now = time();

    // Check IP-based lockout
    if (isset($data['ip'][$ip])) {
        $ip_info = $data['ip'][$ip];
        if ($ip_info['locked_until'] > $now) {
            $remaining = $ip_info['locked_until'] - $now;
            return [
                'locked' => true,
                'reason' => 'ip',
                'unlock_time' => $ip_info['locked_until'],
                'remaining_seconds' => $remaining,
                'message' => "Too many failed login attempts from your IP address. Please try again in " .
                    ceil($remaining / 60) . " minutes."
            ];
        }
    }

    // Check username-based lockout
    if (isset($data['username'][$username])) {
        $user_info = $data['username'][$username];
        if ($user_info['locked_until'] > $now) {
            $remaining = $user_info['locked_until'] - $now;
            return [
                'locked' => true,
                'reason' => 'username',
                'unlock_time' => $user_info['locked_until'],
                'remaining_seconds' => $remaining,
                'message' => "This account is temporarily locked due to too many failed login attempts. " .
                    "Please try again in " . ceil($remaining / 60) . " minutes."
            ];
        }
    }

    return ['locked' => false];
}

/**
 * Record a failed login attempt
 *
 * @param string $ip Client IP address
 * @param string $username Username that failed to login
 * @return array Updated lock status
 */
function ratelimit_record_failure($ip, $username)
{
    $data = ratelimit_load();
    $now = time();

    // Periodic cleanup (every 5 minutes)
    if ($now - $data['last_cleanup'] > 300) {
        $data = ratelimit_cleanup($data);
    }

    // Initialize IP tracking if needed
    if (!isset($data['ip'][$ip])) {
        $data['ip'][$ip] = [
            'attempts' => [],
            'locked_until' => 0
        ];
    }

    // Initialize username tracking if needed
    if (!isset($data['username'][$username])) {
        $data['username'][$username] = [
            'attempts' => [],
            'locked_until' => 0
        ];
    }

    // Record the failed attempt
    $data['ip'][$ip]['attempts'][] = $now;
    $data['username'][$username]['attempts'][] = $now;

    // Count recent attempts (within the time window)
    $cutoff_time = $now - RATE_LIMIT_WINDOW;
    $ip_recent_attempts = count(array_filter(
        $data['ip'][$ip]['attempts'],
        function ($timestamp) use ($cutoff_time) {
            return $timestamp > $cutoff_time;
        }
    ));

    $username_recent_attempts = count(array_filter(
        $data['username'][$username]['attempts'],
        function ($timestamp) use ($cutoff_time) {
            return $timestamp > $cutoff_time;
        }
    ));

    // Check if lockout threshold reached
    if ($ip_recent_attempts >= RATE_LIMIT_MAX_ATTEMPTS) {
        $data['ip'][$ip]['locked_until'] = $now + RATE_LIMIT_LOCKOUT_TIME;
        error_log("Fail2Ban Web: IP $ip locked out after $ip_recent_attempts failed login attempts");
    }

    if ($username_recent_attempts >= RATE_LIMIT_MAX_ATTEMPTS) {
        $data['username'][$username]['locked_until'] = $now + RATE_LIMIT_LOCKOUT_TIME;
        error_log("Fail2Ban Web: Username '$username' locked out after $username_recent_attempts failed login attempts");
    }

    // Save updated data
    ratelimit_save($data);

    // Return current lock status
    return ratelimit_is_locked($ip, $username);
}

/**
 * Clear all failed attempts for a successful login
 *
 * @param string $ip Client IP address
 * @param string $username Username that successfully logged in
 */
function ratelimit_reset($ip, $username)
{
    $data = ratelimit_load();

    // Clear IP attempts
    if (isset($data['ip'][$ip])) {
        unset($data['ip'][$ip]);
    }

    // Clear username attempts
    if (isset($data['username'][$username])) {
        unset($data['username'][$username]);
    }

    ratelimit_save($data);
}

/**
 * Get current attempt count for IP and username
 *
 * @param string $ip Client IP address
 * @param string $username Username to check
 * @return array ['ip_attempts' => int, 'username_attempts' => int]
 */
function ratelimit_get_attempts($ip, $username)
{
    $data = ratelimit_load();
    $now = time();
    $cutoff_time = $now - RATE_LIMIT_WINDOW;

    $ip_attempts = 0;
    if (isset($data['ip'][$ip])) {
        $ip_attempts = count(array_filter(
            $data['ip'][$ip]['attempts'],
            function ($timestamp) use ($cutoff_time) {
                return $timestamp > $cutoff_time;
            }
        ));
    }

    $username_attempts = 0;
    if (isset($data['username'][$username])) {
        $username_attempts = count(array_filter(
            $data['username'][$username]['attempts'],
            function ($timestamp) use ($cutoff_time) {
                return $timestamp > $cutoff_time;
            }
        ));
    }

    return [
        'ip_attempts' => $ip_attempts,
        'username_attempts' => $username_attempts,
        'max_attempts' => RATE_LIMIT_MAX_ATTEMPTS,
        'remaining_attempts' => max(0, RATE_LIMIT_MAX_ATTEMPTS - max($ip_attempts, $username_attempts))
    ];
}

/**
 * Get client IP address (handles proxy headers)
 *
 * @return string Client IP address
 */
function ratelimit_get_client_ip()
{
    // Check for proxy headers first
    $headers_to_check = [
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_X_FORWARDED_FOR',     // Standard proxy header
        'HTTP_X_REAL_IP',           // Nginx proxy
        'REMOTE_ADDR'               // Direct connection
    ];

    foreach ($headers_to_check as $header) {
        if (!empty($_SERVER[$header])) {
            // Handle comma-separated IPs (take first one)
            $ip = trim(explode(',', $_SERVER[$header])[0]);

            // Validate IP address
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0'; // Fallback if no valid IP found
}
