<?php
// ============================================================
//  admin/rooms.php — Manage Rooms / Areas
// ============================================================
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/admin_layout.php';
require_role('admin');

$flash = ''; $flash_type = 'success';
$action = $_POST['action'] ?? '';

if ($action === 'add_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['room_name'] ?? '');
    $loc  = trim($_POST['location']  ?? '');
    $desc = trim($_POST['description'] ?? '');
    if (empty($name)) { $flash = 'Room name is required.'; $flash_type = 'error'; }
    else {
        $pdo->prepare("INSERT INTO rooms (room_name, location, description) VALUES (?,?,?)")
            ->execute([$name, $loc, $desc]);
        $flash = "Room \"$name\" added.";
    }
}

if ($action === 'edit_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['room_id']   ?? 0);
    $name = trim($_POST['room_name']  ?? '');
    $loc  = trim($_POST['location']   ?? '');
    $desc = trim($_POST['description']?? '');
    if (empty($name)) { $flash = 'Room name is required.'; $flash_type = 'error'; }
    else {
        $pdo->prepare("UPDATE rooms SET room_name=?, location=?, description=? WHERE id=?")
            ->execute([$name, $loc, $desc, $id]);
        $flash = 'Room updated.';
    }
}

if ($action === 'delete_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['room_id'] ?? 0);
    $pdo->prepare("DELETE FROM rooms WHERE id=?")->execute([$id]);
    $flash = 'Room deleted.';
}

$rooms = $pdo->query("
    SELECT r.*, COUNT(DISTINCT p.id) AS prop_count, COUNT(DISTINCT ra.instructor_id) AS instr_count
    FROM rooms r
    LEFT JOIN properties p ON p.room_id = r.id
    LEFT JOIN room_assignments ra ON ra.room_id = r.id
    GROUP BY r.id ORDER BY r.room_name
")->fetchAll();

open_layout('Rooms');
?>

<?php if ($flash): ?>
  <div class="flash <?= $flash_type ?>">
    <i class="bi <?= $flash_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
  <button class="btn-primary-custom" onclick="openModal('modal-add-room')">
    <i class="bi bi-plus-lg"></i> Add Room
  </button>
</div>

<div class="card">
  <div class="card-header-custom">
    <h5><i class="bi bi-door-open me-2" style="color:var(--blue)"></i>All Rooms / Areas</h5>
  </div>
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead>
        <tr><th>#</th><th>Room Name</th><th>Location</th><th>Properties</th><th>Instructors</th><th>Description</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($rooms)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2.5rem;">No rooms yet.</td></tr>
        <?php else: ?>
          <?php foreach ($rooms as $i => $rm): ?>
          <tr>
            <td style="color:var(--muted);font-size:.78rem"><?= $i+1 ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($rm['room_name']) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($rm['location'] ?: '—') ?></td>
            <td><span class="badge-pill badge-admin"><?= $rm['prop_count'] ?> items</span></td>
            <td><span class="badge-pill badge-instr"><?= $rm['instr_count'] ?> assigned</span></td>
            <td style="color:var(--muted);font-size:.82rem;max-width:200px;"><?= htmlspecialchars($rm['description'] ?: '—') ?></td>
            <td>
              <div style="display:flex;gap:.35rem;">
                <button class="btn-sm-action" onclick='openEditRoom(<?= htmlspecialchars(json_encode($rm)) ?>)'>
                  <i class="bi bi-pencil"></i> Edit
                </button>
                <button class="btn-sm-action danger" onclick="openDelRoom(<?= $rm['id'] ?>, '<?= htmlspecialchars(addslashes($rm['room_name'])) ?>')">
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

<!-- ADD -->
<div class="modal-backdrop-custom" id="modal-add-room">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-header-custom"><h5><i class="bi bi-plus-circle me-2" style="color:var(--blue)"></i>Add Room</h5><button class="modal-close" onclick="closeModal('modal-add-room')">&#x2715;</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="add_room">
      <div class="modal-body-custom" style="display:grid;gap:.9rem;">
        <div><label class="form-label">Room Name *</label><input type="text" name="room_name" class="form-control" placeholder="e.g. Computer Laboratory 1" required></div>
        <div><label class="form-label">Building / Location</label><input type="text" name="location" class="form-control" placeholder="e.g. 2nd Floor, Main Building"></div>
        <div><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2" placeholder="Optional notes…"></textarea></div>
      </div>
      <div class="modal-footer-custom"><button type="button" class="btn-cancel" onclick="closeModal('modal-add-room')">Cancel</button><button type="submit" class="btn-submit">Add Room</button></div>
    </form>
  </div>
</div>

<!-- EDIT -->
<div class="modal-backdrop-custom" id="modal-edit-room">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-header-custom"><h5><i class="bi bi-pencil-square me-2" style="color:var(--blue)"></i>Edit Room</h5><button class="modal-close" onclick="closeModal('modal-edit-room')">&#x2715;</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_room">
      <input type="hidden" name="room_id" id="er-id">
      <div class="modal-body-custom" style="display:grid;gap:.9rem;">
        <div><label class="form-label">Room Name *</label><input type="text" name="room_name" id="er-name" class="form-control" required></div>
        <div><label class="form-label">Building / Location</label><input type="text" name="location" id="er-loc" class="form-control"></div>
        <div><label class="form-label">Description</label><textarea name="description" id="er-desc" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer-custom"><button type="button" class="btn-cancel" onclick="closeModal('modal-edit-room')">Cancel</button><button type="submit" class="btn-submit">Save</button></div>
    </form>
  </div>
</div>

<!-- DELETE -->
<div class="modal-backdrop-custom" id="modal-del-room">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-header-custom"><h5 style="color:var(--danger)"><i class="bi bi-trash me-2"></i>Delete Room</h5><button class="modal-close" onclick="closeModal('modal-del-room')">&#x2715;</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="delete_room">
      <input type="hidden" name="room_id" id="dr-id">
      <div class="modal-body-custom"><p style="color:var(--muted)">Delete room <strong id="dr-name" style="color:var(--text)"></strong>? All properties assigned to this room will also be deleted.</p></div>
      <div class="modal-footer-custom"><button type="button" class="btn-cancel" onclick="closeModal('modal-del-room')">Cancel</button><button type="submit" class="btn-danger-submit">Delete</button></div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop-custom').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
function openEditRoom(r) {
  document.getElementById('er-id').value   = r.id;
  document.getElementById('er-name').value = r.room_name;
  document.getElementById('er-loc').value  = r.location  || '';
  document.getElementById('er-desc').value = r.description || '';
  openModal('modal-edit-room');
}
function openDelRoom(id, name) {
  document.getElementById('dr-id').value = id;
  document.getElementById('dr-name').textContent = name;
  openModal('modal-del-room');
}
</script>

<?php close_layout(); ?>