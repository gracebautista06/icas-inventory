<?php
// ============================================================
//  instructor/submit_report.php — Submit a Condition Report
// ============================================================
require_once '../includes/auth_check.php';
require_role('instructor');
require_once '../config/db.php';

$user    = current_user();
$user_id = $user['id'];

// ─── Fetch assigned room ──────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT ra.id AS assignment_id, r.id AS room_id, r.room_name, r.location, ra.assigned_date
    FROM room_assignments ra
    JOIN rooms r ON r.id = ra.room_id
    WHERE ra.instructor_id = ?
    ORDER BY ra.assigned_date DESC
    LIMIT 1
');
$stmt->execute([$user_id]);
$room = $stmt->fetch();

$flash_success = '';
$flash_error   = '';

// ─── Handle POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $room) {
    $title = trim($_POST['title'] ?? '');

    if (empty($title)) {
        $flash_error = 'Report title is required.';
    } else {
        $ins = $pdo->prepare("
            INSERT INTO reports (instructor_id, room_id, title, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $ins->execute([$user_id, $room['room_id'], $title]);
        $flash_success = 'Report submitted successfully. The admin will review it shortly.';
    }
}

// ─── Fetch this instructor's past reports ────────────────────
$past_reports = [];
if ($room) {
    $s = $pdo->prepare("
        SELECT r.*, ro.room_name
        FROM reports r
        JOIN rooms ro ON ro.id = r.room_id
        WHERE r.instructor_id = ?
        ORDER BY r.submitted_at DESC
        LIMIT 20
    ");
    $s->execute([$user_id]);
    $past_reports = $s->fetchAll();
}

// ─── Fetch properties with latest condition for checklist ────
$properties = [];
if ($room) {
    $s = $pdo->prepare('
        SELECT p.id, p.property_name, p.category, p.quantity,
               pc.conditions AS latest_condition, pc.notes AS latest_notes, pc.reported_at AS latest_reported_at
        FROM properties p
        LEFT JOIN (
            SELECT pc1.*
            FROM property_conditions pc1
            INNER JOIN (
                SELECT property_id, MAX(reported_at) AS latest
                FROM property_conditions
                WHERE instructor_id = ?
                GROUP BY property_id
            ) newest ON pc1.property_id = newest.property_id AND pc1.reported_at = newest.latest
            WHERE pc1.instructor_id = ?
        ) pc ON pc.property_id = p.id
        WHERE p.room_id = ?
        ORDER BY p.category, p.property_name
    ');
    $s->execute([$user_id, $user_id, $room['room_id']]);
    $properties = $s->fetchAll();
}

// ─── Page meta ────────────────────────────────────────────────
$page_title  = 'Submit Report';
$active_page = 'report';
require_once '../includes/header.php';
?>

<!-- ── Flash Messages ───────────────────────────────────────── -->
<?php if ($flash_success): ?>
    <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2 py-2 px-3 mb-4" role="alert">
        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash_success) ?></span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-danger alert-dismissible d-flex align-items-center gap-2 py-2 px-3 mb-4" role="alert">
        <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash_error) ?></span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$room): ?>
<!-- ── No Assignment State ──────────────────────────────────── -->
<div class="card-section text-center py-5">
    <i class="bi bi-building-slash" style="font-size:3rem;color:#d0d7e3;display:block;margin-bottom:1rem"></i>
    <h4 class="fw-bold" style="color:var(--brand-navy)">No Room Assigned</h4>
    <p class="text-muted mb-0">You have not been assigned to a room yet.<br>Please contact an administrator.</p>
</div>

<?php else: ?>

