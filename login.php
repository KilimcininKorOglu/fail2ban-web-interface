<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once('config.inc.php'); ?>
<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Fail2Ban Web Interface">
  <title><?php echo htmlspecialchars($config['title']); ?> - Login</title>

  <!-- Bootstrap 5.3 with dark mode support -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

  <style>
    body {
      display: flex;
      align-items: center;
      padding-top: 40px;
      padding-bottom: 40px;
      background: linear-gradient(135deg, #0d1117 0%, #1c2333 100%);
      min-height: 100vh;
    }

    .form-signin {
      width: 100%;
      max-width: 420px;
      padding: 15px;
      margin: auto;
    }

    .form-signin .card {
      border: 1px solid rgba(255, 255, 255, 0.1);
      background: rgba(13, 17, 23, 0.8);
      backdrop-filter: blur(10px);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    }

    .form-floating > .form-control {
      background-color: rgba(255, 255, 255, 0.05);
      border-color: rgba(255, 255, 255, 0.1);
      color: #fff;
    }

    .form-floating > .form-control:focus {
      background-color: rgba(255, 255, 255, 0.08);
      border-color: #0d6efd;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .form-floating > label {
      color: rgba(255, 255, 255, 0.6);
    }

    .icon-circle {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      box-shadow: 0 4px 20px rgba(13, 110, 253, 0.4);
    }

    .icon-circle i {
      font-size: 2.5rem;
      color: white;
    }

    .btn-primary {
      background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
      border: none;
      padding: 0.75rem;
      font-weight: 500;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 20px rgba(13, 110, 253, 0.5);
    }

    .form-check-input:checked {
      background-color: #0d6efd;
      border-color: #0d6efd;
    }

    .text-muted {
      color: rgba(255, 255, 255, 0.5) !important;
    }
  </style>
</head>

<body>
  <main class="form-signin">
    <form method="POST" action="index.php">
      <div class="card">
        <div class="card-body p-4">
          <!-- Icon -->
          <div class="icon-circle">
            <i class="bi bi-shield-lock"></i>
          </div>

          <!-- Title -->
          <h1 class="h3 mb-4 fw-normal text-center">Sign in to Fail2Ban</h1>

          <!-- Username -->
          <div class="form-floating mb-3">
            <input name="username" type="text" class="form-control" id="floatingInput" placeholder="Username" required autofocus>
            <label for="floatingInput"><i class="bi bi-person me-2"></i>Username</label>
          </div>

          <!-- Password -->
          <div class="form-floating mb-3">
            <input name="password" type="password" class="form-control" id="floatingPassword" placeholder="Password" required>
            <label for="floatingPassword"><i class="bi bi-key me-2"></i>Password</label>
          </div>

          <!-- Remember me -->
          <div class="form-check text-start mb-4">
            <input class="form-check-input" type="checkbox" value="remember-me" id="rememberCheck">
            <label class="form-check-label" for="rememberCheck">
              Remember me
            </label>
          </div>

          <!-- Submit button -->
          <button class="w-100 btn btn-lg btn-primary" type="submit">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign in
          </button>

          <!-- Footer -->
          <p class="mt-4 mb-0 text-center text-muted">
            <i class="bi bi-shield-check me-1"></i>
            <?php echo htmlspecialchars($config['title']); ?> &copy; <?php echo date('Y'); ?>
          </p>
        </div>
      </div>
    </form>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>