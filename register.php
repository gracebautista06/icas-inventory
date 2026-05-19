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
    $role             =      $_POST['role']             ?? '';
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
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$full_name, $email, $hash, $role]);

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

  <!-- ── Left panel ── -->
  <div class="auth-brand">
    <div class="brand-logo">
      ICAS
      <span>Inventory Control &amp; Asset System</span>
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

      <!-- Role -->
      <div class="mb-3">
        <label class="form-label">Register as</label>
        <div class="input-group-icon">
          <i class="bi bi-briefcase"></i>
          <!-- padding-left keeps the icon clear of the select text -->
          <select name="role" class="form-select form-control" style="padding-left:2.6rem;" required>
            <option value="instructor">Instructor</option>
            <option value="admin">Administrator</option>
          </select>
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
          <button type="button" class="pass-toggle" onclick="togglePass('password','icon1')" title="Show/hide password">
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
          <button type="button" class="pass-toggle" onclick="togglePass('confirm_password','icon2')" title="Show/hide password">
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
  input.type     = input.type === 'password' ? 'text' : 'password';
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