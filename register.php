<?php
// ============================================================
//  register.php — New Account Registration
// ============================================================

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// If already logged in, redirect
if (!empty($_SESSION['user_id'])) {
    $dest = $_SESSION['user_role'] === 'admin' ? 'admin/dashboard.php' : 'instructor/dashboard.php';
    header("Location: $dest");
    exit;
}

$error   = '';
$success = '';
$fields  = []; // Repopulate form on error

// ── Handle form submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect & sanitize inputs
    $full_name        = trim($_POST['full_name']        ?? '');
    $email            = trim($_POST['email']            ?? '');
    $password         =      $_POST['password']         ?? '';
    $confirm_password =      $_POST['confirm_password'] ?? '';

    // Keep values for repopulating the form
    $fields = ['full_name' => $full_name, 'email' => $email];

    // ── Validation ────────────────────────────────────────────
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';

    } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $error = 'Full name must be between 2 and 100 characters.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';

    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';

    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';

    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';

    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'That email address is already registered.';
        } else {
            // ── Create the account ────────────────────────────
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, password_hash, role)
                 VALUES (?, ?, ?, 'instructor')"
            );
            $stmt->execute([$full_name, $email, $hash]);

            // Redirect to login with success message
            header('Location: index.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ICAS — Create Account</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">

  <style>
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

    .auth-wrapper {
      display: flex;
      width: 100%;
      max-width: 900px;
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,.12);
    }

    /* Left branding panel */
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

    /* Steps on left panel */
    .steps-list {
      list-style: none;
      padding: 0; margin: 0;
      z-index: 1;
    }
    .steps-list li {
      display: flex;
      align-items: flex-start;
      gap: .85rem;
      margin-bottom: 1.1rem;
      color: rgba(255,255,255,.8);
      font-size: .88rem;
      line-height: 1.5;
    }
    .step-dot {
      flex-shrink: 0;
      width: 26px; height: 26px;
      background: rgba(37,99,235,.35);
      border: 1.5px solid rgba(37,99,235,.6);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .75rem;
      font-weight: 600;
      color: #93C5FD;
      margin-top: 1px;
    }

    /* Right form panel */
    .auth-form-panel {
      flex: 1.2;
      background: #fff;
      padding: 2.5rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .auth-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.7rem;
      color: var(--brand-navy);
      margin-bottom: .3rem;
    }
    .auth-subtitle {
      color: var(--text-muted);
      font-size: .88rem;
      margin-bottom: 1.6rem;
    }

    .form-label {
      font-size: .8rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: .35rem;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .input-group-icon { position: relative; }
    .input-group-icon .bi {
      position: absolute;
      left: 14px; top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 1rem;
      z-index: 2;
    }
    .input-group-icon input { padding-left: 2.6rem; }

    .form-control {
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: .68rem 1rem;
      font-size: .93rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus {
      border-color: var(--brand-blue);
      box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }
    .form-control.is-invalid { border-color: #DC2626; }

    /* Password strength bar */
    .strength-bar {
      height: 4px;
      border-radius: 4px;
      background: var(--border);
      margin-top: .5rem;
      overflow: hidden;
    }
    .strength-fill {
      height: 100%;
      border-radius: 4px;
      width: 0;
      transition: width .3s, background .3s;
    }
    .strength-label {
      font-size: .75rem;
      color: var(--text-muted);
      margin-top: .3rem;
    }

    /* Pass toggle */
    .pass-wrapper { position: relative; }
    .pass-toggle {
      position: absolute;
      right: 12px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      color: var(--text-muted);
      cursor: pointer;
      font-size: 1rem; padding: 0;
      z-index: 3;
    }
    .pass-wrapper input { padding-right: 2.6rem; }

    .btn-register {
      background: var(--brand-blue);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: .75rem;
      font-size: .95rem;
      font-weight: 600;
      width: 100%;
      cursor: pointer;
      transition: background .2s, transform .1s;
      margin-top: .5rem;
    }
    .btn-register:hover  { background: #1D4ED8; }
    .btn-register:active { transform: scale(.99); }

    .alert {
      border-radius: 10px;
      font-size: .87rem;
      padding: .7rem 1rem;
      margin-bottom: 1.1rem;
    }

    .login-link {
      text-align: center;
      margin-top: 1.25rem;
      font-size: .87rem;
      color: var(--text-muted);
    }
    .login-link a { color: var(--brand-blue); font-weight: 600; text-decoration: none; }
    .login-link a:hover { text-decoration: underline; }

    @media (max-width: 640px) {
      .auth-brand { display: none; }
      .auth-form-panel { padding: 2rem 1.25rem; }
    }
  </style>
</head>
<body>

<div class="auth-wrapper">

  <!-- ── Left panel ── -->
  <div class="auth-brand">
    <div class="brand-logo">
      ICAS
      <span>Inventory Control & Asset System</span>
    </div>

    <div style="z-index:1">
      <p style="color:rgba(255,255,255,.55);font-size:.78rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;margin-bottom:1rem;">
        Getting started
      </p>
      <ul class="steps-list">
        <li>
          <span class="step-dot">1</span>
          Create your instructor account below
        </li>
        <li>
          <span class="step-dot">2</span>
          Wait for the Admin to assign you to a room
        </li>
        <li>
          <span class="step-dot">3</span>
          Log in to view and report property conditions
        </li>
      </ul>
    </div>
  </div>

  <!-- ── Right form panel ── -->
  <div class="auth-form-panel">

    <h1 class="auth-title">Create an account</h1>
    <p class="auth-subtitle">Register as an Instructor to get started</p>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>

      <!-- Full Name -->
      <div class="mb-3">
        <label for="full_name" class="form-label">Full name</label>
        <div class="input-group-icon">
          <i class="bi bi-person"></i>
          <input
            type="text"
            class="form-control"
            id="full_name"
            name="full_name"
            placeholder="e.g. Juan Dela Cruz"
            value="<?= htmlspecialchars($fields['full_name'] ?? '') ?>"
            required
            autocomplete="name"
          >
        </div>
      </div>

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
            value="<?= htmlspecialchars($fields['email'] ?? '') ?>"
            required
            autocomplete="email"
          >
        </div>
      </div>

      <!-- Password -->
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="pass-wrapper input-group-icon">
          <i class="bi bi-lock"></i>
          <input
            type="password"
            class="form-control"
            id="password"
            name="password"
            placeholder="Min. 8 characters"
            required
            autocomplete="new-password"
            oninput="checkStrength(this.value)"
          >
          <button type="button" class="pass-toggle" onclick="togglePass('password','icon1')">
            <i class="bi bi-eye" id="icon1"></i>
          </button>
        </div>
        <!-- Strength indicator -->
        <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
        <p class="strength-label" id="strength-label">Enter a password</p>
      </div>

      <!-- Confirm Password -->
      <div class="mb-4">
        <label for="confirm_password" class="form-label">Confirm password</label>
        <div class="pass-wrapper input-group-icon">
          <i class="bi bi-lock-fill"></i>
          <input
            type="password"
            class="form-control"
            id="confirm_password"
            name="confirm_password"
            placeholder="Re-enter your password"
            required
            autocomplete="new-password"
          >
          <button type="button" class="pass-toggle" onclick="togglePass('confirm_password','icon2')">
            <i class="bi bi-eye" id="icon2"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-register">
        <i class="bi bi-person-plus me-2"></i>Create Account
      </button>

    </form>

    <p class="login-link">
      Already have an account? <a href="index.php">Sign in</a>
    </p>

  </div>
</div>

<script>
// ── Password visibility toggle ──
function togglePass(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon  = document.getElementById(iconId);
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

// ── Password strength meter ──
function checkStrength(val) {
  const fill  = document.getElementById('strength-fill');
  const label = document.getElementById('strength-label');

  let score = 0;
  if (val.length >= 8)            score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val))  score++;

  const levels = [
    { pct: '0%',   color: '#E5E7EB', text: 'Enter a password' },
    { pct: '25%',  color: '#EF4444', text: 'Weak' },
    { pct: '50%',  color: '#F59E0B', text: 'Fair' },
    { pct: '75%',  color: '#3B82F6', text: 'Good' },
    { pct: '100%', color: '#10B981', text: 'Strong — great password!' },
  ];

  const level = val.length === 0 ? levels[0] : levels[score];
  fill.style.width      = level.pct;
  fill.style.background = level.color;
  label.textContent     = level.text;
  label.style.color     = val.length === 0 ? '#6B7280' : level.color;
}
</script>
</body>
</html>