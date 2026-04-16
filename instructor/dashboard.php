<?php
// ─── Auth ─────────────────────────────────────────────────────────────────
require_once '../includes/auth_check.php';
require_role('instructor');
require_once '../config/db.php';

$user = current_user();
$user_id = $user['id'];
$user_name = $_SESSION['full_name'];

// ─── Fetch assigned room(s) ───────────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT ra.id AS assignment_id, r.id AS room_id, r.room_name, r.location, ra.assigned_date
    FROM room_assignments ra
    JOIN rooms r ON r.id = ra.room_id
    WHERE ra.instructor_id = ?
    ORDER BY ra.assigned_date DESC
');
$stmt->execute([$user_id]);
$assigned_rooms = $stmt->fetchAll();

// Use the first (most recent) assignment for stats
$primary_room = $assigned_rooms[0] ?? null;

// ─── Stats for primary room ───────────────────────────────────────────────
$total_properties = 0;
$good_count       = 0;
$damaged_count    = 0;
$missing_count    = 0;
$pending_reports  = 0;

if ($primary_room) {
    $rid = $primary_room['room_id'];

    // Total properties in the room
    $s = $pdo->prepare('SELECT COUNT(*) FROM properties WHERE room_id = ?');
    $s->execute([$rid]);
    $total_properties = (int) $s->fetchColumn();

    // Latest condition per property (reported by this instructor)
    $s = $pdo->prepare('
        SELECT pc.condition, COUNT(*) AS cnt
        FROM property_conditions pc
        INNER JOIN (
            SELECT property_id, MAX(reported_at) AS latest
            FROM property_conditions
            WHERE instructor_id = ?
            GROUP BY property_id
        ) latest_only ON pc.property_id = latest_only.property_id
                      AND pc.reported_at = latest_only.latest
        WHERE pc.instructor_id = ?
        GROUP BY pc.condition
    ');
    $s->execute([$user_id, $user_id]);
    foreach ($s->fetchAll() as $row) {
        if ($row['condition'] === 'good')    $good_count    = (int)$row['cnt'];
        if ($row['condition'] === 'damaged') $damaged_count = (int)$row['cnt'];
        if ($row['condition'] === 'missing') $missing_count = (int)$row['cnt'];
    }

    // Pending reports
    $s = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE instructor_id = ? AND room_id = ? AND status = "pending"');
    $s->execute([$user_id, $rid]);
    $pending_reports = (int) $s->fetchColumn();
}

// ─── Recent condition updates (this instructor, last 10) ─────────────────
$recent_updates = [];
if ($primary_room) {
    $s = $pdo->prepare('
        SELECT pc.condition, pc.notes, pc.reported_at,
               p.property_name
        FROM property_conditions pc
        JOIN properties p ON p.id = pc.property_id
        WHERE pc.instructor_id = ?
        ORDER BY pc.reported_at DESC
        LIMIT 8
    ');
    $s->execute([$user_id]);
    $recent_updates = $s->fetchAll();
}

// ─── Page meta ────────────────────────────────────────────────────────────
$page_title  = 'Dashboard';
$active_page = 'dashboard';
require_once '../includes/header.php';
?>

<!-- ── Welcome Banner ───────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold mb-1" style="font-family:'Playfair Display',serif;color:var(--brand-navy)">
            Good <?= (date('H') < 12) ? 'morning' : ((date('H') < 17) ? 'afternoon' : 'evening') ?>,
            <?= htmlspecialchars(explode(' ', $user_name)[0]) ?>!
        </h2>
        <p class="text-muted mb-0" style="font-size:.9rem">
            <?= $primary_room
                ? 'You are assigned to <strong>' . htmlspecialchars($primary_room['room_name']) . '</strong>.'
                : 'You have no room assignment yet. Please contact an administrator.' ?>
        </p>
    </div>
    <?php if ($primary_room): ?>
        <a href="my_room.php" class="btn btn-sm btn-dark rounded-pill px-3">
            <i class="bi bi-door-open me-1"></i>View My Room
        </a>
    <?php endif; ?>
</div>

<!-- ── Stat Cards ───────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff">
                <i class="bi bi-archive" style="color:#1a4a7a"></i>
            </div>
            <div>
                <div class="stat-value"><?= $total_properties ?></div>
                <div class="stat-label">Total Properties</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7">
                <i class="bi bi-check-circle" style="color:#166534"></i>
            </div>
            <div>
                <div class="stat-value"><?= $good_count ?></div>
                <div class="stat-label">Good Condition</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3">
                <i class="bi bi-exclamation-triangle" style="color:#854d0e"></i>
            </div>
            <div>
                <div class="stat-value"><?= $damaged_count ?></div>
                <div class="stat-label">Damaged</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2">
                <i class="bi bi-x-circle" style="color:#991b1b"></i>
            </div>
            <div>
                <div class="stat-value"><?= $missing_count ?></div>
                <div class="stat-label">Missing</div>
            </div>
        </div>
    </div>

</div>

<!-- ── Two-column: Recent Activity + Quick Actions ──────────────── -->
<div class="row g-3">

    <!-- Recent Condition Updates -->
    <div class="col-lg-8">
        <div class="card-section">
            <div class="card-section-header">
                <h3 class="card-section-title"><i class="bi bi-clock-history me-2 text-muted"></i>Recent Condition Updates</h3>
                <a href="my_room.php" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size:.78rem">View All</a>
            </div>

            <?php if (empty($recent_updates)): ?>
                <div class="empty-state">
                    <i class="bi bi-clipboard"></i>
                    <p>No condition updates yet.<br>Go to <a href="my_room.php">My Room</a> to start reporting.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Condition</th>
                                <th>Notes</th>
                                <th>Reported</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_updates as $u): ?>
                                <tr>
                                    <td class="fw-500"><?= htmlspecialchars($u['property_name']) ?></td>
                                    <td>
                                        <?php
                                        $badgeMap = [
                                            'good'    => 'badge-good',
                                            'damaged' => 'badge-damaged',
                                            'missing' => 'badge-missing',
                                        ];
                                        $cls = $badgeMap[$u['condition']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge rounded-pill <?= $cls ?>" style="font-size:.75rem">
                                            <?= ucfirst($u['condition']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted" style="max-width:200px">
                                        <?= $u['notes'] ? htmlspecialchars(mb_strimwidth($u['notes'], 0, 60, '…')) : '<em class="text-muted">—</em>' ?>
                                    </td>
                                    <td class="text-muted" style="white-space:nowrap;font-size:.8rem">
                                        <?= date('M j, Y', strtotime($u['reported_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions + Assigned Rooms -->
    <div class="col-lg-4 d-flex flex-column gap-3">

        <!-- Quick Actions -->
        <div class="card-section">
            <div class="card-section-header">
                <h3 class="card-section-title"><i class="bi bi-lightning me-2 text-muted"></i>Quick Actions</h3>
            </div>
            <div class="p-3 d-flex flex-column gap-2">
                <a href="my_room.php" class="btn btn-outline-primary rounded-3 text-start" style="font-size:.875rem">
                    <i class="bi bi-door-open me-2"></i>View My Room &amp; Properties
                </a>
                <a href="submit_report.php" class="btn btn-outline-dark rounded-3 text-start position-relative" style="font-size:.875rem">
                    <i class="bi bi-send me-2"></i>Submit Condition Report
                    <?php if ($pending_reports > 0): ?>
                        <span class="badge bg-danger rounded-pill position-absolute end-0 me-2"><?= $pending_reports ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Assigned Rooms list -->
        <div class="card-section">
            <div class="card-section-header">
                <h3 class="card-section-title"><i class="bi bi-building me-2 text-muted"></i>Assigned Rooms</h3>
            </div>
            <?php if (empty($assigned_rooms)): ?>
                <div class="empty-state" style="padding:1.5rem">
                    <i class="bi bi-building-slash" style="font-size:1.8rem"></i>
                    <p style="font-size:.83rem">No room assigned yet.</p>
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($assigned_rooms as $rm): ?>
                        <li class="list-group-item d-flex align-items-center gap-3 py-2" style="font-size:.875rem">
                            <div style="width:34px;height:34px;background:#f0f4ff;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="bi bi-door-open" style="color:var(--brand-blue)"></i>
                            </div>
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($rm['room_name']) ?></div>
                                <div class="text-muted" style="font-size:.78rem">
                                    <?= htmlspecialchars($rm['location'] ?? '—') ?>
                                    · Since <?= date('M Y', strtotime($rm['assigned_date'])) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>