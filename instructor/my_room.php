<?php
// ─── Auth ─────────────────────────────────────────────────────────────────
require_once '../includes/auth_check.php';
require_role('instructor');
require_once '../config/db.php';

$user = current_user();
$user_id = $user['id'];

// ─── Fetch assigned room ──────────────────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT ra.id AS assignment_id, r.id AS room_id,
           r.room_name, r.location, r.description, ra.assigned_date
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

// ─── Handle inline condition update (AJAX-friendly POST) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_condition') {
    $property_id = (int)  ($_POST['property_id']  ?? 0);
    $condition   = trim(   $_POST['condition']    ?? '');
    $notes       = trim(   $_POST['notes']        ?? '');

    $allowed_conditions = ['good', 'damaged', 'missing'];

    if (!$property_id || !in_array($condition, $allowed_conditions)) {
        $flash_error = 'Invalid condition data submitted.';
    } else {
        // Verify the property belongs to this instructor's room
        if ($room) {
            $check = $pdo->prepare('SELECT id FROM properties WHERE id = ? AND room_id = ?');
            $check->execute([$property_id, $room['room_id']]);
            if ($check->fetch()) {
                $ins = $pdo->prepare('
                    INSERT INTO property_conditions (property_id, instructor_id, `condition`, notes)
                    VALUES (?, ?, ?, ?)
                ');
                $ins->execute([$property_id, $user_id, $condition, $notes]);
                $flash_success = 'Condition updated successfully.';
            } else {
                $flash_error = 'Property does not belong to your assigned room.';
            }
        } else {
            $flash_error = 'You have no room assignment.';
        }
    }
}

