<?php
// ============================================================
//  includes/admin_layout.php
//  Usage: call open_layout('Page Title') to output <head>
//         + sidebar + open the main content wrapper.
//         Call close_layout() at the end of every admin page.
// ============================================================

function open_layout(string $page_title): void
{
    $user      = current_user();
    $base      = '../';                       // path back to root from /admin/
    $nav_items = [
        ['href' => 'dashboard.php',  'icon' => 'bi-speedometer2',   'label' => 'Dashboard'],
        ['href' => 'properties.php', 'icon' => 'bi-box-seam',       'label' => 'Properties'],
        ['href' => 'rooms.php',      'icon' => 'bi-door-open',      'label' => 'Rooms'],
        ['href' => 'users.php',      'icon' => 'bi-people',         'label' => 'Users'],
        ['href' => 'reports.php',    'icon' => 'bi-clipboard-data', 'label' => 'Reports'],
    ];
    $current = basename($_SERVER['PHP_SELF']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ICAS Admin — <?= htmlspecialchars($page_title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy:       #0F1F3D;
      --navy-light: #1B2E52;
      --navy-mid:   #243656;
      --blue:       #2563EB;
      --blue-soft:  #EFF6FF;
      --accent:     #F59E0B;
      --accent-soft:#FEF3C7;
      --success:    #059669;
      --danger:     #DC2626;
      --warning:    #D97706;
      --text:       #1E293B;
      --muted:      #64748B;
      --border:     #E2E8F0;
      --bg:         #F1F5F9;
      --card:       #FFFFFF;
      --sidebar-w:  240px;
      --radius:     12px;
      --radius-sm:  8px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      display: flex;
      min-height: 100vh;
      font-size: 14px;
    }

    /* ── Sidebar ── */
    .sidebar {
      width: var(--sidebar-w);
      min-height: 100vh;
      background: var(--navy);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; left: 0;
      z-index: 100;
      transition: transform .25s;
    }
    .sidebar-logo {
      padding: 1.5rem 1.4rem 1.2rem;
      border-bottom: 1px solid rgba(255,255,255,.07);
    }
    .sidebar-logo .logo-text {
      font-size: 1.4rem;
      font-weight: 700;
      color: #fff;
      letter-spacing: -.02em;
    }
    .sidebar-logo .logo-sub {
      font-size: .68rem;
      font-weight: 500;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: rgba(255,255,255,.4);
      margin-top: 2px;
    }
    .sidebar-badge {
      display: inline-block;
      background: rgba(245,158,11,.2);
      color: var(--accent);
      font-size: .65rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      padding: .2rem .5rem;
      border-radius: 4px;
      margin-top: .5rem;
    }
    .sidebar-nav {
      flex: 1;
      padding: 1rem 0;
      overflow-y: auto;
    }
    .nav-section-label {
      padding: .6rem 1.4rem .3rem;
      font-size: .65rem;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: rgba(255,255,255,.28);
    }
    .nav-link {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .65rem 1.4rem;
      color: rgba(255,255,255,.6);
      text-decoration: none;
      font-size: .85rem;
      font-weight: 500;
      border-radius: 0;
      transition: color .15s, background .15s;
      position: relative;
    }
    .nav-link .bi { font-size: 1.05rem; }
    .nav-link:hover {
      color: #fff;
      background: rgba(255,255,255,.06);
    }
    .nav-link.active {
      color: #fff;
      background: rgba(37,99,235,.35);
    }
    .nav-link.active::before {
      content: '';
      position: absolute;
      left: 0; top: 6px; bottom: 6px;
      width: 3px;
      background: var(--blue);
      border-radius: 0 3px 3px 0;
    }
    .sidebar-footer {
      padding: 1rem 1.4rem;
      border-top: 1px solid rgba(255,255,255,.07);
    }
    .user-chip {
      display: flex;
      align-items: center;
      gap: .65rem;
    }
    .user-avatar {
      width: 32px; height: 32px;
      background: var(--blue);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .78rem;
      font-weight: 700;
      color: #fff;
      flex-shrink: 0;
    }
    .user-info .user-name {
      font-size: .8rem;
      font-weight: 600;
      color: rgba(255,255,255,.85);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 130px;
    }
    .user-info .user-role {
      font-size: .68rem;
      color: rgba(255,255,255,.38);
      text-transform: uppercase;
      letter-spacing: .06em;
    }
    .logout-btn {
      display: flex;
      align-items: center;
      gap: .5rem;
      color: rgba(255,255,255,.4);
      text-decoration: none;
      font-size: .78rem;
      margin-top: .75rem;
      padding: .4rem .5rem;
      border-radius: 6px;
      transition: color .15s, background .15s;
    }
    .logout-btn:hover { color: #fff; background: rgba(220,38,38,.25); }

    /* ── Main content area ── */
    .main-content {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    .topbar {
      background: var(--card);
      border-bottom: 1px solid var(--border);
      padding: .9rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 50;
    }
    .topbar-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--text);
    }
    .topbar-breadcrumb {
      font-size: .78rem;
      color: var(--muted);
      margin-top: 1px;
    }
    .page-body {
      padding: 1.75rem 2rem;
      flex: 1;
    }

    /* ── Cards ── */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 1px 4px rgba(0,0,0,.04);
    }
    .card-header-custom {
      padding: 1.1rem 1.4rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .75rem;
    }
    .card-header-custom h5 {
      font-size: .95rem;
      font-weight: 700;
      color: var(--text);
      margin: 0;
    }
    .card-body-custom { padding: 1.4rem; }

    /* ── Stat cards ── */
    .stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.2rem 1.4rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .stat-icon {
      width: 48px; height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      flex-shrink: 0;
    }
    .stat-icon.blue   { background: var(--blue-soft);  color: var(--blue); }
    .stat-icon.amber  { background: var(--accent-soft); color: var(--accent); }
    .stat-icon.green  { background: #DCFCE7; color: var(--success); }
    .stat-icon.red    { background: #FEE2E2; color: var(--danger); }
    .stat-label { font-size: .75rem; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .06em; }
    .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; color: var(--text); margin-top: .15rem; }

    /* ── Table ── */
    .table-custom { width: 100%; border-collapse: collapse; }
    .table-custom thead th {
      background: var(--bg);
      padding: .65rem 1rem;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--muted);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    .table-custom tbody td {
      padding: .8rem 1rem;
      border-bottom: 1px solid var(--border);
      font-size: .85rem;
      vertical-align: middle;
    }
    .table-custom tbody tr:last-child td { border-bottom: none; }
    .table-custom tbody tr:hover { background: #FAFAFA; }

    /* ── Badges ── */
    .badge-pill {
      display: inline-flex;
      align-items: center;
      gap: .3rem;
      padding: .25rem .7rem;
      border-radius: 999px;
      font-size: .72rem;
      font-weight: 600;
    }
    .badge-good    { background: #DCFCE7; color: #166534; }
    .badge-damaged { background: #FEE2E2; color: #991B1B; }
    .badge-missing { background: #FEF3C7; color: #92400E; }
    .badge-admin   { background: var(--blue-soft); color: #1E40AF; }
    .badge-instr   { background: #F0FDF4; color: #166534; }

    /* ── Buttons ── */
    .btn-primary-custom {
      background: var(--blue);
      color: #fff;
      border: none;
      border-radius: var(--radius-sm);
      padding: .5rem 1rem;
      font-size: .83rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      text-decoration: none;
      transition: background .15s;
    }
    .btn-primary-custom:hover { background: #1D4ED8; color: #fff; }
    .btn-sm-action {
      padding: .3rem .6rem;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--muted);
      font-size: .8rem;
      cursor: pointer;
      transition: all .15s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .25rem;
    }
    .btn-sm-action:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-soft); }
    .btn-sm-action.danger:hover { border-color: var(--danger); color: var(--danger); background: #FEE2E2; }

    /* ── Form controls ── */
    .form-control, .form-select {
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      padding: .55rem .9rem;
      font-size: .88rem;
      font-family: 'Plus Jakarta Sans', sans-serif;
      transition: border-color .2s, box-shadow .2s;
      width: 100%;
      color: var(--text);
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(37,99,235,.1);
      outline: none;
    }
    .form-label {
      font-size: .78rem;
      font-weight: 600;
      color: #374151;
      text-transform: uppercase;
      letter-spacing: .05em;
      display: block;
      margin-bottom: .35rem;
    }

    /* ── Modal ── */
    .modal-backdrop-custom {
      position: fixed; inset: 0;
      background: rgba(15,31,61,.55);
      z-index: 200;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      opacity: 0;
      pointer-events: none;
      transition: opacity .2s;
    }
    .modal-backdrop-custom.open { opacity: 1; pointer-events: all; }
    .modal-box {
      background: var(--card);
      border-radius: var(--radius);
      width: 100%;
      max-width: 560px;
      max-height: 90vh;
      overflow-y: auto;
      transform: translateY(16px);
      transition: transform .2s;
      box-shadow: 0 24px 64px rgba(0,0,0,.2);
    }
    .modal-backdrop-custom.open .modal-box { transform: translateY(0); }
    .modal-header-custom {
      padding: 1.2rem 1.5rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .modal-header-custom h5 { font-size: 1rem; font-weight: 700; margin: 0; }
    .modal-close {
      background: none; border: none; font-size: 1.2rem;
      color: var(--muted); cursor: pointer; line-height: 1;
    }
    .modal-body-custom  { padding: 1.5rem; }
    .modal-footer-custom {
      padding: 1rem 1.5rem;
      border-top: 1px solid var(--border);
      display: flex;
      justify-content: flex-end;
      gap: .6rem;
    }
    .btn-cancel {
      background: var(--bg); border: 1px solid var(--border);
      border-radius: var(--radius-sm); padding: .5rem 1.1rem;
      font-size: .85rem; font-weight: 600; cursor: pointer;
      color: var(--muted); transition: border-color .15s;
    }
    .btn-cancel:hover { border-color: var(--muted); }
    .btn-submit {
      background: var(--blue); color: #fff; border: none;
      border-radius: var(--radius-sm); padding: .5rem 1.2rem;
      font-size: .85rem; font-weight: 600; cursor: pointer;
      transition: background .15s;
    }
    .btn-submit:hover { background: #1D4ED8; }
    .btn-danger-submit {
      background: var(--danger); color: #fff; border: none;
      border-radius: var(--radius-sm); padding: .5rem 1.2rem;
      font-size: .85rem; font-weight: 600; cursor: pointer;
    }

    /* ── Search bar ── */
    .search-wrap { position: relative; }
    .search-wrap .bi {
      position: absolute; left: 10px; top: 50%;
      transform: translateY(-50%); color: var(--muted); font-size: .9rem;
    }
    .search-wrap input { padding-left: 2.2rem; }

    /* ── Alert flash ── */
    .flash {
      padding: .7rem 1rem;
      border-radius: var(--radius-sm);
      font-size: .85rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: .5rem;
      margin-bottom: 1.25rem;
    }
    .flash.success { background: #DCFCE7; color: #166534; }
    .flash.error   { background: #FEE2E2; color: #991B1B; }

    /* ── Responsive ── */
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); }
      .main-content { margin-left: 0; }
    }
  </style>
</head>
<body>

<!-- ══ Sidebar ══ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text">ICAS</div>
    <div class="logo-sub">Property Inventory</div>
    <div class="sidebar-badge"><i class="bi bi-shield-fill me-1"></i>Admin Panel</div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <?php foreach ($nav_items as $item): ?>
      <a href="<?= $item['href'] ?>" class="nav-link <?= $current === $item['href'] ? 'active' : '' ?>">
        <i class="bi <?= $item['icon'] ?>"></i>
        <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="user-role">Administrator</div>
      </div>
    </div>
    <a href="../logout.php" class="logout-btn">
      <i class="bi bi-box-arrow-left"></i> Sign out
    </a>
  </div>
</aside>

<!-- ══ Main ══ -->
<div class="main-content">
  <header class="topbar">
    <div>
      <div class="topbar-title"><?= htmlspecialchars($page_title) ?></div>
      <div class="topbar-breadcrumb">Admin / <?= htmlspecialchars($page_title) ?></div>
    </div>
    <div style="font-size:.82rem;color:var(--muted)">
      <i class="bi bi-calendar3 me-1"></i><?= date('F j, Y') ?>
    </div>
  </header>
  <div class="page-body">
<?php
} // end open_layout()

function close_layout(): void
{
    ?>
  </div><!-- /page-body -->
</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
} // end close_layout()
?>