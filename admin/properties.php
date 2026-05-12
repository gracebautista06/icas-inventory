<?php
// ============================================================
//  admin/properties.php — Full CRUD for Property Items
// ============================================================
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role('admin');

// ── AJAX branch: ?ajax=1 → return JSON and exit ─────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    $search      = trim($_GET['q']    ?? '');
    $room_filter = (int)($_GET['room'] ?? 0);

    // ── Lookup branch: ?ajax=1&lookup=name → return autofill data ──
    // Used by the Add modal to pre-fill fields for existing items.
    if (isset($_GET['lookup'])) {
        $name = trim($_GET['lookup']);
        $row  = $pdo->prepare("SELECT category, serial_no, date_acquired FROM properties WHERE property_name = ? LIMIT 1");
        $row->execute([$name]);
        echo json_encode($row->fetch(PDO::FETCH_ASSOC) ?: null);
        exit;
    }

    // ── Autocomplete branch: ?ajax=1&suggest=term → return name list ──
    if (isset($_GET['suggest'])) {
        $term = '%' . trim($_GET['suggest']) . '%';
        $rows = $pdo->prepare("SELECT DISTINCT property_name FROM properties WHERE property_name LIKE ? ORDER BY property_name LIMIT 8");
        $rows->execute([$term]);
        echo json_encode($rows->fetchAll(PDO::FETCH_COLUMN));
        exit;
    }

    // ── Main list fetch ──────────────────────────────────────
    if ($room_filter) {
        // Specific room: show individual rows for that room
        $where = ["p.room_id = ?"];
        $params = [$room_filter];
        if ($search) {
            $where[]  = "(p.property_name LIKE ? OR p.category LIKE ? OR p.serial_no LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        $where_sql = 'WHERE ' . implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT p.id, p.property_name, p.category, p.serial_no,
                   p.quantity, p.date_acquired, p.remarks, p.room_id,
                   r.room_name,
                   (SELECT conditions FROM property_conditions
                    WHERE property_id = p.id ORDER BY reported_at DESC LIMIT 1) AS latest_condition,
                   0 AS is_grouped
            FROM properties p
            JOIN rooms r ON p.room_id = r.id
            $where_sql
            ORDER BY p.property_name ASC
        ");
        $stmt->execute($params);

    } else {
        // All rooms: GROUP BY property_name — one row per unique item, qty summed
        $where = []; $params = [];
        if ($search) {
            $where[]  = "(p.property_name LIKE ? OR p.category LIKE ? OR p.serial_no LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT
                MIN(p.id)           AS id,
                p.property_name,
                MAX(p.category)     AS category,
                MAX(p.serial_no)    AS serial_no,
                SUM(p.quantity)     AS quantity,
                MIN(p.date_acquired) AS date_acquired,
                MAX(p.remarks)      AS remarks,
                NULL                AS room_id,
                GROUP_CONCAT(DISTINCT r.room_name ORDER BY r.room_name SEPARATOR ', ') AS room_name,
                (SELECT conditions FROM property_conditions
                 WHERE property_id = MIN(p.id) ORDER BY reported_at DESC LIMIT 1) AS latest_condition,
                COUNT(DISTINCT p.room_id) AS room_count,
                1 AS is_grouped
            FROM properties p
            JOIN rooms r ON p.room_id = r.id
            $where_sql
            GROUP BY p.property_name
            ORDER BY p.property_name ASC
        ");
        $stmt->execute($params);
    }

    $rows        = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_items = array_sum(array_column($rows, 'quantity'));

    echo json_encode([
        'properties'  => $rows,
        'count'       => count($rows),
        'total_items' => $total_items,
        'grouped'     => !$room_filter,
    ]);
    exit;
}

// ── Normal page request from here ───────────────────────────
require_once '../includes/admin_layout.php';

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
    $pdo->prepare("DELETE FROM property_conditions WHERE property_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM properties WHERE id=?")->execute([$id]);
    $flash = 'Property deleted.';
}

// ── Fetch data for initial page render ───────────────────────
$search      = trim($_GET['q']    ?? '');
$room_filter = (int)($_GET['room'] ?? 0);

