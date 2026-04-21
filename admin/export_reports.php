<?php
// ============================================================
//  admin/export_reports.php — Export Reports as PDF or CSV
//  Usage: export_reports.php?format=pdf|csv&status=...&room=...&q=...
// ============================================================
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role('admin');

// ── Filters (mirror reports.php) ─────────────────────────────
$format        = $_GET['format']  ?? 'pdf';   // 'pdf' or 'csv'
$status_filter = $_GET['status']  ?? '';
$room_filter   = (int)($_GET['room'] ?? 0);
$search        = trim($_GET['q']  ?? '');

$where  = [];
$params = [];
if ($status_filter) { $where[] = "r.status = ?";              $params[] = $status_filter; }
if ($room_filter)   { $where[] = "r.room_id = ?";             $params[] = $room_filter; }
if ($search)        { $where[] = "(r.title LIKE ? OR u.full_name LIKE ?)";
                      $params[] = "%$search%"; $params[] = "%$search%"; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT r.id, r.title, r.status, r.submitted_at,
           u.full_name AS instructor_name,
           ro.room_name, ro.location AS room_location
    FROM reports r
    JOIN users u  ON r.instructor_id = u.id
    JOIN rooms ro ON r.room_id = ro.id
    $where_sql
    ORDER BY CASE r.status WHEN 'pending' THEN 0 ELSE 1 END, r.submitted_at DESC
");
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Summary counts ────────────────────────────────────────────
$total    = count($reports);
$pending  = count(array_filter($reports, fn($r) => $r['status'] === 'pending'));
$reviewed = $total - $pending;

$generated_at = date('F j, Y \a\t g:i A');
$filename_date = date('Y-m-d');

// ════════════════════════════════════════════════════════════
//  CSV EXPORT
// ════════════════════════════════════════════════════════════
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"reports_{$filename_date}.csv\"");
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");

    // Meta rows
    fputcsv($out, ['ICAS Inventory — Condition Reports']);
    fputcsv($out, ["Generated: $generated_at"]);
    fputcsv($out, ['Total: ' . $total, 'Pending: ' . $pending, 'Reviewed: ' . $reviewed]);
    fputcsv($out, []); // blank row

    // Header
    fputcsv($out, ['#', 'Report Title', 'Instructor', 'Room', 'Location', 'Status', 'Submitted']);

    // Rows
    foreach ($reports as $i => $r) {
        fputcsv($out, [
            $i + 1,
            $r['title'],
            $r['instructor_name'],
            $r['room_name'],
            $r['room_location'] ?? '—',
            ucfirst($r['status']),
            date('M j, Y g:i A', strtotime($r['submitted_at'])),
        ]);
    }

    fclose($out);
    exit;
}

// ════════════════════════════════════════════════════════════
//  PDF EXPORT  (pure PHP — no Composer required)
// ════════════════════════════════════════════════════════════
// Build an HTML document then stream it — the browser's
// built-in print-to-PDF handles rendering. We send a
// print-optimised HTML page with a window.print() trigger.
// This works without any PHP PDF library.

header('Content-Type: text/html; charset=UTF-8');

