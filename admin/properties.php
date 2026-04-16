<?php
// ============================================================
//  admin/properties.php — Full CRUD for Property Items
// ============================================================
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/admin_layout.php';
require_role('admin');

$flash = '';
$flash_type = 'success';

// ── Helper: validate & sanitize inputs ───────────────────────
function get_property_input(): array {
    return [
        'room_id'       => (int)  ($_POST['room_id']        ?? 0),
        'property_name' => trim(  $_POST['property_name']   ?? ''),
        'category'      => trim(  $_POST['category']        ?? ''),
        'serial_no'     => trim(  $_POST['serial_no']       ?? ''),
        'quantity'      => max(1, (int)($_POST['quantity']  ?? 1)),
        'date_acquired' =>         $_POST['date_acquired']  ?? '',
        'remarks'       => trim(  $_POST['remarks']         ?? ''),
    ];
}

// ── Actions ──────────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ADD
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = get_property_input();
    if (empty($d['property_name']) || $d['room_id'] === 0) {
        $flash = 'Property name and room are required.';
        $flash_type = 'error';
    } else {
        $stmt = $pdo->prepare("INSERT INTO properties
            (room_id, property_name, category, serial_no, quantity, date_acquired, remarks)
            VALUES (?,?,?,?,?,?,?)");
        $stmt->execute(array_values($d));
        $flash = 'Property added successfully.';
    }
}

// EDIT
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['property_id'] ?? 0);
    $d  = get_property_input();
    if (empty($d['property_name']) || $d['room_id'] === 0) {
        $flash = 'Property name and room are required.';
        $flash_type = 'error';
    } else {
        $stmt = $pdo->prepare("UPDATE properties SET
            room_id=?, property_name=?, category=?, serial_no=?,
            quantity=?, date_acquired=?, remarks=?
            WHERE id=?");
        $stmt->execute([...array_values($d), $id]);
        $flash = 'Property updated successfully.';
    }
}

// DELETE
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['property_id'] ?? 0);
    $pdo->prepare("DELETE FROM properties WHERE id=?")->execute([$id]);
    $flash = 'Property deleted.';
}

// ── Fetch data ───────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$room_filter = (int)($_GET['room'] ?? 0);

