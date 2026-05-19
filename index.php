<?php
// ============================================================
//  index.php — Login Page
// ============================================================

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// If already logged in, redirect to their dashboard
if (!empty($_SESSION['user_id'])) {
    $dest = $_SESSION['user_role'] === 'admin' ? 'admin/dashboard.php' : 'instructor/dashboard.php';
    header("Location: $dest");
    exit;
}

$error   = '';
$success = '';

// Success message after registration
if (!empty($_GET['registered'])) {
    $success = 'Account created successfully! You can now log in.';
}
if (!empty($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// ── Handle login form submission ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    // Basic input validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } else {
        // Fetch user by email only — role is detected from the database,
        // NOT supplied by the user. This prevents role spoofing.
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];

            // Redirect based on role stored in DB — never user input
            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: instructor/dashboard.php');
            }
            exit;

        } else {
            // Generic error — never reveal whether email or password is wrong
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ICAS — Login</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">

  <!-- Shared stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">

  <!-- ── Left branding panel ── -->
  <div class="auth-brand">
    <div class="brand-logo">
      ICAS
      <span>Inventory Control &amp; Asset System</span>
    </div>

    <div>
      <p class="brand-tagline mb-4">
        <strong>Monitor, manage, and track</strong><br>
        school properties with ease and confidence.
      </p>
      <div class="brand-badge">
        <i class="bi bi-shield-check"></i>
        Secure &amp; Reliable
      </div>
    </div>
  </div>

  <!-- ── Right form panel ── -->
  <div class="auth-form-panel">

    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-subtitle">Sign in to your account to continue</p>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="index.php" novalidate>

      <!-- Email -->
      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <div class="input-group-icon">
          <i class="bi bi-envelope"></i>
          <input
            type="email"
            class="form-control"
            id="email"
            name="email"
            placeholder="Enter your email"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            required
            autocomplete="email"
          >
        </div>
      </div>

      <!-- Password -->
      <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <div class="pass-wrapper input-group-icon">
          <i class="bi bi-lock"></i>
          <input
            type="password"
            class="form-control"
            id="password"
            name="password"
            placeholder="Enter your password"
            required
            autocomplete="current-password"
          >
          <button type="button" class="pass-toggle" onclick="togglePassword()" title="Show/hide password">
            <i class="bi bi-eye" id="pass-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>

    </form>

    <p class="register-link">
      Don't have an account? <a href="register.php">Create one here</a>
    </p>

  </div><!-- /auth-form-panel -->
</div><!-- /auth-wrapper -->

<script>
function togglePassword() {
  const input = document.getElementById('password');
  const icon  = document.getElementById('pass-icon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>