if ($room_filter) {
    // Specific room: individual rows
    $where = ["p.room_id = ?"];
    $params = [$room_filter];
    if ($search) {
        $where[]  = "(p.property_name LIKE ? OR p.category LIKE ? OR p.serial_no LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT p.id, p.property_name, p.category, p.serial_no,
               p.quantity, p.date_acquired, p.remarks, p.room_id,
               r.room_name,
               (SELECT conditions FROM property_conditions
                WHERE property_id = p.id ORDER BY reported_at DESC LIMIT 1) AS latest_condition,
               0 AS is_grouped
        FROM properties p
        JOIN rooms r ON p.room_id = r.id
        $where_sql
        ORDER BY p.property_name ASC
    ");
    $stmt->execute($params);

} else {
    // All rooms: grouped — one row per unique property name, qty summed
    $where = []; $params = [];
    if ($search) {
        $where[]  = "(p.property_name LIKE ? OR p.category LIKE ? OR p.serial_no LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT
            MIN(p.id)           AS id,
            p.property_name,
            MAX(p.category)     AS category,
            MAX(p.serial_no)    AS serial_no,
            SUM(p.quantity)     AS quantity,
            MIN(p.date_acquired) AS date_acquired,
            MAX(p.remarks)      AS remarks,
            NULL                AS room_id,
            GROUP_CONCAT(DISTINCT r.room_name ORDER BY r.room_name SEPARATOR ', ') AS room_name,
            (SELECT conditions FROM property_conditions
             WHERE property_id = MIN(p.id) ORDER BY reported_at DESC LIMIT 1) AS latest_condition,
            COUNT(DISTINCT p.room_id) AS room_count,
            1 AS is_grouped
        FROM properties p
        JOIN rooms r ON p.room_id = r.id
        $where_sql
        GROUP BY p.property_name
        ORDER BY p.property_name ASC
    ");
    $stmt->execute($params);
}

$properties  = $stmt->fetchAll();
$rooms       = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();
$total_qty   = array_sum(array_column($properties, 'quantity'));
$row_count   = count($properties);
$is_grouped  = !$room_filter;

open_layout('Properties');
?>

<?php if ($flash): ?>
  <div class="flash <?= $flash_type ?>">
    <i class="bi <?= $flash_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<!-- Top bar -->
<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">
  <div style="display:flex;gap:.6rem;flex:1;flex-wrap:wrap;align-items:center;">

    <div class="search-wrap" style="flex:1;min-width:200px;">
      <i class="bi bi-search"></i>
      <input type="text" id="prop-search" class="form-control"
             placeholder="Search properties…"
             value="<?= htmlspecialchars($search) ?>"
             autocomplete="off">
    </div>

    <select id="room-filter" class="form-select" style="width:auto;min-width:160px;">
      <option value="">All rooms</option>
      <?php foreach ($rooms as $rm): ?>
        <option value="<?= $rm['id'] ?>" <?= $room_filter === (int)$rm['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($rm['room_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <a id="clear-filters" href="properties.php"
       style="display:<?= ($room_filter || $search) ? 'flex' : 'none' ?>;align-items:center;gap:.25rem;color:var(--blue);font-size:.82rem;white-space:nowrap;">
      <i class="bi bi-x-circle"></i> Clear
    </a>
  </div>
  <button class="btn-primary-custom" onclick="openModal('modal-add')">
    <i class="bi bi-plus-lg"></i> Add Property
  </button>
</div>

<!-- Properties table -->
<div class="card">
  <div class="card-header-custom">
    <h5><i class="bi bi-box-seam me-2" style="color:var(--blue)"></i>
        <span id="table-heading"><?= $room_filter ? htmlspecialchars(array_values(array_filter($rooms, fn($r) => (int)$r['id'] === $room_filter))[0]['room_name'] ?? '') . ' Properties' : 'All Properties' ?></span>
    </h5>
    <span id="record-count" style="font-size:.78rem;color:var(--muted)">
      <?= number_format($total_qty) ?> item<?= $total_qty !== 1 ? 's' : '' ?>
      <span style="opacity:.5;margin:0 .25rem">&middot;</span>
      <?= $row_count ?> entr<?= $row_count !== 1 ? 'ies' : 'y' ?>
    </span>
  </div>
  <div style="overflow-x:auto;position:relative;">

    <div id="table-loading"
         style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.75);
                z-index:10;align-items:center;justify-content:center;gap:.5rem;font-size:.85rem;color:var(--muted);">
      <div style="width:1rem;height:1rem;border:2px solid var(--blue);border-top-color:transparent;
                  border-radius:50%;animation:spin .6s linear infinite;"></div>
      Loading…
    </div>
    <style>@keyframes spin{to{transform:rotate(360deg)}}</style>

    <table class="table-custom">
      <thead>
        <tr>
          <th>#</th><th>Property Name</th><th>Category</th><th>Room(s)</th>
          <th>Total Qty</th><th>Serial No.</th><th>Condition</th><th>Date Acquired</th>
          <th id="actions-col">Actions</th>
        </tr>
      </thead>
      <tbody id="properties-tbody">
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
            <td>
              <?php if ($is_grouped && ($p['room_count'] ?? 1) > 1): ?>
                <span title="<?= htmlspecialchars($p['room_name']) ?>"
                      style="background:var(--blue-soft);color:var(--blue);padding:.2rem .6rem;border-radius:4px;font-size:.75rem;font-weight:600;cursor:default;">
                  <?= $p['room_count'] ?> rooms
                </span>
              <?php else: ?>
                <span style="background:var(--blue-soft);color:var(--blue);padding:.2rem .6rem;border-radius:4px;font-size:.75rem;font-weight:600;">
                  <?= htmlspecialchars($p['room_name']) ?>
                </span>
              <?php endif; ?>
            </td>
            <td style="font-weight:<?= $is_grouped ? '700' : '400' ?>;">
              <?= $p['quantity'] ?>
              <?php if ($is_grouped && ($p['room_count'] ?? 1) > 1): ?>
                <span style="color:var(--muted);font-size:.72rem;font-weight:400"> total</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-family:monospace;font-size:.82rem;"><?= htmlspecialchars($p['serial_no'] ?: '—') ?></td>
            <td>
              <?php
                $cond = $p['latest_condition'];
                if ($cond === 'damaged')     echo '<span class="badge-pill badge-damaged">Damaged</span>';
                elseif ($cond === 'missing') echo '<span class="badge-pill badge-missing">Missing</span>';
                elseif ($cond === 'good')    echo '<span class="badge-pill badge-good">Good</span>';
                else echo '<span style="color:var(--muted);font-size:.78rem;">Not reported</span>';
              ?>
            </td>
            <td style="color:var(--muted);"><?= $p['date_acquired'] ? date('M j, Y', strtotime($p['date_acquired'])) : '—' ?></td>
            <td>
              <?php if (!$is_grouped): ?>
              <div style="display:flex;gap:.35rem;">
                <button class="btn-sm-action" onclick='openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn-sm-action danger"
                  onclick="openDeleteModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['property_name'])) ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
              <?php else: ?>
                <span style="color:var(--muted);font-size:.75rem;">Select a room to edit</span>
              <?php endif; ?>
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

          <div style="grid-column:span 2;position:relative;">
            <label class="form-label">Property Name *</label>
            <input type="text" name="property_name" id="add-name" class="form-control"
                   placeholder="e.g. Monobloc Chair" required autocomplete="off">
            <!-- Autocomplete dropdown -->
            <ul id="add-name-suggestions"
                style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;
                       background:#fff;border:1px solid var(--border);border-radius:0 0 8px 8px;
                       margin:0;padding:0;list-style:none;box-shadow:0 4px 12px rgba(0,0,0,.1);max-height:200px;overflow-y:auto;">
            </ul>
            <div id="add-autofill-badge"
                 style="display:none;margin-top:.35rem;font-size:.75rem;color:var(--blue);">
              <i class="bi bi-lightning-fill"></i> Fields auto-filled from existing record
            </div>
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
            <input type="text" name="category" id="add-cat" class="form-control" placeholder="e.g. Furniture">
          </div>

          <div>
            <label class="form-label">Serial / Property No.</label>
            <input type="text" name="serial_no" id="add-serial" class="form-control" placeholder="e.g. SCH-2024-001">
          </div>

          <div>
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" class="form-control" value="1" min="1">
          </div>

          <div>
            <label class="form-label">Date Acquired</label>
            <input type="date" name="date_acquired" id="add-date" class="form-control">
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
// ── Modal helpers ─────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-backdrop-custom').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

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

function openDeleteModal(id, name) {
  document.getElementById('delete-id').value        = id;
  document.getElementById('delete-name').textContent = name;
  openModal('modal-delete');
}

<?php if ($flash_type === 'error' && $action === 'add'): ?>
  openModal('modal-add');
<?php elseif ($flash_type === 'error' && $action === 'edit'): ?>
  openModal('modal-edit');
<?php endif; ?>

// ── Add modal: autocomplete + auto-fill ──────────────────────
(function () {
  const nameInput  = document.getElementById('add-name');
  const suggestions = document.getElementById('add-name-suggestions');
  const badge      = document.getElementById('add-autofill-badge');
  const catInput   = document.getElementById('add-cat');
  const serialInput= document.getElementById('add-serial');
  const dateInput  = document.getElementById('add-date');

  const ajaxBase = new URL(location.href);
  ajaxBase.search = '';
  ajaxBase.searchParams.set('ajax', '1');

  let suggestTimer = null;

  // Close suggestions on outside click
  document.addEventListener('click', e => {
    if (!nameInput.contains(e.target) && !suggestions.contains(e.target)) {
      suggestions.style.display = 'none';
    }
  });

  // Autocomplete suggestions while typing
  nameInput.addEventListener('input', () => {
    badge.style.display = 'none';
    clearTimeout(suggestTimer);
    const term = nameInput.value.trim();

    if (term.length < 2) { suggestions.style.display = 'none'; return; }

    suggestTimer = setTimeout(async () => {
      const url = new URL(ajaxBase);
      url.searchParams.set('suggest', term);
      const res  = await fetch(url);
      const list = await res.json();

      if (!list.length) { suggestions.style.display = 'none'; return; }

      suggestions.innerHTML = list.map(name => `
        <li style="padding:.5rem .9rem;cursor:pointer;font-size:.87rem;border-bottom:1px solid var(--border);"
            onmouseenter="this.style.background='var(--blue-soft)'"
            onmouseleave="this.style.background=''"
            data-name="${name.replace(/"/g,'&quot;')}">
          ${name}
        </li>
      `).join('');
      suggestions.style.display = 'block';

      // Click a suggestion → fill name + auto-fill other fields
      suggestions.querySelectorAll('li').forEach(li => {
        li.addEventListener('click', () => {
          nameInput.value = li.dataset.name;
          suggestions.style.display = 'none';
          autoFill(li.dataset.name);
        });
      });
    }, 250);
  });

  // Auto-fill on exact match when user leaves the field
  nameInput.addEventListener('blur', () => {
    // Small delay so suggestion clicks register first
    setTimeout(() => {
      const term = nameInput.value.trim();
      if (term) autoFill(term);
    }, 200);
  });

  // Fetch existing record and fill category, serial, date
  async function autoFill(name) {
    const url = new URL(ajaxBase);
    url.searchParams.set('lookup', name);
    const res  = await fetch(url);
    const data = await res.json();

    if (!data) { badge.style.display = 'none'; return; }

    let filled = false;
    if (data.category     && !catInput.value)    { catInput.value    = data.category;     filled = true; }
    if (data.serial_no    && !serialInput.value) { serialInput.value = data.serial_no;    filled = true; }
    if (data.date_acquired && !dateInput.value)  { dateInput.value   = data.date_acquired; filled = true; }

    badge.style.display = filled ? 'block' : 'none';
  }

  // Clear auto-fill badge when modal closes
  document.getElementById('modal-add').addEventListener('click', e => {
    if (e.target.classList.contains('modal-close') || e.target === e.currentTarget) {
      badge.style.display = 'none';
      suggestions.style.display = 'none';
    }
  });
})();

// ── Dynamic room filtering ────────────────────────────────────
(function () {
  const roomSel   = document.getElementById('room-filter');
  const searchInp = document.getElementById('prop-search');
  const tbody     = document.getElementById('properties-tbody');
  const countEl   = document.getElementById('record-count');
  const headingEl = document.getElementById('table-heading');
  const loadingEl = document.getElementById('table-loading');
  const clearLink = document.getElementById('clear-filters');

  const roomNames = {};
  [...roomSel.options].forEach(o => { if (o.value) roomNames[o.value] = o.text; });

  let debounce = null, controller = null;

  function esc(s) {
    const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML;
  }

  function badge(cond) {
    if (cond === 'damaged') return '<span class="badge-pill badge-damaged">Damaged</span>';
    if (cond === 'missing') return '<span class="badge-pill badge-missing">Missing</span>';
    if (cond === 'good')    return '<span class="badge-pill badge-good">Good</span>';
    return '<span style="color:var(--muted);font-size:.78rem;">Not reported</span>';
  }

  function fmtDate(d) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
  }

  function buildRow(p, n, grouped) {
    const json = esc(JSON.stringify(p));
    const safe = (p.property_name || '').replace(/'/g, "\\'");
    const roomCount = parseInt(p.room_count || 1);

    const roomCell = (grouped && roomCount > 1)
      ? `<span title="${esc(p.room_name)}" style="background:var(--blue-soft);color:var(--blue);padding:.2rem .6rem;border-radius:4px;font-size:.75rem;font-weight:600;cursor:default;">${roomCount} rooms</span>`
      : `<span style="background:var(--blue-soft);color:var(--blue);padding:.2rem .6rem;border-radius:4px;font-size:.75rem;font-weight:600;">${esc(p.room_name)}</span>`;

    const qtyCell = (grouped && roomCount > 1)
      ? `<strong>${p.quantity}</strong> <span style="color:var(--muted);font-size:.72rem;">total</span>`
      : p.quantity;

    const actionsCell = grouped
      ? `<span style="color:var(--muted);font-size:.75rem;">Select a room to edit</span>`
      : `<div style="display:flex;gap:.35rem;">
           <button class="btn-sm-action" onclick='openEditModal(${json})'><i class="bi bi-pencil"></i></button>
           <button class="btn-sm-action danger" onclick="openDeleteModal(${p.id},'${safe}')"><i class="bi bi-trash"></i></button>
         </div>`;

    return `<tr>
      <td style="color:var(--muted);font-size:.78rem;">${n}</td>
      <td style="font-weight:600;max-width:200px;">${esc(p.property_name)}</td>
      <td>${esc(p.category || '—')}</td>
      <td>${roomCell}</td>
      <td>${qtyCell}</td>
      <td style="color:var(--muted);font-family:monospace;font-size:.82rem;">${esc(p.serial_no || '—')}</td>
      <td>${badge(p.latest_condition)}</td>
      <td style="color:var(--muted);">${fmtDate(p.date_acquired)}</td>
      <td>${actionsCell}</td>
    </tr>`;
  }


  async function fetchProperties() {
    const room = roomSel.value;
    const q    = searchInp.value.trim();

    clearLink.style.display = (room || q) ? 'flex' : 'none';
    headingEl.textContent   = room ? (roomNames[room] + ' Properties') : 'All Properties';
    loadingEl.style.display = 'flex';

    if (controller) controller.abort();
    controller = new AbortController();

    const url = new URL(location.href);
    url.search = '';
    url.searchParams.set('ajax', '1');
    if (room) url.searchParams.set('room', room);
    if (q)    url.searchParams.set('q', q);

    try {
      const res  = await fetch(url, { signal: controller.signal });
      const data = await res.json();
      const grouped = data.grouped;

      if (data.count === 0) {
        const msg = room
          ? `No properties found in <strong>${esc(roomNames[room])}</strong>.`
          : 'No properties found. <a href="properties.php" style="color:var(--blue)">Clear filters</a>';
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2.5rem;">${msg}</td></tr>`;
      } else {
        tbody.innerHTML = data.properties.map((p, i) => buildRow(p, i + 1, grouped)).join('');
      }

      const totalItems = data.properties.reduce((s, p) => s + parseInt(p.quantity || 0), 0);
      const rowCount   = data.count;
      countEl.innerHTML =
        `${totalItems.toLocaleString()} item${totalItems !== 1 ? 's' : ''}`
        + ` <span style="opacity:.5;margin:0 .25rem">&middot;</span> `
        + `${rowCount} entr${rowCount !== 1 ? 'ies' : 'y'}`;


      const qs = new URLSearchParams();
      if (room) qs.set('room', room);
      if (q)    qs.set('q', q);
      history.replaceState(null, '', qs.toString() ? '?' + qs : location.pathname);

    } catch (err) {
      if (err.name !== 'AbortError') {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;color:var(--danger);padding:2.5rem;">
          <i class="bi bi-exclamation-triangle me-1"></i>Failed to load. Please refresh.
        </td></tr>`;
      }
    } finally {
      loadingEl.style.display = 'none';
      controller = null;
    }
  }

  roomSel.addEventListener('change', fetchProperties);
  searchInp.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(fetchProperties, 350);
  });

  if (roomSel.value || searchInp.value.trim()) {
    clearLink.style.display = 'flex';
    if (roomSel.value) headingEl.textContent = roomNames[roomSel.value] + ' Properties';
  }
})();
</script>

<?php close_layout(); ?>