// ─── Fetch properties for the assigned room ───────────────────────────────
$properties = [];
if ($room) {
    $stmt = $pdo->prepare('
        SELECT
            p.*,
            pc.condition   AS latest_condition,
            pc.notes       AS latest_notes,
            pc.reported_at AS latest_reported_at
        FROM properties p
        LEFT JOIN (
            SELECT pc1.*
            FROM property_conditions pc1
            INNER JOIN (
                SELECT property_id, MAX(reported_at) AS latest
                FROM property_conditions
                WHERE instructor_id = ?
                GROUP BY property_id
            ) newest ON pc1.property_id = newest.property_id
                     AND pc1.reported_at = newest.latest
            WHERE pc1.instructor_id = ?
        ) pc ON pc.property_id = p.id
        WHERE p.room_id = ?
        ORDER BY p.category, p.property_name
    ');
    $stmt->execute([$user_id, $user_id, $room['room_id']]);
    $properties = $stmt->fetchAll();
}

// ─── Group properties by category ────────────────────────────────────────
$by_category = [];
foreach ($properties as $prop) {
    $cat = $prop['category'] ?: 'Uncategorized';
    $by_category[$cat][] = $prop;
}

// ─── Summary counts ───────────────────────────────────────────────────────
$count_good    = 0;
$count_damaged = 0;
$count_missing = 0;
$count_unset   = 0;
foreach ($properties as $p) {
    if     ($p['latest_condition'] === 'good')    $count_good++;
    elseif ($p['latest_condition'] === 'damaged') $count_damaged++;
    elseif ($p['latest_condition'] === 'missing') $count_missing++;
    else                                          $count_unset++;
}

// ─── Page meta ────────────────────────────────────────────────────────────
$page_title  = 'My Room';
$active_page = 'my_room';
require_once '../includes/header.php';
?>

<!-- ── Flash Messages ───────────────────────────────────────────── -->
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
<!-- ── No Assignment State ──────────────────────────────────────── -->
<div class="card-section text-center py-5">
    <i class="bi bi-building-slash" style="font-size:3rem;color:#d0d7e3;display:block;margin-bottom:1rem"></i>
    <h4 class="fw-bold" style="color:var(--brand-navy)">No Room Assigned</h4>
    <p class="text-muted mb-0">You have not been assigned to a room yet.<br>Please contact an administrator.</p>
</div>

<?php else: ?>

<!-- ── Room Info Banner ──────────────────────────────────────────── -->
<div class="card-section mb-4" style="background:var(--brand-navy);color:#fff;border:none">
    <div class="p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div style="width:54px;height:54px;background:rgba(200,151,58,.25);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">
                <i class="bi bi-door-open" style="color:var(--brand-gold)"></i>
            </div>
            <div>
                <div style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700">
                    <?= htmlspecialchars($room['room_name']) ?>
                </div>
                <div style="font-size:.83rem;opacity:.6;margin-top:2px">
                    <?php if ($room['location']): ?>
                        <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($room['location']) ?> &nbsp;·&nbsp;
                    <?php endif; ?>
                    Assigned <?= date('M j, Y', strtotime($room['assigned_date'])) ?>
                </div>
                <?php if ($room['description']): ?>
                    <div style="font-size:.82rem;opacity:.5;margin-top:3px"><?= htmlspecialchars($room['description']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <a href="submit_report.php" class="btn btn-sm rounded-pill px-3" style="background:var(--brand-gold);color:#fff;font-weight:600;border:none">
            <i class="bi bi-send me-1"></i>Submit Report
        </a>
    </div>
</div>

<!-- ── Condition Summary Badges ─────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php
    $summaries = [
        ['label' => 'Good',        'count' => $count_good,    'icon' => 'check-circle',        'bg' => '#dcfce7', 'color' => '#166534'],
        ['label' => 'Damaged',     'count' => $count_damaged, 'icon' => 'exclamation-triangle', 'bg' => '#fef9c3', 'color' => '#854d0e'],
        ['label' => 'Missing',     'count' => $count_missing, 'icon' => 'x-circle',            'bg' => '#fee2e2', 'color' => '#991b1b'],
        ['label' => 'Not Checked', 'count' => $count_unset,   'icon' => 'question-circle',     'bg' => '#f1f5f9', 'color' => '#64748b'],
    ];
    foreach ($summaries as $s):
    ?>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:<?= $s['bg'] ?>">
                    <i class="bi bi-<?= $s['icon'] ?>" style="color:<?= $s['color'] ?>"></i>
                </div>
                <div>
                    <div class="stat-value" style="color:<?= $s['color'] ?>"><?= $s['count'] ?></div>
                    <div class="stat-label"><?= $s['label'] ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ── Search & Filter Bar ──────────────────────────────────────── -->
<div class="card-section mb-4">
    <div class="p-3 d-flex align-items-center gap-2 flex-wrap">
        <div class="input-group" style="max-width:300px">
            <span class="input-group-text bg-white border-end-0" style="border-radius:9px 0 0 9px;border-color:#dde3ed">
                <i class="bi bi-search text-muted" style="font-size:.85rem"></i>
            </span>
            <input type="text" id="searchInput" class="form-control border-start-0"
                   placeholder="Search properties…"
                   style="border-radius:0 9px 9px 0;border-color:#dde3ed;font-size:.875rem"
                   oninput="filterTable()">
        </div>
        <select id="conditionFilter" class="form-select" style="max-width:170px;font-size:.875rem;border-radius:9px;border-color:#dde3ed" onchange="filterTable()">
            <option value="">All Conditions</option>
            <option value="good">Good</option>
            <option value="damaged">Damaged</option>
            <option value="missing">Missing</option>
            <option value="unchecked">Not Checked</option>
        </select>
        <div class="ms-auto text-muted" style="font-size:.8rem" id="countLabel">
            <?= count($properties) ?> properties
        </div>
    </div>
</div>

<!-- ── Properties by Category ───────────────────────────────────── -->
<?php if (empty($properties)): ?>
    <div class="card-section">
        <div class="empty-state">
            <i class="bi bi-archive"></i>
            <p>No properties found in this room.<br>Please contact an administrator to add items.</p>
        </div>
    </div>
<?php else: ?>

    <?php foreach ($by_category as $category => $items): ?>
        <div class="card-section mb-3 category-block">
            <!-- Category Header -->
            <div class="card-section-header" style="cursor:pointer" onclick="toggleCategory(this)">
                <h3 class="card-section-title">
                    <i class="bi bi-tag me-2 text-muted"></i>
                    <?= htmlspecialchars($category) ?>
                    <span class="badge bg-light text-muted ms-2" style="font-size:.75rem;font-weight:600"><?= count($items) ?></span>
                </h3>
                <i class="bi bi-chevron-down toggle-chevron" style="color:#94a3b8;transition:transform .2s"></i>
            </div>

            <!-- Properties Table -->
            <div class="table-responsive category-body">
                <table class="table table-hover" id="propTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Property Name</th>
                            <th>Serial No.</th>
                            <th>Qty</th>
                            <th>Date Acquired</th>
                            <th>Condition</th>
                            <th>Last Updated</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $prop): ?>
                            <?php
                            $cond = $prop['latest_condition'];
                            $badgeMap = [
                                'good'    => ['cls' => 'badge-good',    'label' => 'Good'],
                                'damaged' => ['cls' => 'badge-damaged', 'label' => 'Damaged'],
                                'missing' => ['cls' => 'badge-missing', 'label' => 'Missing'],
                            ];
                            $badge = $badgeMap[$cond] ?? null;
                            ?>
                            <tr class="prop-row"
                                data-name="<?= strtolower(htmlspecialchars($prop['property_name'])) ?>"
                                data-condition="<?= $cond ?? 'unchecked' ?>">
                                <td class="text-muted" style="font-size:.8rem"><?= $i + 1 ?></td>
                                <td class="fw-500"><?= htmlspecialchars($prop['property_name']) ?></td>
                                <td class="text-muted font-monospace" style="font-size:.8rem">
                                    <?= $prop['serial_no'] ? htmlspecialchars($prop['serial_no']) : '—' ?>
                                </td>
                                <td><?= (int) $prop['quantity'] ?></td>
                                <td class="text-muted" style="font-size:.82rem">
                                    <?= $prop['date_acquired'] ? date('M j, Y', strtotime($prop['date_acquired'])) : '—' ?>
                                </td>
                                <td>
                                    <?php if ($badge): ?>
                                        <span class="badge rounded-pill <?= $badge['cls'] ?>" style="font-size:.75rem">
                                            <?= $badge['label'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill" style="background:#f1f5f9;color:#94a3b8;font-size:.75rem">Not Checked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted" style="font-size:.79rem;white-space:nowrap">
                                    <?= $prop['latest_reported_at'] ? date('M j, Y', strtotime($prop['latest_reported_at'])) : '—' ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary rounded-2"
                                            style="font-size:.78rem;padding:.25rem .65rem"
                                            onclick="openConditionModal(<?= $prop['id'] ?>, '<?= htmlspecialchars(addslashes($prop['property_name'])) ?>', '<?= $cond ?? '' ?>', '<?= htmlspecialchars(addslashes($prop['latest_notes'] ?? '')) ?>')">
                                        <i class="bi bi-pencil me-1"></i>Update
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; // empty properties ?>

<?php endif; // no room assignment ?>


<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ── Condition Update Modal ──────────────────────────────────── -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="conditionModal" tabindex="-1" aria-labelledby="condModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none">

            <div class="modal-header" style="border-bottom:1px solid #f0f4fa;padding:1.2rem 1.5rem">
                <h5 class="modal-title fw-700" id="condModalLabel" style="font-family:'Playfair Display',serif;color:var(--brand-navy)">
                    Update Condition
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="my_room.php" id="conditionForm">
                <input type="hidden" name="action" value="update_condition">
                <input type="hidden" name="property_id" id="modal_property_id">

                <div class="modal-body" style="padding:1.4rem 1.5rem">

                    <p class="mb-3" style="font-size:.9rem;color:#64748b">
                        Reporting condition for: <strong id="modal_property_name" style="color:var(--brand-navy)"></strong>
                    </p>

                    <!-- Condition selector -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--brand-navy)">
                            Condition <span class="text-danger">*</span>
                        </label>
                        <div class="d-flex gap-2">
                            <?php foreach (['good' => ['Good','success','check-circle'], 'damaged' => ['Damaged','warning','exclamation-triangle'], 'missing' => ['Missing','danger','x-circle']] as $val => [$lbl, $col, $ico]): ?>
                                <label class="condition-chip flex-fill text-center" style="cursor:pointer">
                                    <input type="radio" name="condition" value="<?= $val ?>" class="d-none condition-radio" required>
                                    <div class="condition-chip-inner py-2 px-1" data-condition="<?= $val ?>">
                                        <i class="bi bi-<?= $ico ?> d-block mb-1" style="font-size:1.2rem"></i>
                                        <span style="font-size:.8rem;font-weight:600"><?= $lbl ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-1">
                        <label for="modal_notes" class="form-label" style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--brand-navy)">
                            Notes <span class="text-muted fw-400">(optional)</span>
                        </label>
                        <textarea id="modal_notes" name="notes" class="form-control" rows="3"
                                  placeholder="Describe the issue, damage, or any relevant observation…"
                                  style="border-radius:10px;border-color:#dde3ed;font-size:.875rem;resize:vertical"></textarea>
                    </div>

                </div>

                <div class="modal-footer" style="border-top:1px solid #f0f4fa;padding:.9rem 1.5rem;gap:.5rem">
                    <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal" style="font-size:.875rem">
                        Cancel
                    </button>
                    <button type="submit" class="btn rounded-3" style="background:var(--brand-navy);color:#fff;font-size:.875rem;font-weight:600">
                        <i class="bi bi-save me-1"></i>Save Condition
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- ── Condition chip styles ─────────────────────────────────────── -->
<style>
    .condition-chip-inner {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        transition: all .18s;
        color: #94a3b8;
    }

    .condition-radio:checked + .condition-chip-inner[data-condition="good"] {
        border-color: #198754; background: #dcfce7; color: #166534;
    }
    .condition-radio:checked + .condition-chip-inner[data-condition="damaged"] {
        border-color: #fd7e14; background: #fef9c3; color: #854d0e;
    }
    .condition-radio:checked + .condition-chip-inner[data-condition="missing"] {
        border-color: #dc3545; background: #fee2e2; color: #991b1b;
    }

    .condition-chip:hover .condition-chip-inner { border-color: #94a3b8; }
</style>

<script>
    // ── Open modal with property data ─────────────────────────────
    function openConditionModal(id, name, currentCondition, currentNotes) {
        document.getElementById('modal_property_id').value = id;
        document.getElementById('modal_property_name').textContent = name;
        document.getElementById('modal_notes').value = currentNotes || '';

        // Reset chip selection
        document.querySelectorAll('.condition-radio').forEach(r => r.checked = false);

        if (currentCondition) {
            const radio = document.querySelector(`.condition-radio[value="${currentCondition}"]`);
            if (radio) radio.checked = true;
        }

        new bootstrap.Modal(document.getElementById('conditionModal')).show();
    }

    // ── Search & filter ───────────────────────────────────────────
    function filterTable() {
        const query    = document.getElementById('searchInput').value.toLowerCase();
        const condFilt = document.getElementById('conditionFilter').value;
        const rows     = document.querySelectorAll('.prop-row');
        let visible    = 0;

        rows.forEach(row => {
            const name = row.dataset.name || '';
            const cond = row.dataset.condition || '';

            const nameMatch = name.includes(query);
            const condMatch = !condFilt || cond === condFilt;

            if (nameMatch && condMatch) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        const lbl = document.getElementById('countLabel');
        if (lbl) lbl.textContent = visible + ' propert' + (visible === 1 ? 'y' : 'ies');
    }

    // ── Collapse/expand category ──────────────────────────────────
    function toggleCategory(header) {
        const body    = header.nextElementSibling;
        const chevron = header.querySelector('.toggle-chevron');
        const isOpen  = body.style.display !== 'none';
        body.style.display    = isOpen ? 'none' : '';
        chevron.style.transform = isOpen ? 'rotate(-90deg)' : 'rotate(0deg)';
    }
</script>

<?php require_once '../includes/footer.php'; ?>