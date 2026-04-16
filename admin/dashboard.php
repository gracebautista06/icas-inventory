<?php
// ============================================================
//  admin/dashboard.php — Admin Overview
// ============================================================
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/admin_layout.php';
require_role('admin');

// ── Stats ────────────────────────────────────────────────────
$total_props   = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$total_rooms   = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$total_users   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='instructor'")->fetchColumn();
$pending_rpts  = $pdo->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn();
$damaged_count = $pdo->query("SELECT COUNT(*) FROM property_conditions WHERE conditions='damaged'")->fetchColumn();
$missing_count = $pdo->query("SELECT COUNT(*) FROM property_conditions WHERE conditions='missing'")->fetchColumn();

// ── Recent condition reports ──────────────────────────────────
$recent = $pdo->query("
    SELECT pc.*, p.property_name, u.full_name AS instructor_name, r.room_name
    FROM property_conditions pc
    JOIN properties p ON pc.property_id = p.id
    JOIN users u ON pc.instructor_id = u.id
    JOIN rooms r ON p.room_id = r.id
    ORDER BY pc.reported_at DESC
    LIMIT 8
")->fetchAll();

open_layout('Dashboard');
?>

<!-- Stat cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.75rem;">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="bi bi-box-seam"></i></div>
    <div>
      <div class="stat-label">Total Properties</div>
      <div class="stat-value"><?= number_format($total_props) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber"><i class="bi bi-door-open"></i></div>
    <div>
      <div class="stat-label">Rooms / Areas</div>
      <div class="stat-value"><?= number_format($total_rooms) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="bi bi-people"></i></div>
    <div>
      <div class="stat-label">Instructors</div>
      <div class="stat-value"><?= number_format($total_users) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
    <div>
      <div class="stat-label">Pending Reports</div>
      <div class="stat-value"><?= number_format($pending_rpts) ?></div>
    </div>
  </div>
</div>

<!-- Alerts row -->
<?php if ($damaged_count > 0 || $missing_count > 0): ?>
<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
  <?php if ($damaged_count > 0): ?>
  <div class="flash error" style="margin:0;flex:1;min-width:200px;">
    <i class="bi bi-tools"></i>
    <strong><?= $damaged_count ?></strong> propert<?= $damaged_count === 1 ? 'y' : 'ies' ?> reported as damaged
  </div>
  <?php endif; ?>
  <?php if ($missing_count > 0): ?>
  <div class="flash error" style="margin:0;background:#FEF3C7;color:#92400E;flex:1;min-width:200px;">
    <i class="bi bi-question-circle"></i>
    <strong><?= $missing_count ?></strong> propert<?= $missing_count === 1 ? 'y' : 'ies' ?> reported as missing
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Recent reports table -->
<div class="card">
  <div class="card-header-custom">
    <h5><i class="bi bi-activity me-2" style="color:var(--blue)"></i>Recent Condition Reports</h5>
    <a href="reports.php" class="btn-sm-action"><i class="bi bi-arrow-right"></i> View all</a>
  </div>
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead>
        <tr>
          <th>Property</th>
          <th>Room</th>
          <th>Condition</th>
          <th>Reported By</th>
          <th>Date &amp; Time</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem;">No condition reports yet.</td></tr>
        <?php else: ?>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($r['property_name']) ?></td>
            <td><?= htmlspecialchars($r['room_name']) ?></td>
            <td>
              <?php
                $map = ['good'=>'badge-good','damaged'=>'badge-damaged','missing'=>'badge-missing'];
                $cls = $map[$r['condition']] ?? 'badge-good';
              ?>
              <span class="badge-pill <?= $cls ?>"><?= ucfirst($r['condition']) ?></span>
            </td>
            <td><?= htmlspecialchars($r['instructor_name']) ?></td>
            <td style="color:var(--muted)"><?= date('M j, Y g:i A', strtotime($r['reported_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php close_layout(); ?>