<div class="row g-3">

    <!-- ── LEFT: Submit Form + Property Snapshot ────────────── -->
    <div class="col-lg-7 d-flex flex-column gap-3">

        <!-- Submit Form -->
        <div class="card-section">
            <div class="card-section-header">
                <h3 class="card-section-title">
                    <i class="bi bi-send me-2 text-muted"></i>New Condition Report
                </h3>
                <span style="font-size:.78rem;color:#8a95a5">
                    <i class="bi bi-door-open me-1"></i><?= htmlspecialchars($room['room_name']) ?>
                </span>
            </div>
            <div class="p-4">
                <form method="POST" action="submit_report.php">

                    <!-- Report Title -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--brand-navy)">
                            Report Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" class="form-control"
                               placeholder="e.g. Weekly Room Inspection — April 2026"
                               style="border-radius:10px;border-color:#dde3ed;font-size:.9rem;"
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                        <div class="form-text text-muted" style="font-size:.77rem">
                            Give a short descriptive title for this inspection report.
                        </div>
                    </div>

                    <!-- Property Condition Snapshot (read-only reference) -->
                    <?php if (!empty($properties)): ?>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--brand-navy)">
                            Current Property Status
                        </label>
                        <div style="border:1px solid #dde3ed;border-radius:10px;overflow:hidden;max-height:220px;overflow-y:auto;">
                            <table class="table table-sm mb-0" style="font-size:.82rem;">
                                <thead style="position:sticky;top:0;">
                                    <tr>
                                        <th style="background:#f8fafc;color:#6b7a8d;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:.5rem .8rem;">Property</th>
                                        <th style="background:#f8fafc;color:#6b7a8d;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:.5rem .8rem;">Condition</th>
                                        <th style="background:#f8fafc;color:#6b7a8d;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:.5rem .8rem;">Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($properties as $p):
                                        $cond = $p['latest_condition'];
                                        $badgeMap = ['good' => 'badge-good', 'damaged' => 'badge-damaged', 'missing' => 'badge-missing'];
                                        $cls = $badgeMap[$cond] ?? null;
                                    ?>
                                    <tr>
                                        <td style="padding:.45rem .8rem;color:#334155;font-weight:500;"><?= htmlspecialchars($p['property_name']) ?></td>
                                        <td style="padding:.45rem .8rem;">
                                            <?php if ($cls): ?>
                                                <span class="badge rounded-pill <?= $cls ?>" style="font-size:.72rem"><?= ucfirst($cond) ?></span>
                                            <?php else: ?>
                                                <span style="color:#a0aec0;font-size:.78rem;font-style:italic">Not reported</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:.45rem .8rem;color:#a0aec0;font-size:.78rem;">
                                            <?= $p['latest_reported_at'] ? date('M j, Y', strtotime($p['latest_reported_at'])) : '—' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-text text-muted" style="font-size:.77rem">
                            This reflects your latest reported conditions. <a href="my_room.php">Update conditions</a> before submitting if needed.
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 justify-content-end mt-3">
                        <a href="dashboard.php" class="btn btn-outline-secondary rounded-3" style="font-size:.875rem">
                            Cancel
                        </a>
                        <button type="submit" class="btn rounded-3 px-4"
                                style="background:var(--brand-navy);color:#fff;font-size:.875rem;font-weight:600">
                            <i class="bi bi-send me-1"></i>Submit Report
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>

    <!-- ── RIGHT: Past Reports ─────────────────────────────── -->
    <div class="col-lg-5">
        <div class="card-section">
            <div class="card-section-header">
                <h3 class="card-section-title">
                    <i class="bi bi-clock-history me-2 text-muted"></i>My Past Reports
                </h3>
                <span style="font-size:.78rem;color:#8a95a5"><?= count($past_reports) ?> total</span>
            </div>

            <?php if (empty($past_reports)): ?>
                <div class="empty-state">
                    <i class="bi bi-file-earmark-text"></i>
                    <p>No reports submitted yet.</p>
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($past_reports as $rpt):
                        $statusMap = [
                            'pending'  => ['badge-pending',  'bi-hourglass-split', 'Pending'],
                            'reviewed' => ['badge-reviewed', 'bi-check-circle',    'Reviewed'],
                        ];
                        [$sCls, $sIco, $sLbl] = $statusMap[$rpt['status']] ?? ['bg-secondary text-white', 'bi-question', $rpt['status']];
                    ?>
                        <li class="list-group-item py-3 px-4" style="border-color:#f0f4fa;">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div style="min-width:0">
                                    <div class="fw-600" style="font-size:.875rem;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($rpt['title']) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.76rem;margin-top:2px;">
                                        <i class="bi bi-door-open me-1"></i><?= htmlspecialchars($rpt['room_name']) ?>
                                        &nbsp;·&nbsp;
                                        <?= date('M j, Y', strtotime($rpt['submitted_at'])) ?>
                                    </div>
                                </div>
                                <span class="badge rounded-pill <?= $sCls ?>" style="font-size:.72rem;white-space:nowrap;flex-shrink:0">
                                    <i class="bi <?= $sIco ?> me-1"></i><?= $sLbl ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>