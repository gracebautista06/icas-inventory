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

    // Basic input check
    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } else {
        // Fetch user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1");
        $stmt->execute([$email, $_POST['role']]); 
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: instructor/dashboard.php');
            }
            exit;

        } else {
            // Generic error — do NOT reveal whether email or password is wrong
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

  <style>
    /* ── Root variables ── */
    :root {
      --brand-navy:   #1B2B4B;
      --brand-blue:   #2563EB;
      --brand-light:  #EFF4FF;
      --brand-accent: #F59E0B;
      --text-muted:   #6B7280;
      --border:       #E5E7EB;
      --radius:       14px;
    }

    * { box-sizing: border-box; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: #F0F4F8;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }

    /* ── Split layout ── */
    .auth-wrapper {
      display: flex;
      width: 100%;
      max-width: 900px;
      min-height: 560px;
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,.12);
    }

    /* Left panel — branding */
    .auth-brand {
      flex: 1;
      background: var(--brand-navy);
      padding: 3rem 2.5rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }
    .auth-brand::before {
      content: '';
      position: absolute;
      top: -80px; left: -80px;
      width: 340px; height: 340px;
      border-radius: 50%;
      background: rgba(37,99,235,.25);
    }
    .auth-brand::after {
      content: '';
      position: absolute;
      bottom: -60px; right: -60px;
      width: 260px; height: 260px;
      border-radius: 50%;
      background: rgba(245,158,11,.15);
    }
    .brand-logo {
      font-family: 'DM Serif Display', serif;
      color: #fff;
      font-size: 2rem;
      line-height: 1;
      z-index: 1;
    }
    .brand-logo span {
      display: block;
      font-family: 'DM Sans', sans-serif;
      font-size: .78rem;
      font-weight: 500;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: rgba(255,255,255,.55);
      margin-top: .35rem;
    }
    .brand-badge {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      background: rgba(245,158,11,.18);
      color: var(--brand-accent);
      font-size: .78rem;
      font-weight: 600;
      letter-spacing: .05em;
      text-transform: uppercase;
      padding: .4rem .9rem;
      border-radius: 999px;
      border: 1px solid rgba(245,158,11,.35);
      z-index: 1;
    }
    .brand-tagline {
      color: rgba(255,255,255,.75);
      font-size: 1.05rem;
      line-height: 1.6;
      z-index: 1;
    }
    .brand-tagline strong { color: #fff; }

    /* Right panel — form */
    .auth-form-panel {
      flex: 1.15;
      background: #fff;
      padding: 3rem 2.5rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .auth-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.8rem;
      color: var(--brand-navy);
      margin-bottom: .4rem;
    }
    .auth-subtitle {
      color: var(--text-muted);
      font-size: .9rem;
      margin-bottom: 2rem;
    }

    /* Form controls */
    .form-label {
      font-size: .82rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: .4rem;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .input-group-icon {
      position: relative;
    }
    .input-group-icon .bi {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 1rem;
      z-index: 2;
    }
    .input-group-icon input {
      padding-left: 2.6rem;
    }
    .form-control {
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: .72rem 1rem;
      font-size: .95rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus {
      border-color: var(--brand-blue);
      box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }

    /* Password toggle */
    .pass-wrapper { position: relative; }
    .pass-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      font-size: 1rem;
      padding: 0;
      z-index: 2;
    }
    .pass-wrapper input { padding-right: 2.6rem; }

    /* Submit button */
    .btn-login {
      background: var(--brand-blue);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: .78rem;
      font-size: .95rem;
      font-weight: 600;
      width: 100%;
      cursor: pointer;
      transition: background .2s, transform .1s;
    }
    .btn-login:hover  { background: #1D4ED8; }
    .btn-login:active { transform: scale(.99); }

    /* Alert */
    .alert {
      border-radius: 10px;
      font-size: .88rem;
      padding: .75rem 1rem;
      margin-bottom: 1.25rem;
    }

    /* Register link */
    .register-link {
      text-align: center;
      margin-top: 1.5rem;
      font-size: .88rem;
      color: var(--text-muted);
    }
    .register-link a {
      color: var(--brand-blue);
      font-weight: 600;
      text-decoration: none;
    }
    .register-link a:hover { text-decoration: underline; }

    /* Responsive */
    @media (max-width: 640px) {
      .auth-brand { display: none; }
      .auth-form-panel { padding: 2rem 1.5rem; }
    }
  </style>
</head>
<body>

<div class="auth-wrapper">

  <!-- ── Left branding panel ── -->
  <div class="auth-brand">
    <div class="brand-logo">
      ICAS
      <span>Inventory Control & Asset System</span>
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
            placeholder="you@school.edu"
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

      <div class="mb-3">
  <label class="form-label">Login as</label>
  <select name="role" class="form-select form-control" required>
    <option value="instructor">Instructor</option>
    <option value="admin">Administrator</option>
  </select>
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