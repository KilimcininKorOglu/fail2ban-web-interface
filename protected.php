<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once('config.inc.php');

if (!isset($_SESSION['active']) || $_SESSION['active'] == false) {
  header('location:index.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($config['title']); ?> - Protected Area</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

  <style>
    body {
      background: linear-gradient(135deg, #0d1117 0%, #1c2333 100%);
      min-height: 100vh;
      color: #e6edf3;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .card {
      background: rgba(13, 17, 23, 0.8);
      border: 1px solid rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card">
          <div class="card-body text-center p-5">
            <div class="mb-4">
              <i class="bi bi-shield-check text-success" style="font-size: 4rem;"></i>
            </div>
            <h1 class="mb-3">Welcome to Protected Space</h1>
            <p class="text-muted mb-4">
              <i class="bi bi-person-check"></i> You are logged in as <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong>
            </p>
            <div class="d-grid gap-2">
              <a href="fail2ban.php" class="btn btn-primary">
                <i class="bi bi-speedometer2"></i> Go to Dashboard
              </a>
              <a href="logout.php" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>