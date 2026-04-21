<?php
// ─── Shared Header + Sidebar ──────────────────────────────────────────────
// Usage: include at the top of every protected page AFTER auth check.
//
//   $page_title = 'My Room';   // set this before including
//   require_once '../includes/header.php';
//
// ─────────────────────────────────────────────────────────────────────────

$page_title   = $page_title   ?? 'ICAS Inventory';
$active_page  = $active_page  ?? '';
$user_role    = $_SESSION['role']      ?? 'instructor';
$user_name    = $_SESSION['full_name'] ?? 'User';

// Build correct relative path prefix (admin pages are one level deeper)
$depth  = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false
        || strpos($_SERVER['PHP_SELF'], '/instructor/') !== false)
        ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — ICAS Inventory</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-navy:  #0d1b2a;
            --brand-blue:  #1a4a7a;
            --brand-gold:  #c8973a;
            --sidebar-w:   240px;
            --topbar-h:    60px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #f4f7fb;
            min-height: 100vh;
        }

        /* ── Sidebar ────────────────────────────────────────────── */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--brand-navy);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform .25s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 1.2rem 1.3rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            text-decoration: none;
        }

        .sidebar-brand .logo-box {
            width: 36px; height: 36px;
            background: var(--brand-gold);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff; flex-shrink: 0;
        }

        .sidebar-brand span {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem;
            color: #fff;
            line-height: 1.2;
        }

        .sidebar-brand small {
            display: block;
            font-family: 'DM Sans', sans-serif;
            font-size: .68rem;
            color: rgba(255,255,255,.4);
            font-weight: 400;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .sidebar-nav { flex: 1; padding: 1rem 0; overflow-y: auto; }

        .nav-section-label {
            font-size: .68rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255,255,255,.3);
            padding: .6rem 1.3rem .3rem;
            margin-top: .4rem;
        }

        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: .62rem 1.3rem;
            color: rgba(255,255,255,.62);
            font-size: .88rem;
            font-weight: 500;
            border-radius: 0;
            transition: background .15s, color .15s;
            text-decoration: none;
        }

        .sidebar-nav .nav-link i { font-size: 1rem; flex-shrink: 0; }

        .sidebar-nav .nav-link:hover {
            background: rgba(255,255,255,.07);
            color: #fff;
        }

        .sidebar-nav .nav-link.active {
            background: rgba(200,151,58,.18);
            color: var(--brand-gold);
            border-right: 3px solid var(--brand-gold);
        }

        .sidebar-footer {
            padding: .9rem 1.3rem;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .user-avatar {
            width: 34px; height: 34px;
            background: var(--brand-blue);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; color: #fff; font-weight: 600; flex-shrink: 0;
        }

        .user-name {
            font-size: .82rem;
            color: rgba(255,255,255,.8);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }

        .user-role-badge {
            font-size: .65rem;
            color: rgba(255,255,255,.35);
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        /* ── Top bar ────────────────────────────────────────────── */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.6rem;
            z-index: 900;
        }

        .topbar .page-heading {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            color: var(--brand-navy);
            margin: 0;
        }

        .topbar-actions { display: flex; align-items: center; gap: .6rem; }

        .btn-logout {
            font-size: .82rem;
            font-weight: 600;
            color: #6b7a8d;
            border: 1.5px solid #dde3ed;
            border-radius: 8px;
            padding: .35rem .85rem;
            background: #fff;
            transition: all .2s;
            text-decoration: none;
        }
        .btn-logout:hover { background: #fff0f0; border-color: #f5a0a0; color: #c0392b; }

        .btn-sidebar-toggle {
            display: none;
            background: none; border: none;
            font-size: 1.3rem; color: var(--brand-navy);
            padding: 0; cursor: pointer;
        }

        /* ── Main content ───────────────────────────────────────── */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            padding: 1.8rem;
            min-height: calc(100vh - var(--topbar-h));
        }

        /* ── Stat cards ─────────────────────────────────────────── */
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.3rem 1.5rem;
            border: 1px solid #e8edf5;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 50px; height: 50px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }

        .stat-value {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--brand-navy);
            line-height: 1;
        }

        .stat-label {
            font-size: .78rem;
            color: #8a95a5;
            margin-top: 3px;
            font-weight: 500;
        }

        /* ── Tables ─────────────────────────────────────────────── */
        .card-section {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e8edf5;
            overflow: hidden;
        }

        .card-section-header {
            padding: 1rem 1.4rem;
            border-bottom: 1px solid #f0f4fa;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-section-title {
            font-weight: 700;
            font-size: .95rem;
            color: var(--brand-navy);
            margin: 0;
        }

        .table { margin: 0; font-size: .875rem; }
        .table thead th {
            background: #f8fafc;
            color: #6b7a8d;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            border-bottom: 1px solid #e8edf5;
            padding: .7rem 1rem;
        }
        .table tbody td { padding: .75rem 1rem; vertical-align: middle; color: #334155; }
        .table tbody tr:hover { background: #fafbff; }

        /* ── Badges ─────────────────────────────────────────────── */
        .badge-good    { background:#dcfce7; color:#166534; }
        .badge-damaged { background:#fef9c3; color:#854d0e; }
        .badge-missing { background:#fee2e2; color:#991b1b; }
        .badge-pending  { background:#fef3c7; color:#92400e; }
        .badge-reviewed { background:#d1fae5; color:#065f46; }

        /* ── Empty state ────────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #a0aec0;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: .8rem; display: block; }
        .empty-state p { font-size: .9rem; margin: 0; }

        /* ── Responsive ─────────────────────────────────────────── */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .topbar { left: 0; }
            .main-content { margin-left: 0; }
            .btn-sidebar-toggle { display: block; }
        }
    </style>
</head>
<body>

<!-- ── Sidebar ──────────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="<?= $depth ?>index.php">
        <div class="logo-box"><i class="bi bi-box-seam"></i></div>
        <div>
            <span>ICAS<br>Inventory</span>
            <small>Asset Management</small>
        </div>
    </a>

    <nav class="sidebar-nav">
        <?php if ($user_role === 'admin'): ?>
            <div class="nav-section-label">Admin</div>
            <a href="<?= $depth ?>admin/dashboard.php"      class="nav-link <?= $active_page === 'dashboard'   ? 'active' : '' ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="<?= $depth ?>admin/properties.php"     class="nav-link <?= $active_page === 'properties'  ? 'active' : '' ?>"><i class="bi bi-archive"></i> Properties/Items</a>
            <a href="<?= $depth ?>admin/users.php"          class="nav-link <?= $active_page === 'users'       ? 'active' : '' ?>"><i class="bi bi-people"></i> Users</a>
            <a href="<?= $depth ?>admin/reports.php"        class="nav-link <?= $active_page === 'reports'     ? 'active' : '' ?>"><i class="bi bi-file-earmark-text"></i> Reports</a>
        <?php else: ?>
            <div class="nav-section-label">Instructor</div>
            <a href="<?= $depth ?>instructor/dashboard.php"     class="nav-link <?= $active_page === 'dashboard'  ? 'active' : '' ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="<?= $depth ?>instructor/my_room.php"       class="nav-link <?= $active_page === 'my_room'    ? 'active' : '' ?>"><i class="bi bi-door-open"></i> My Room</a>
            <a href="<?= $depth ?>instructor/submit_report.php" class="nav-link <?= $active_page === 'report'     ? 'active' : '' ?>"><i class="bi bi-send"></i> Submit Report</a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="user-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
                <div class="user-role-badge"><?= ucfirst($user_role) ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- ── Top Bar ──────────────────────────────────────────────────── -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn-sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="page-heading"><?= htmlspecialchars($page_title) ?></h1>
    </div>
    <div class="topbar-actions">
        <a href="<?= $depth ?>logout.php" class="btn-logout">
            <i class="bi bi-box-arrow-right me-1"></i>Sign Out
        </a>
    </div>
</header>

<!-- ── Main Content Wrapper ─────────────────────────────────────── -->
<main class="main-content">