<?php
// ============================================================
//  admin/reports.php — View & Manage Submitted Reports
// ============================================================
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/admin_layout.php';
require_role('admin');

$flash      = '';
$flash_type = 'success';
$action     = $_POST['action'] ?? '';

// ── Mark report as reviewed ───────────────────────────────────
if ($action === 'mark_reviewed' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['report_id'] ?? 0);
    $pdo->prepare("UPDATE reports SET status='reviewed' WHERE id=?")->execute([$id]);
    $flash = 'Report marked as reviewed.';
}

// ── Mark report as pending ────────────────────────────────────
if ($action === 'mark_pending' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['report_id'] ?? 0);
    $pdo->prepare("UPDATE reports SET status='pending' WHERE id=?")->execute([$id]);
    $flash = 'Report marked as pending.';
}

// ── Delete report ─────────────────────────────────────────────
if ($action === 'delete_report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['report_id'] ?? 0);
    $pdo->prepare("DELETE FROM reports WHERE id=?")->execute([$id]);
    $flash = 'Report deleted.';
}

// ── Filters ───────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? '';
$room_filter   = (int)($_GET['room'] ?? 0);
$search        = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($status_filter) { $where[] = "r.status = ?";                           $params[] = $status_filter; }
if ($room_filter)   { $where[] = "r.room_id = ?";                          $params[] = $room_filter; }
if ($search)        { $where[] = "(r.title LIKE ? OR u.full_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Fetch reports ─────────────────────────────────────────────
$reports_stmt = $pdo->prepare("
    SELECT r.*, u.full_name AS instructor_name, ro.room_name
    FROM reports r
    JOIN users u  ON r.instructor_id = u.id
    JOIN rooms ro ON r.room_id = ro.id
    $where_sql
    ORDER BY CASE r.status WHEN 'pending' THEN 0 ELSE 1 END, r.submitted_at DESC
");
$reports_stmt->execute($params);
$reports = $reports_stmt->fetchAll();

// ── Summary counts ────────────────────────────────────────────
$total_reports  = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
$pending_count  = $pdo->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn();
$reviewed_count = $pdo->query("SELECT COUNT(*) FROM reports WHERE status='reviewed'")->fetchColumn();

// ── Rooms for filter dropdown ─────────────────────────────────
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();

// ── Export query string (carries current filters through) ─────
$export_qs = http_build_query(array_filter([
    'status' => $status_filter,
    'room'   => $room_filter ?: null,
    'q'      => $search,
]));
$export_qs = $export_qs ? '&' . $export_qs : '';

open_layout('Reports');
?>

<?php if ($flash): ?>
  <div class="flash <?= $flash_type ?>">
    <i class="bi <?= $flash_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<!-- ── Summary Stat Cards ────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.75rem;">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="bi bi-file-earmark-text"></i></div>
    <div><div class="stat-label">Total Reports</div><div class="stat-value"><?= number_format($total_reports) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
    <div><div class="stat-label">Pending Review</div><div class="stat-value"><?= number_format($pending_count) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
    <div><div class="stat-label">Reviewed</div><div class="stat-value"><?= number_format($reviewed_count) ?></div></div>
  </div>
</div>

<!-- ── Filters + Export ───────────────────────────────────────── -->
<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">

  <form method="GET" style="display:flex;gap:.6rem;flex:1;flex-wrap:wrap;">
    <div class="search-wrap" style="flex:1;min-width:200px;">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="form-control" placeholder="Search by title or instructor…"
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status" class="form-select" style="width:auto;min-width:150px;">
      <option value="">All statuses</option>
      <option value="pending"  <?= $status_filter === 'pending'  ? 'selected' : '' ?>>Pending</option>
      <option value="reviewed" <?= $status_filter === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
    </select>
    <select name="room" class="form-select" style="width:auto;min-width:160px;">
      <option value="">All rooms</option>
      <?php foreach ($rooms as $rm): ?>
        <option value="<?= $rm['id'] ?>" <?= $room_filter === (int)$rm['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($rm['room_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-primary-custom" style="background:var(--navy)">
      <i class="bi bi-funnel"></i> Filter
    </button>
    <?php if ($search || $status_filter || $room_filter): ?>
      <a href="reports.php" class="btn-cancel" style="display:flex;align-items:center;gap:.3rem;text-decoration:none;">
        <i class="bi bi-x-lg"></i> Clear
      </a>
    <?php endif; ?>
  </form>

  <!-- Export buttons — both respect the current active filters -->
  <div style="display:flex;gap:.45rem;flex-shrink:0;">
    <a href="export_reports.php?format=pdf<?= $export_qs ?>"
       target="_blank"
       title="Open a print-ready page — use browser Print › Save as PDF"
       style="display:inline-flex;align-items:center;gap:.4rem;
              padding:.46rem .9rem;border-radius:8px;font-size:.85rem;font-weight:600;
              background:#991b1b;color:#fff;text-decoration:none;border:1px solid #991b1b;
              transition:opacity .15s;"
       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
      <i class="bi bi-file-earmark-pdf"></i> Export PDF
    </a>
    <a href="export_reports.php?format=csv<?= $export_qs ?>"
       title="Download as CSV — opens in Excel / Google Sheets"
       style="display:inline-flex;align-items:center;gap:.4rem;
              padding:.46rem .9rem;border-radius:8px;font-size:.85rem;font-weight:600;
              background:#166534;color:#fff;text-decoration:none;border:1px solid #166534;
              transition:opacity .15s;"
       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
      <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </a>
  </div>

</div>

<!-- ── Reports Table ─────────────────────────────────────────── -->
<div class="card">
  <div class="card-header-custom">
    <h5><i class="bi bi-file-earmark-text me-2" style="color:var(--blue)"></i>Submitted Reports</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($reports) ?> record<?= count($reports) !== 1 ? 's' : '' ?></span>
  </div>
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead>
        <tr>
          <th>#</th>
          <th>Report Title</th>
          <th>Instructor</th>
          <th>Room</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($reports)): ?>
          <tr>
            <td colspan="7" style="text-align:center;color:var(--muted);padding:2.5rem;">
              No reports found.
              <?php if ($search || $status_filter || $room_filter): ?>
                <a href="reports.php" style="color:var(--blue)">Clear filters</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($reports as $i => $rpt): ?>
          <tr style="<?= $rpt['status'] === 'pending' ? 'background:#fffbf0;' : '' ?>">
            <td style="color:var(--muted);font-size:.78rem"><?= $i + 1 ?></td>
            <td style="font-weight:600;max-width:260px;"><?= htmlspecialchars($rpt['title']) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <div style="width:28px;height:28px;background:var(--blue-soft);border-radius:50%;
                            display:flex;align-items:center;justify-content:center;
                            font-size:.75rem;font-weight:700;color:var(--blue);flex-shrink:0;">
                  <?= strtoupper(substr($rpt['instructor_name'], 0, 1)) ?>
                </div>
                <span style="font-size:.875rem"><?= htmlspecialchars($rpt['instructor_name']) ?></span>
              </div>
            </td>
            <td>
              <span style="background:var(--blue-soft);color:var(--blue);padding:.2rem .6rem;
                           border-radius:4px;font-size:.75rem;font-weight:600;">
                <?= htmlspecialchars($rpt['room_name']) ?>
              </span>
            </td>
            <td>
              <?php if ($rpt['status'] === 'pending'): ?>
                <span class="badge-pill badge-pending"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
              <?php else: ?>
                <span class="badge-pill badge-reviewed"><i class="bi bi-check-circle me-1"></i>Reviewed</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:.82rem;white-space:nowrap;">
              <?= date('M j, Y', strtotime($rpt['submitted_at'])) ?>
              <div style="font-size:.72rem;"><?= date('g:i A', strtotime($rpt['submitted_at'])) ?></div>
            </td>
            <td>
              <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                <button class="btn-sm-action"
                        onclick="openViewModal(<?= htmlspecialchars(json_encode($rpt)) ?>)">
                  <i class="bi bi-eye"></i> View
                </button>
                <?php if ($rpt['status'] === 'pending'): ?>
                  <form method="POST" style="margin:0">
                    <input type="hidden" name="action" value="mark_reviewed">
                    <input type="hidden" name="report_id" value="<?= $rpt['id'] ?>">
                    <button type="submit" class="btn-sm-action" style="color:#166534;">
                      <i class="bi bi-check-lg"></i> Mark Reviewed
                    </button>
                  </form>
                <?php else: ?>
                  <form method="POST" style="margin:0">
                    <input type="hidden" name="action" value="mark_pending">
                    <input type="hidden" name="report_id" value="<?= $rpt['id'] ?>">
                    <button type="submit" class="btn-sm-action">
                      <i class="bi bi-arrow-counterclockwise"></i> Reopen
                    </button>
                  </form>
                <?php endif; ?>
                <button class="btn-sm-action danger"
                        onclick="openDeleteModal(<?= $rpt['id'] ?>, '<?= htmlspecialchars(addslashes($rpt['title'])) ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══ VIEW REPORT MODAL ═══ -->
<div class="modal-backdrop-custom" id="modal-view">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-header-custom">
      <h5><i class="bi bi-file-earmark-text me-2" style="color:var(--blue)"></i>Report Details</h5>
      <button class="modal-close" onclick="closeModal('modal-view')">&#x2715;</button>
    </div>
    <div class="modal-body-custom" id="view-body" style="display:grid;gap:.75rem;"></div>
    <div class="modal-footer-custom">
      <button type="button" class="btn-cancel" onclick="closeModal('modal-view')">Close</button>
    </div>
  </div>
</div>

<!-- ═══ DELETE REPORT MODAL ═══ -->
<div class="modal-backdrop-custom" id="modal-delete">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header-custom">
      <h5 style="color:var(--danger)"><i class="bi bi-trash me-2"></i>Delete Report</h5>
      <button class="modal-close" onclick="closeModal('modal-delete')">&#x2715;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="delete_report">
      <input type="hidden" name="report_id" id="del-rid">
      <div class="modal-body-custom">
        <p style="color:var(--muted);line-height:1.6;">
          Are you sure you want to delete the report
          <strong id="del-rtitle" style="color:var(--text)"></strong>?
          This action cannot be undone.
        </p>
      </div>
      <div class="modal-footer-custom">
        <button type="button" class="btn-cancel" onclick="closeModal('modal-delete')">Cancel</button>
        <button type="submit" class="btn-danger-submit"><i class="bi bi-trash me-1"></i>Yes, Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-backdrop-custom').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

function openDeleteModal(id, title) {
  document.getElementById('del-rid').value = id;
  document.getElementById('del-rtitle').textContent = title;
  openModal('modal-delete');
}

function openViewModal(rpt) {
  const statusMap = {
    pending:  '<span class="badge-pill badge-pending"><i class="bi bi-hourglass-split"></i> Pending</span>',
    reviewed: '<span class="badge-pill badge-reviewed"><i class="bi bi-check-circle"></i> Reviewed</span>',
  };
  const submitted = new Date(rpt.submitted_at).toLocaleDateString('en-US', {
    year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
  });
  document.getElementById('view-body').innerHTML = `
    <div style="display:grid;grid-template-columns:auto 1fr;gap:.4rem 1rem;font-size:.875rem;align-items:start;">
      <span style="color:var(--muted);white-space:nowrap;font-weight:600;">Title</span>
      <span style="font-weight:700;color:var(--text)">${rpt.title}</span>
      <span style="color:var(--muted);white-space:nowrap;font-weight:600;">Instructor</span>
      <span>${rpt.instructor_name}</span>
      <span style="color:var(--muted);white-space:nowrap;font-weight:600;">Room</span>
      <span>${rpt.room_name}</span>
      <span style="color:var(--muted);white-space:nowrap;font-weight:600;">Status</span>
      <span>${statusMap[rpt.status] ?? rpt.status}</span>
      <span style="color:var(--muted);white-space:nowrap;font-weight:600;">Submitted</span>
      <span style="color:var(--muted)">${submitted}</span>
    </div>
  `;
  openModal('modal-view');
}
</script>

<?php close_layout(); ?>