$where  = [];
$params = [];
if ($search) {
    $where[]  = "(p.property_name LIKE ? OR p.category LIKE ? OR p.serial_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($room_filter) {
    $where[]  = "p.room_id = ?";
    $params[] = $room_filter;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$properties = $pdo->prepare("
    SELECT p.*, r.room_name,
           (SELECT conditions FROM property_conditions WHERE property_id = p.id ORDER BY reported_at DESC LIMIT 1) AS latest_condition
    FROM properties p
    JOIN rooms r ON p.room_id = r.id
    $where_sql
    ORDER BY p.id DESC
");
$properties->execute($params);
$properties = $properties->fetchAll();

$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();

open_layout('Properties');
?>

<?php if ($flash): ?>
  <div class="flash <?= $flash_type ?>">
    <i class="bi <?= $flash_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<!-- Top bar: search + filter + add button -->
<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">
  <form method="GET" style="display:flex;gap:.6rem;flex:1;flex-wrap:wrap;">
    <div class="search-wrap" style="flex:1;min-width:200px;">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="form-control" placeholder="Search properties…" value="<?= htmlspecialchars($search) ?>">
    </div>
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
  </form>
  <button class="btn-primary-custom" onclick="openModal('modal-add')">
    <i class="bi bi-plus-lg"></i> Add Property
  </button>
</div>

<!-- Properties table -->
<div class="card">
  <div class="card-header-custom">
    <h5><i class="bi bi-box-seam me-2" style="color:var(--blue)"></i>All Properties</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($properties) ?> record<?= count($properties) !== 1 ? 's' : '' ?></span>
  </div>
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead>
        <tr>
          <th>#</th>
          <th>Property Name</th>
          <th>Category</th>
          <th>Room</th>
          <th>Qty</th>
          <th>Serial No.</th>
          <th>Condition</th>
          <th>Date Acquired</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($properties)): ?>
          <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2.5rem;">
            No properties found. <a href="properties.php" style="color:var(--blue)">Clear filters</a>
          </td></tr>
        <?php else: ?>
          <?php foreach ($properties as $i => $p): ?>
          <tr>
            <td style="color:var(--muted);font-size:.78rem;"><?= $i + 1 ?></td>
            <td style="font-weight:600;max-width:200px;"><?= htmlspecialchars($p['property_name']) ?></td>
            <td><?= htmlspecialchars($p['category'] ?: '—') ?></td>
            <td><span style="background:var(--blue-soft);color:var(--blue);padding:.2rem .6rem;border-radius:4px;font-size:.75rem;font-weight:600;"><?= htmlspecialchars($p['room_name']) ?></span></td>
            <td><?= $p['quantity'] ?></td>
            <td style="color:var(--muted);font-family:monospace;font-size:.82rem;"><?= htmlspecialchars($p['serial_no'] ?: '—') ?></td>
            <td>
              <?php
                $cond = $p['latest_condition'];
                if ($cond === 'damaged')  echo '<span class="badge-pill badge-damaged">Damaged</span>';
                elseif ($cond === 'missing') echo '<span class="badge-pill badge-missing">Missing</span>';
                elseif ($cond === 'good') echo '<span class="badge-pill badge-good">Good</span>';
                else echo '<span style="color:var(--muted);font-size:.78rem;">Not reported</span>';
              ?>
            </td>
            <td style="color:var(--muted);">
              <?= $p['date_acquired'] ? date('M j, Y', strtotime($p['date_acquired'])) : '—' ?>
            </td>
            <td>
              <div style="display:flex;gap:.35rem;">
                <button class="btn-sm-action"
                  onclick='openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)'>
                  <i class="bi bi-pencil"></i> Edit
                </button>
                <button class="btn-sm-action danger"
                  onclick="openDeleteModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['property_name'])) ?>')">
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

<!-- ═══ ADD MODAL ═══ -->
<div class="modal-backdrop-custom" id="modal-add">
  <div class="modal-box">
    <div class="modal-header-custom">
      <h5><i class="bi bi-plus-circle me-2" style="color:var(--blue)"></i>Add New Property</h5>
      <button class="modal-close" onclick="closeModal('modal-add')">&#x2715;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body-custom">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

          <div style="grid-column:span 2">
            <label class="form-label">Property Name *</label>
            <input type="text" name="property_name" class="form-control" placeholder="e.g. Monobloc Chair" required>
          </div>

          <div style="grid-column:span 2">
            <label class="form-label">Room / Area *</label>
            <select name="room_id" class="form-select" required>
              <option value="">— Select room —</option>
              <?php foreach ($rooms as $rm): ?>
                <option value="<?= $rm['id'] ?>"><?= htmlspecialchars($rm['room_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control" placeholder="e.g. Furniture">
          </div>

          <div>
            <label class="form-label">Serial / Property No.</label>
            <input type="text" name="serial_no" class="form-control" placeholder="e.g. SCH-2024-001">
          </div>

          <div>
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" class="form-control" value="1" min="1">
          </div>

          <div>
            <label class="form-label">Date Acquired</label>
            <input type="date" name="date_acquired" class="form-control">
          </div>

          <div style="grid-column:span 2">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer-custom">
        <button type="button" class="btn-cancel" onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn-submit"><i class="bi bi-check-lg me-1"></i>Add Property</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ EDIT MODAL ═══ -->
<div class="modal-backdrop-custom" id="modal-edit">
  <div class="modal-box">
    <div class="modal-header-custom">
      <h5><i class="bi bi-pencil-square me-2" style="color:var(--blue)"></i>Edit Property</h5>
      <button class="modal-close" onclick="closeModal('modal-edit')">&#x2715;</button>
    </div>
    <form method="POST" id="edit-form">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="property_id" id="edit-id">
      <div class="modal-body-custom">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

          <div style="grid-column:span 2">
            <label class="form-label">Property Name *</label>
            <input type="text" name="property_name" id="edit-name" class="form-control" required>
          </div>

          <div style="grid-column:span 2">
            <label class="form-label">Room / Area *</label>
            <select name="room_id" id="edit-room" class="form-select" required>
              <option value="">— Select room —</option>
              <?php foreach ($rooms as $rm): ?>
                <option value="<?= $rm['id'] ?>"><?= htmlspecialchars($rm['room_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="form-label">Category</label>
            <input type="text" name="category" id="edit-cat" class="form-control">
          </div>

          <div>
            <label class="form-label">Serial / Property No.</label>
            <input type="text" name="serial_no" id="edit-serial" class="form-control">
          </div>

          <div>
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" id="edit-qty" class="form-control" min="1">
          </div>

          <div>
            <label class="form-label">Date Acquired</label>
            <input type="date" name="date_acquired" id="edit-date" class="form-control">
          </div>

          <div style="grid-column:span 2">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" id="edit-remarks" class="form-control" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer-custom">
        <button type="button" class="btn-cancel" onclick="closeModal('modal-edit')">Cancel</button>
        <button type="submit" class="btn-submit"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ DELETE MODAL ═══ -->
<div class="modal-backdrop-custom" id="modal-delete">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-header-custom">
      <h5 style="color:var(--danger)"><i class="bi bi-trash me-2"></i>Delete Property</h5>
      <button class="modal-close" onclick="closeModal('modal-delete')">&#x2715;</button>
    </div>
    <form method="POST" id="delete-form">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="property_id" id="delete-id">
      <div class="modal-body-custom">
        <p style="color:var(--muted);line-height:1.6;">
          Are you sure you want to delete <strong id="delete-name" style="color:var(--text)"></strong>?
          This will also remove all condition reports for this item. This action cannot be undone.
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
// Modal helpers
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Close on backdrop click
document.querySelectorAll('.modal-backdrop-custom').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// Populate edit modal
function openEditModal(p) {
  document.getElementById('edit-id').value      = p.id;
  document.getElementById('edit-name').value    = p.property_name;
  document.getElementById('edit-room').value    = p.room_id;
  document.getElementById('edit-cat').value     = p.category  || '';
  document.getElementById('edit-serial').value  = p.serial_no || '';
  document.getElementById('edit-qty').value     = p.quantity;
  document.getElementById('edit-date').value    = p.date_acquired || '';
  document.getElementById('edit-remarks').value = p.remarks   || '';
  openModal('modal-edit');
}

// Populate delete modal
function openDeleteModal(id, name) {
  document.getElementById('delete-id').value   = id;
  document.getElementById('delete-name').textContent = name;
  openModal('modal-delete');
}

// Auto-open modal if there was a validation error on POST
<?php if ($flash_type === 'error' && $action === 'add'): ?>
  openModal('modal-add');
<?php elseif ($flash_type === 'error' && $action === 'edit'): ?>
  openModal('modal-edit');
<?php endif; ?>
</script>

<?php close_layout(); ?>