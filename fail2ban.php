<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once('config.inc.php');
if (!isset($_SESSION['active']) || $_SESSION['active'] == false) {
  header("Location: login.php");
  exit;
}
require_once('engine.inc.php');
require_once('csrf.inc.php');
require_once('cache.inc.php');

// GeoIP (optional feature - suppress deprecation warnings)
if (file_exists('vendor/autoload.php')) {
  @require_once 'vendor/autoload.php';
}

function getCountryFromIP($ipAddr)
{
  // Skip if GeoIP2 not available or database file missing
  if (!class_exists('GeoIp2\Database\Reader') || !file_exists('GeoLite2-City.mmdb')) {
    return '';
  }

  try {
    @$cityDbReader = new GeoIp2\Database\Reader('GeoLite2-City.mmdb');
    @$record = $cityDbReader->city($ipAddr);
    return $record->country->name ?? '';
  } catch (Exception $e) {
    return '';
  }
}

// Initialize error variables
$error1 = '';
$error2 = '';

if (isset($_POST['submit'])) {
  // CSRF Protection for POST requests
  if (!csrf_verify()) {
    $error2 = 'CSRF validation failed. Please try again.';
    $error2_type = 'danger';
  } else {
    $result = ban_ip($_POST['ban_jail'], $_POST['ban_ip']);
    if ($result != 'OK') {
      $error2 = htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
      $error2_type = 'danger';
    } else {
      $error2 = 'IP successfully banned';
      $error2_type = 'success';
      unset($_POST);
      clearstatcache();
      // Removed sleep(1) for faster page load
    }
  }
}

if (isset($_GET['j']) && isset($_GET['c']) && $_GET['j'] != '' && $_GET['c'] != '') {
  // CSRF Protection for GET requests (unban action)
  if (!csrf_verify()) {
    $error1 = 'CSRF validation failed. Please try again.';
    $error1_type = 'danger';
  } else {
    $result = unban_ip($_GET['j'], $_GET['c']);
    if ($result != 'OK') {
      $error1 = htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
      $error1_type = 'danger';
    } else {
      $error1 = 'IP successfully unbanned';
      $error1_type = 'success';
      unset($_GET);
      clearstatcache();
      // Removed sleep(1) for faster page load
    }
  }
}

$chk = check_socket();
if ($chk != 'OK') {
  $socket_error = $chk;
}

