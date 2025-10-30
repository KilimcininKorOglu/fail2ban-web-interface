<?php
/**
 * Simple caching helper - uses APCu if available, otherwise file cache
 */

function cache_get($key) {
    // Try APCu first (if available)
    if (function_exists('apcu_fetch')) {
        $success = false;
        $value = apcu_fetch($key, $success);
        return $success ? $value : null;
    }

    // Fallback to file cache
    $cache_file = sys_get_temp_dir() . '/f2b_cache_' . md5($key);
    if (file_exists($cache_file)) {
        $data = @unserialize(file_get_contents($cache_file));
        if ($data && isset($data['expire']) && $data['expire'] > time()) {
            return $data['value'];
        }
    }
    return null;
}

function cache_set($key, $value, $ttl = 60) {
    // Try APCu first (if available)
    if (function_exists('apcu_store')) {
        return apcu_store($key, $value, $ttl);
    }

    // Fallback to file cache
    $cache_file = sys_get_temp_dir() . '/f2b_cache_' . md5($key);
    $data = [
        'value' => $value,
        'expire' => time() + $ttl
    ];
    return @file_put_contents($cache_file, serialize($data)) !== false;
}

function cache_clear($pattern = null) {
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
    }

    // Clear file cache
    $files = glob(sys_get_temp_dir() . '/f2b_cache_*');
    foreach ($files as $file) {
        @unlink($file);
    }
}