// Active filter label for the report header
$filter_parts = [];
if ($status_filter) $filter_parts[] = 'Status: ' . ucfirst($status_filter);
if ($room_filter) {
    $rn = $pdo->prepare("SELECT room_name FROM rooms WHERE id=?");
    $rn->execute([$room_filter]);
    $rname = $rn->fetchColumn();
    if ($rname) $filter_parts[] = 'Room: ' . htmlspecialchars($rname);
}
if ($search) $filter_parts[] = 'Search: "' . htmlspecialchars($search) . '"';
$filter_label = $filter_parts ? implode(' · ', $filter_parts) : 'All Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ICAS Reports — <?= $filename_date ?></title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 11pt;
    color: #1e293b;
    background: #fff;
    padding: 0;
  }

  /* ── Screen-only controls ── */
  .no-print {
    background: #0d1b2a;
    color: #fff;
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
  }
  .no-print span { font-size: .95rem; opacity: .85; }
  .btn-print {
    background: #c8973a;
    color: #fff;
    border: none;
    padding: .55rem 1.4rem;
    border-radius: 8px;
    font-size: .9rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: .4rem;
  }
  .btn-back {
    background: rgba(255,255,255,.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,.25);
    padding: .5rem 1.1rem;
    border-radius: 8px;
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: .35rem;
  }

  /* ── Document ── */
  .document {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 2rem 3rem;
  }

  /* ── Header ── */
  .doc-header {
    border-bottom: 3px solid #0d1b2a;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
  }
  .doc-logo {
    display: flex;
    align-items: center;
    gap: .7rem;
  }
  .logo-box {
    width: 44px; height: 44px;
    background: #0d1b2a;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #c8973a;
    font-size: 1.3rem;
    font-weight: 700;
    flex-shrink: 0;
  }
  .logo-text { line-height: 1.2; }
  .logo-text strong { font-size: 1.1rem; color: #0d1b2a; }
  .logo-text small { display: block; font-size: .72rem; color: #8a95a5; text-transform: uppercase; letter-spacing: .06em; }

  .doc-meta { text-align: right; font-size: .8rem; color: #64748b; }
  .doc-meta strong { display: block; font-size: 1rem; color: #0d1b2a; margin-bottom: .15rem; }

  /* ── Summary cards ── */
  .summary-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .75rem;
    margin-bottom: 1.5rem;
  }
  .summary-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: .8rem 1rem;
    text-align: center;
  }
  .summary-card .num {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0d1b2a;
    line-height: 1;
  }
  .summary-card .lbl {
    font-size: .72rem;
    color: #8a95a5;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-top: .2rem;
  }
  .summary-card.pending-card  { border-color: #fbbf24; background: #fffbf0; }
  .summary-card.reviewed-card { border-color: #6ee7b7; background: #f0fdf4; }
  .summary-card.pending-card  .num { color: #92400e; }
  .summary-card.reviewed-card .num { color: #065f46; }

  /* ── Filter info ── */
  .filter-bar {
    font-size: .8rem;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: .5rem 1rem;
    margin-bottom: 1.25rem;
  }

  /* ── Table ── */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
  }
  thead th {
    background: #0d1b2a;
    color: #fff;
    padding: .6rem .8rem;
    text-align: left;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
  }
  tbody tr:nth-child(even) { background: #f8fafc; }
  tbody tr.pending-row     { background: #fffbf0; }
  tbody td {
    padding: .55rem .8rem;
    border-bottom: 1px solid #f0f4fa;
    vertical-align: middle;
  }

  .badge {
    display: inline-block;
    padding: .2rem .55rem;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .03em;
  }
  .badge-pending  { background: #fef3c7; color: #92400e; }
  .badge-reviewed { background: #d1fae5; color: #065f46; }

  /* ── Footer ── */
  .doc-footer {
    margin-top: 2rem;
    padding-top: .75rem;
    border-top: 1px solid #e2e8f0;
    font-size: .75rem;
    color: #94a3b8;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .5rem;
  }

  /* ── Print styles ── */
  @media print {
    .no-print { display: none !important; }
    .document { margin: 0; padding: 0 1.5rem 2rem; max-width: 100%; }
    body { font-size: 10pt; }
    thead th { background: #0d1b2a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .summary-card.pending-card  { background: #fffbf0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .summary-card.reviewed-card { background: #f0fdf4 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-pending  { background: #fef3c7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-reviewed { background: #d1fae5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    tbody tr.pending-row { background: #fffbf0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { margin: 1.5cm; size: A4 landscape; }
  }
</style>
</head>
<body>

<!-- Screen-only toolbar -->
<div class="no-print">
  <div style="display:flex;align-items:center;gap:.75rem;">
    <a href="reports.php" class="btn-back">&#8592; Back to Reports</a>
    <span>📄 ICAS Inventory — Condition Reports Export</span>
  </div>
  <button class="btn-print" onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<div class="document">

  <!-- Header -->
  <div class="doc-header">
    <div class="doc-logo">
      <div class="logo-box">&#9723;</div>
      <div class="logo-text">
        <strong>ICAS Inventory System</strong>
        <small>Asset Management — Condition Reports</small>
      </div>
    </div>
    <div class="doc-meta">
      <strong>Instructor Condition Reports</strong>
      Generated: <?= $generated_at ?><br>
      Total records: <?= $total ?>
    </div>
  </div>

  <!-- Summary -->
  <div class="summary-row">
    <div class="summary-card">
      <div class="num"><?= $total ?></div>
      <div class="lbl">Total Reports</div>
    </div>
    <div class="summary-card pending-card">
      <div class="num"><?= $pending ?></div>
      <div class="lbl">Pending Review</div>
    </div>
    <div class="summary-card reviewed-card">
      <div class="num"><?= $reviewed ?></div>
      <div class="lbl">Reviewed</div>
    </div>
  </div>

  <!-- Filter info -->
  <div class="filter-bar">
    <strong>Filter applied:</strong> <?= $filter_label ?>
  </div>

  <!-- Table -->
  <?php if (empty($reports)): ?>
    <p style="text-align:center;color:#94a3b8;padding:2rem;">No reports match the selected filters.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:3%">#</th>
        <th style="width:30%">Report Title</th>
        <th style="width:15%">Instructor</th>
        <th style="width:15%">Room</th>
        <th style="width:12%">Location</th>
        <th style="width:10%">Status</th>
        <th style="width:15%">Submitted</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reports as $i => $r): ?>
      <tr class="<?= $r['status'] === 'pending' ? 'pending-row' : '' ?>">
        <td style="color:#94a3b8;font-size:.75rem"><?= $i + 1 ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($r['title']) ?></td>
        <td><?= htmlspecialchars($r['instructor_name']) ?></td>
        <td><?= htmlspecialchars($r['room_name']) ?></td>
        <td style="color:#64748b"><?= htmlspecialchars($r['room_location'] ?? '—') ?></td>
        <td>
          <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
        </td>
        <td style="color:#64748b">
          <?= date('M j, Y', strtotime($r['submitted_at'])) ?><br>
          <span style="font-size:.7rem"><?= date('g:i A', strtotime($r['submitted_at'])) ?></span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Footer -->
  <div class="doc-footer">
    <span>ICAS Inventory System &mdash; Confidential</span>
    <span>Exported on <?= $generated_at ?></span>
  </div>

</div>

<script>
  // Auto-trigger print dialog if ?autoprint=1 is passed
  <?php if (($_GET['autoprint'] ?? '') === '1'): ?>
  window.addEventListener('load', () => setTimeout(() => window.print(), 500));
  <?php endif; ?>
</script>

</body>
</html>
<?php exit; ?>