$jails = array();
if (!isset($socket_error)) {
  // Clear cache if refresh requested
  if (isset($_GET['refresh'])) {
    cache_clear();
  }

  // Cache jail data for 30 seconds to improve performance
  $cache_key = 'f2b_jails_' . session_id();
  $jails = cache_get($cache_key);

  if ($jails === null) {
    // Cache miss - fetch from fail2ban
    $jails = list_jails();
    // Check if async DNS is enabled
    $async_dns = isset($f2b['dns_async']) && $f2b['dns_async'] === true && $f2b['usedns'] === true;
    foreach ($jails as $j => $i) {
      // Skip DNS resolution if async is enabled
      $banned = list_banned($j, $async_dns);
      $jails[$j] = $banned;
    }
    cache_set($cache_key, $jails, 30); // Cache for 30 seconds
  }

  // Clear cache after ban/unban operations
  if (!empty($error1) || !empty($error2)) {
    cache_clear();
  }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Fail2Ban Web Interface">
  <title><?php echo htmlspecialchars($config['title']); ?> - Fail2Ban Dashboard</title>

  <!-- Bootstrap 5.3 with dark mode -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

  <style>
    body {
      background: linear-gradient(135deg, #0d1117 0%, #1c2333 100%);
      min-height: 100vh;
      color: #e6edf3;
    }

    .navbar {
      background: rgba(13, 17, 23, 0.95) !important;
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .card {
      background: rgba(13, 17, 23, 0.8);
      border: 1px solid rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .card-header {
      background: rgba(13, 110, 253, 0.1);
      border-bottom: 1px solid rgba(13, 110, 253, 0.3);
      font-weight: 600;
    }

    .table {
      color: #e6edf3;
    }

    .table-dark {
      --bs-table-bg: rgba(255, 255, 255, 0.02);
      --bs-table-border-color: rgba(255, 255, 255, 0.1);
    }

    .table > tbody > tr:hover {
      background-color: rgba(13, 110, 253, 0.1);
    }

    .badge {
      padding: 0.5rem 0.75rem;
      font-weight: 500;
    }

    .btn-action {
      padding: 0.25rem 0.5rem;
      font-size: 0.875rem;
    }

    .jail-header {
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.2) 0%, rgba(10, 88, 202, 0.2) 100%);
      padding: 0.75rem 1rem;
      border-left: 3px solid #0d6efd;
    }

    .form-control, .form-select {
      background-color: rgba(255, 255, 255, 0.05);
      border-color: rgba(255, 255, 255, 0.1);
      color: #e6edf3;
    }

    .form-control:focus, .form-select:focus {
      background-color: rgba(255, 255, 255, 0.08);
      border-color: #0d6efd;
      color: #e6edf3;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .form-select option {
      background-color: #1c2333;
      color: #e6edf3;
    }

    .stats-card {
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(10, 88, 202, 0.05) 100%);
      border-left: 3px solid #0d6efd;
    }

    .empty-state {
      padding: 3rem 1rem;
      text-align: center;
      color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
      font-size: 3rem;
      opacity: 0.3;
    }
  </style>
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">
        <i class="bi bi-shield-check text-primary"></i>
        <strong><?php echo htmlspecialchars($config['title']); ?></strong>
      </a>
      <div class="d-flex align-items-center">
        <span class="navbar-text me-3">
          <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['user']); ?>
        </span>
        <button class="btn btn-outline-danger btn-sm" onclick="location.href='logout.php';">
          <i class="bi bi-box-arrow-right"></i> Logout
        </button>
      </div>
    </div>
  </nav>

  <div class="container-fluid px-4">
    <?php if (isset($socket_error)): ?>
      <!-- Socket Error Alert -->
      <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>
          <strong>Connection Error:</strong> <?php echo htmlspecialchars($socket_error); ?>
        </div>
      </div>
    <?php else: ?>

      <!-- Success/Error Messages -->
      <?php if (!empty($error1)): ?>
        <div class="alert alert-<?php echo $error1_type; ?> alert-dismissible fade show" role="alert">
          <i class="bi bi-<?php echo $error1_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
          <?php echo $error1; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!empty($error2)): ?>
        <div class="alert alert-<?php echo $error2_type; ?> alert-dismissible fade show" role="alert">
          <i class="bi bi-<?php echo $error2_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
          <?php echo $error2; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Add IP Ban Card -->
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-plus-circle"></i> Manually Ban IP Address
        </div>
        <div class="card-body">
          <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" class="row g-3">
            <?php echo csrf_token_field(); ?>
            <div class="col-md-4">
              <label class="form-label"><i class="bi bi-folder2-open"></i> Select Jail</label>
              <select name="ban_jail" class="form-select" required>
                <option value="">Choose jail...</option>
                <?php foreach ($jails as $j => $cli): ?>
                  <option value="<?php echo htmlspecialchars($j, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php if (isset($_POST['ban_jail']) && $_POST['ban_jail'] == $j) echo ' selected'; ?>>
                    <?php echo htmlspecialchars($j, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label"><i class="bi bi-pc-display-horizontal"></i> IP Address</label>
              <input type="text" name="ban_ip" class="form-control" placeholder="192.168.1.1"
                value="<?php echo isset($_POST['ban_ip']) ? htmlspecialchars($_POST['ban_ip'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">&nbsp;</label>
              <button type="submit" name="submit" class="btn btn-primary w-100">
                <i class="bi bi-hammer"></i> Ban IP
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Banned IPs by Jail -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-list-ul"></i> Banned Clients per Jail</span>
          <button class="btn btn-sm btn-outline-primary" onclick="location.reload();">
            <i class="bi bi-arrow-clockwise"></i> Refresh
          </button>
        </div>
        <div class="card-body p-0">
          <?php if (empty($jails)): ?>
            <div class="empty-state">
              <i class="bi bi-inbox"></i>
              <p class="mt-3">No jails found</p>
            </div>
          <?php else: ?>
            <?php foreach ($jails as $j => $cli): ?>
              <?php if ($f2b['noempt'] === false || is_array($cli)): ?>
                <div class="jail-header">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h5 class="mb-1">
                        <i class="bi bi-folder2-open text-primary"></i>
                        <?php echo strtoupper(htmlspecialchars($j, ENT_QUOTES, 'UTF-8')); ?>
                      </h5>
                      <?php if ($f2b['jainfo'] === true): ?>
                        <small class="text-muted">
                          <?php
                          $inf = jail_info($j);
                          echo htmlspecialchars(implode(', ', $inf), ENT_QUOTES, 'UTF-8');
                          ?>
                        </small>
                      <?php endif; ?>
                    </div>
                    <span class="badge bg-<?php echo is_array($cli) ? 'danger' : 'success'; ?>">
                      <?php echo is_array($cli) ? count($cli) . ' banned' : 'No bans'; ?>
                    </span>
                  </div>
                </div>

                <?php if (is_array($cli) && count($cli) > 0): ?>
                  <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                      <thead>
                        <tr>
                          <th style="width: 100px;">Action</th>
                          <th>IP Address</th>
                          <th>Hostname</th>
                          <th style="width: 150px;">Country</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($cli as $i => $c): ?>
                          <?php
                          $ip = trim(strstr($c, '(', true) ?: $c);
                          $hostname = '';
                          if (preg_match('/\((.*?)\)/', $c, $matches)) {
                            $hostname = $matches[1];
                          }
                          // GeoIP lookup (cached to improve performance)
                          static $country_cache = [];
                          if (isset($country_cache[$ip])) {
                            $country = $country_cache[$ip];
                          } else {
                            $country = getCountryFromIP($ip);
                            $country_cache[$ip] = $country;
                          }
                          // Check if async DNS is enabled
                          $async_dns = isset($f2b['dns_async']) && $f2b['dns_async'] === true && $f2b['usedns'] === true;
                          ?>
                          <tr>
                            <td>
                              <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>?j=<?php echo urlencode($j); ?>&c=<?php echo urlencode($ip); ?>&<?php echo csrf_url_token(); ?>"
                                class="btn btn-danger btn-action"
                                onclick="return confirm('Unban <?php echo htmlspecialchars($ip); ?>?');">
                                <i class="bi bi-unlock"></i> Unban
                              </a>
                            </td>
                            <td>
                              <code class="text-warning"><?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?></code>
                            </td>
                            <td>
                              <?php if ($async_dns): ?>
                                <small class="text-muted hostname-cell" data-ip="<?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?>">
                                  <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                  Loading...
                                </small>
                              <?php else: ?>
                                <small class="text-muted"><?php echo $hostname ? htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8') : 'unknown'; ?></small>
                              <?php endif; ?>
                            </td>
                            <td>
                              <?php if ($country): ?>
                                <span class="badge bg-primary">
                                  <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="p-3 text-center text-muted">
                    <i class="bi bi-check-circle"></i> No banned clients in this jail
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Footer -->
      <div class="text-center mt-4 mb-3">
        <small class="text-muted">
          <i class="bi bi-clock"></i> <?php echo date("r"); ?> |
          <i class="bi bi-shield-check"></i> Fail2Ban v<?php echo $f2b['version']; ?>
        </small>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <?php if (isset($f2b['dns_async']) && $f2b['dns_async'] === true && $f2b['usedns'] === true): ?>
  <!-- Asynchronous Hostname Resolution -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const hostnameCells = document.querySelectorAll('.hostname-cell');
      const resolvedIPs = new Set(); // Avoid duplicate requests

      // Process hostnames in batches for better performance
      const batchSize = 10;
      const delay = 100; // 100ms between batches

      let batch = [];
      hostnameCells.forEach((cell, index) => {
        batch.push(cell);

        if (batch.length === batchSize || index === hostnameCells.length - 1) {
          setTimeout(() => {
            processBatch(batch);
          }, Math.floor(index / batchSize) * delay);
          batch = [];
        }
      });

      function processBatch(cells) {
        cells.forEach(cell => {
          const ip = cell.getAttribute('data-ip');

          // Skip if already resolved
          if (resolvedIPs.has(ip)) {
            return;
          }
          resolvedIPs.add(ip);

          // Fetch hostname via AJAX
          fetch('resolve_hostname.php?ip=' + encodeURIComponent(ip))
            .then(response => response.json())
            .then(data => {
              // Update all cells with this IP
              document.querySelectorAll('.hostname-cell[data-ip="' + ip + '"]').forEach(targetCell => {
                if (data.resolved && data.hostname !== ip) {
                  targetCell.textContent = data.hostname;
                  targetCell.classList.remove('text-muted');
                  targetCell.classList.add('text-info');
                } else {
                  targetCell.textContent = 'unknown';
                }
              });
            })
            .catch(error => {
              // Silently fail - keep "Loading..." or show "unknown"
              document.querySelectorAll('.hostname-cell[data-ip="' + ip + '"]').forEach(targetCell => {
                targetCell.textContent = 'unknown';
              });
            });
        });
      }
    });
  </script>
  <?php endif; ?>
</body>

</html>
