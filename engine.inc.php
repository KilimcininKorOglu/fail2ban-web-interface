<?php

function check_socket()
{
  global $f2b;

  // If socket check is disabled, test fail2ban-client directly
  if (isset($f2b['use_socket_check']) && $f2b['use_socket_check'] === false) {
    // Test if fail2ban-client is accessible and working
    $test = @exec('fail2ban-client ping 2>&1', $output, $return_code);
    if ($return_code === 0 && strpos($test, 'pong') !== false) {
      return 'OK';
    } else {
      return 'fail2ban-client is not accessible or fail2ban is not running. Output: ' . htmlspecialchars($test, ENT_QUOTES, 'UTF-8');
    }
  }

  // Traditional socket file check (requires open_basedir access)
  // Suppress warnings for open_basedir restrictions
  if (!@file_exists($f2b['socket'])) {
    return 'Socket file not found. Check open_basedir restrictions or set $f2b[\'use_socket_check\'] = false in config.inc.php';
  } elseif (!@is_readable($f2b['socket'])) {
    return 'Socket file not readable.';
  } elseif (!@is_writeable($f2b['socket'])) {
    return 'Socket file not writeable.';
  }
  return 'OK';
}

function list_jails()
{
  global $f2b;
  $jails = array();
  $erg = @exec('fail2ban-client status | grep "Jail list:" | awk -F ":" \'{print $2}\' | awk \'{$1=$1;print}\'');
  $erg = explode(",", $erg);
  foreach ($erg as $i => $j) {
    $jails[trim($j)] = false;
  }
  ksort($jails);
  return $jails;
}

function jail_info($jail)
{
  global $f2b;
  $info = array();
  $erg = @exec('fail2ban-client get ' . escapeshellarg($jail) . ' findtime ');
  if (is_numeric($erg)) {
    $info['findtime'] = 'findtime: ' . $erg;
  }
  $erg = @exec('fail2ban-client get ' . escapeshellarg($jail) . ' bantime ');
  if (is_numeric($erg)) {
    $info['bantime'] = 'bantime: ' . $erg;
  }
  $erg = @exec('fail2ban-client get ' . escapeshellarg($jail) . ' maxretry ');
  if (is_numeric($erg)) {
    $info['maxretry'] = 'maxretry: ' . $erg;
  }
  return $info;
}

function list_banned($jail, $skip_dns = false)
{
  global $f2b;
  $banned = array();
  // Validate jail name to prevent command injection
  $jail = escapeshellarg($jail);
  $erg = @exec('fail2ban-client status ' . $jail . ' | grep "IP list:" | awk -F ":" \'{print$2}\' | awk \'{$1=$1;print}\'');
  if ($erg != '') {
    $banned = explode(" ", $erg);
    // Skip DNS resolution if requested (for async loading)
    if (!$skip_dns && $f2b['usedns'] === true) {
      foreach ($banned as $i => $cli) {
        $dns = resolve_hostname($cli);
        if ($dns == $cli) {
          $dns = ' (unknown)';
        } else {
          $dns = ' (' . $dns . ')';
        }
        $banned[$i] .= $dns;
      }
    }
    return $banned;
  }
  return false;
}

/**
 * Resolve hostname with caching and timeout
 * @param string $ip IP address to resolve
 * @return string Hostname or IP if resolution fails
 */
function resolve_hostname($ip)
{
  global $f2b;
  require_once('cache.inc.php');

  // Check cache first (24 hour TTL)
  $cache_key = 'hostname_' . $ip;
  $cached = cache_get($cache_key);
  if ($cached !== null) {
    return $cached;
  }

  // Set timeout for DNS query (default 2 seconds)
  $timeout = isset($f2b['dns_timeout']) ? (int)$f2b['dns_timeout'] : 2;
  $original_timeout = ini_get('default_socket_timeout');
  ini_set('default_socket_timeout', $timeout);

  // Perform DNS lookup
  $hostname = @gethostbyaddr($ip);

  // Restore original timeout
  ini_set('default_socket_timeout', $original_timeout);

  // Cache result for 24 hours (86400 seconds)
  $cache_ttl = isset($f2b['dns_cache_ttl']) ? (int)$f2b['dns_cache_ttl'] : 86400;
  cache_set($cache_key, $hostname, $cache_ttl);

  return $hostname;
}

function ban_ip($jail, $ip)
{
  if ($jail == '') {
    return 'no jail selected';
  } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
    return 'no valid ip address';
  }
  $erg = @exec('fail2ban-client set ' . escapeshellarg($jail) . ' banip ' . escapeshellarg($ip));
  if ($erg != 1) {
    return 'could not ban this ip';
  }
  return 'OK';
}

function unban_ip($jail, $ip)
{
  if ($jail == '') {
    return 'no jail selected';
  } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
    return 'no valid ip address';
  }
  $erg = @exec('fail2ban-client set ' . escapeshellarg($jail) . ' unbanip ' . escapeshellarg($ip));
  if ($erg != 1) {
    return 'could not unban this ip $erg';
  }
  return 'OK';
}
