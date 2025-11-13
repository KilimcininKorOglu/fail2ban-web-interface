#!/usr/bin/env php
<?php
/**
 * GeoLite2 Database Auto-Updater
 *
 * Downloads and updates GeoLite2 database files from MaxMind
 *
 * Usage:
 *   php update_geoip.php
 *
 * Cron setup (daily at 8:00 AM):
 *   0 8 * * * /usr/bin/php /path/to/update_geoip.php >> /var/log/geoip_update.log 2>&1
 *
 * Requirements:
 *   - MaxMind License Key (set in config or environment variable)
 *   - wget or curl command available
 *   - tar command available
 *   - Write permissions to target directory
 */

// Configuration
$config = [
    'license_key' => getenv('MAXMIND_LICENSE_KEY') ?: 'YOUR_LICENSE_KEY_HERE',
    'target_dir' => __DIR__,  // Directory where .mmdb files will be stored
    'temp_dir' => sys_get_temp_dir() . '/geoip_update_' . uniqid(),
    'databases' => [
        'GeoLite2-ASN',
        'GeoLite2-City',
        'GeoLite2-Country'
    ],
    'keep_backups' => 3,  // Number of backup copies to keep
    'timeout' => 300,     // Download timeout in seconds
];

// Logging function
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] [$level] $message\n";
}

// Check requirements
function check_requirements() {
    $required_commands = ['wget', 'tar'];

    foreach ($required_commands as $cmd) {
        $output = [];
        $return_code = 0;
        exec("which $cmd 2>/dev/null", $output, $return_code);

        if ($return_code !== 0) {
            log_message("Required command not found: $cmd", 'ERROR');
            log_message("Please install $cmd to use this script", 'ERROR');
            return false;
        }
    }

    return true;
}

// Download and extract GeoIP database
function download_database($edition, $license_key, $temp_dir, $timeout) {
    log_message("Downloading $edition...");

    $url = sprintf(
        "https://download.maxmind.com/app/geoip_download?edition_id=%s&license_key=%s&suffix=tar.gz",
        escapeshellarg($edition),
        escapeshellarg($license_key)
    );

    $output = [];
    $return_code = 0;

    // Download and extract in one command
    $cmd = sprintf(
        'wget --timeout=%d -nv -O- %s 2>&1 | tar -xzv -C %s 2>&1',
        $timeout,
        $url,
        escapeshellarg($temp_dir)
    );

    exec($cmd, $output, $return_code);

    if ($return_code !== 0) {
        log_message("Failed to download $edition: " . implode("\n", $output), 'ERROR');
        return false;
    }

    log_message("Successfully downloaded $edition");
    return true;
}

// Find and copy .mmdb files
function copy_mmdb_files($temp_dir, $target_dir) {
    log_message("Copying .mmdb files to target directory...");

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temp_dir)
    );

    $copied_files = [];

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'mmdb') {
            $source = $file->getPathname();
            $filename = $file->getFilename();
            $destination = $target_dir . '/' . $filename;

            // Create backup if file exists
            if (file_exists($destination)) {
                $backup = $destination . '.backup.' . date('YmdHis');
                if (copy($destination, $backup)) {
                    log_message("Created backup: " . basename($backup));
                }
            }

            // Copy new file
            if (copy($source, $destination)) {
                log_message("Copied: $filename");
                $copied_files[] = $filename;
            } else {
                log_message("Failed to copy: $filename", 'ERROR');
            }
        }
    }

    return $copied_files;
}

// Clean up old backups
function cleanup_old_backups($target_dir, $keep_backups) {
    log_message("Cleaning up old backups...");

    $backup_files = glob($target_dir . '/*.mmdb.backup.*');

    if (empty($backup_files)) {
        log_message("No backup files found");
        return;
    }

    // Group backups by original filename
    $backups_by_file = [];
    foreach ($backup_files as $backup) {
        $basename = preg_replace('/\.backup\.\d+$/', '', basename($backup));
        $backups_by_file[$basename][] = $backup;
    }

    // Sort and remove old backups for each file
    foreach ($backups_by_file as $basename => $backups) {
        // Sort by modification time (newest first)
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Keep only the specified number of backups
        $to_delete = array_slice($backups, $keep_backups);

        foreach ($to_delete as $old_backup) {
            if (unlink($old_backup)) {
                log_message("Deleted old backup: " . basename($old_backup));
            }
        }
    }
}

// Clean up temporary directory
function cleanup_temp_dir($temp_dir) {
    if (is_dir($temp_dir)) {
        log_message("Cleaning up temporary directory...");

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($temp_dir);
        log_message("Temporary directory cleaned up");
    }
}

// Main execution
try {
    log_message("=== GeoLite2 Database Update Started ===");

    // Check license key
    if (empty($config['license_key']) || $config['license_key'] === 'YOUR_LICENSE_KEY_HERE') {
        log_message("MaxMind license key not configured!", 'ERROR');
        log_message("Set MAXMIND_LICENSE_KEY environment variable or edit the script", 'ERROR');
        exit(1);
    }

    // Check requirements
    if (!check_requirements()) {
        log_message("Requirements check failed", 'ERROR');
        exit(1);
    }

    // Check target directory permissions
    if (!is_writable($config['target_dir'])) {
        log_message("Target directory is not writable: {$config['target_dir']}", 'ERROR');
        exit(1);
    }

    // Create temporary directory
    if (!mkdir($config['temp_dir'], 0755, true)) {
        log_message("Failed to create temporary directory", 'ERROR');
        exit(1);
    }

    log_message("Temporary directory: {$config['temp_dir']}");

    // Download all databases
    $download_success = true;
    foreach ($config['databases'] as $database) {
        if (!download_database(
            $database,
            $config['license_key'],
            $config['temp_dir'],
            $config['timeout']
        )) {
            $download_success = false;
            log_message("Skipping $database due to download failure", 'WARNING');
        }
    }

    if (!$download_success) {
        log_message("Some downloads failed, but continuing with available files", 'WARNING');
    }

    // Copy .mmdb files to target directory
    $copied_files = copy_mmdb_files($config['temp_dir'], $config['target_dir']);

    if (empty($copied_files)) {
        log_message("No .mmdb files were copied", 'ERROR');
        cleanup_temp_dir($config['temp_dir']);
        exit(1);
    }

    log_message("Successfully copied " . count($copied_files) . " database file(s)");

    // Clean up old backups
    cleanup_old_backups($config['target_dir'], $config['keep_backups']);

    // Clean up temporary directory
    cleanup_temp_dir($config['temp_dir']);

    // Display updated files info
    log_message("=== Updated Database Files ===");
    foreach ($copied_files as $filename) {
        $filepath = $config['target_dir'] . '/' . $filename;
        $size = filesize($filepath);
        $date = date('Y-m-d H:i:s', filemtime($filepath));
        log_message(sprintf("  %s - %.2f MB - %s", $filename, $size / 1024 / 1024, $date));
    }

    log_message("=== GeoLite2 Database Update Completed Successfully ===");
    exit(0);

} catch (Exception $e) {
    log_message("Fatal error: " . $e->getMessage(), 'ERROR');
    log_message("Stack trace: " . $e->getTraceAsString(), 'ERROR');

    // Clean up on error
    if (isset($config['temp_dir']) && is_dir($config['temp_dir'])) {
        cleanup_temp_dir($config['temp_dir']);
    }

    exit(1);
}
