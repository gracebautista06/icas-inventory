<?php
// ============================================================
//  admin/users.php — Manage Instructors + Room Assignments
// ============================================================
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/admin_layout.php';
require_role('admin');

$flash      = '';
$flash_type = 'success';
$action     = $_POST['action'] ?? '';

// ── ADD instructor ────────────────────────────────────────────
if ($action === 'add_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['full_name']  ?? '');
    $email    = trim($_POST['email']      ?? '');
    $password =      $_POST['password']   ?? '';
    $role     =      $_POST['role']       ?? 'instructor';

    if (empty($name) || empty($email) || empty($password)) {
        $flash = 'All fields are required.'; $flash_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash = 'Invalid email address.'; $flash_type = 'error';
    } elseif (strlen($password) < 8) {
        $flash = 'Password must be at least 8 characters.'; $flash_type = 'error';
    } else {
        $exists = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $flash = 'Email is already registered.'; $flash_type = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?,?,?,?)");
            $stmt->execute([$name, $email, $hash, $role]);
            $flash = "User \"$name\" added successfully.";
        }
    }
}

// ── EDIT instructor ───────────────────────────────────────────
if ($action === 'edit_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['user_id']   ?? 0);
    $name = trim($_POST['full_name']  ?? '');
    $email= trim($_POST['email']      ?? '');
    $role =      $_POST['role']       ?? 'instructor';
    $pass =      $_POST['password']   ?? '';

    if (empty($name) || empty($email)) {
        $flash = 'Name and email are required.'; $flash_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash = 'Invalid email address.'; $flash_type = 'error';
    } else {
        // Check email uniqueness (excluding self)
        $dup = $pdo->prepare("SELECT id FROM users WHERE email=? AND id != ?");
        $dup->execute([$email, $id]);
        if ($dup->fetch()) {
            $flash = 'That email is already used by another account.'; $flash_type = 'error';
        } else {
            if (!empty($pass)) {
                if (strlen($pass) < 8) {
                    $flash = 'New password must be at least 8 characters.'; $flash_type = 'error';
                    goto skip_edit;
                }
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET full_name=?, email=?, role=?, password_hash=? WHERE id=?")
                    ->execute([$name, $email, $role, $hash, $id]);
            } else {
                $pdo->prepare("UPDATE users SET full_name=?, email=?, role=? WHERE id=?")
                    ->execute([$name, $email, $role, $id]);
            }
            $flash = "User updated successfully.";
        }
    }
    skip_edit:;
}

// ── DELETE user ───────────────────────────────────────────────
if ($action === 'delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['user_id'] ?? 0);
    // Prevent deleting yourself
    if ($id === (int)current_user()['id']) {
        $flash = 'You cannot delete your own account.'; $flash_type = 'error';
    } else {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        $flash = 'User deleted.';
    }
}

// ── ASSIGN room ───────────────────────────────────────────────
if ($action === 'assign_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)($_POST['instructor_id'] ?? 0);
    $rid = (int)($_POST['room_id']       ?? 0);
    if ($uid && $rid) {
        // Avoid duplicate assignment
        $dup = $pdo->prepare("SELECT id FROM room_assignments WHERE instructor_id=? AND room_id=?");
        $dup->execute([$uid, $rid]);
        if ($dup->fetch()) {
            $flash = 'This instructor is already assigned to that room.'; $flash_type = 'error';
        } else {
            $pdo->prepare("INSERT INTO room_assignments (instructor_id, room_id) VALUES (?,?)")
                ->execute([$uid, $rid]);
            $flash = 'Room assigned successfully.';
        }
    }
}

// ── REMOVE room assignment ────────────────────────────────────
if ($action === 'remove_assignment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $asgn_id = (int)($_POST['assignment_id'] ?? 0);
    $pdo->prepare("DELETE FROM room_assignments WHERE id=?")->execute([$asgn_id]);
    $flash = 'Room assignment removed.';
}

// ── Fetch users ───────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$role_filter = $_GET['role'] ?? '';

$where = []; $params = [];
if ($search) {
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($role_filter) {
    $where[] = "u.role = ?"; $params[] = $role_filter;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = $pdo->prepare("
    SELECT u.*,
        GROUP_CONCAT(r.room_name ORDER BY r.room_name SEPARATOR ', ') AS assigned_rooms,
        COUNT(DISTINCT ra.id) AS room_count
    FROM users u
    LEFT JOIN room_assignments ra ON ra.instructor_id = u.id
    LEFT JOIN rooms r ON ra.room_id = r.id
    $where_sql
    GROUP BY u.id
    ORDER BY u.role ASC, u.full_name ASC
");
$users->execute($params);
$users = $users->fetchAll();

// For the assign-room dropdown
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();

// For the assign modal — get existing assignments per user
$assignments = $pdo->query("
    SELECT ra.*, r.room_name, u.full_name AS instructor_name
    FROM room_assignments ra
    JOIN rooms r ON ra.room_id = r.id
    JOIN users u ON ra.instructor_id = u.id
    ORDER BY u.full_name, r.room_name
")->fetchAll();
// Group by instructor
$assign_map = [];
foreach ($assignments as $a) {
    $assign_map[$a['instructor_id']][] = $a;
}

open_layout('User Management');
?>

<?php if ($flash): ?>
  <div class="flash <?= $flash_type ?>">
    <i class="bi <?= $flash_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<!-- Top bar -->
<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">
  <form method="GET" style="display:flex;gap:.6rem;flex:1;flex-wrap:wrap;">
    <div class="search-wrap" style="flex:1;min-width:200px;">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="form-control" placeholder="Search by name or email…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="role" class="form-select" style="width:auto;min-width:140px;">
      <option value="">All roles</option>
      <option value="admin"      <?= $role_filter === 'admin'      ? 'selected' : '' ?>>Admin</option>
      <option value="instructor" <?= $role_filter === 'instructor' ? 'selected' : '' ?>>Instructor</option>
    </select>
    <button type="submit" class="btn-primary-custom" style="background:var(--navy)">
      <i class="bi bi-funnel"></i> Filter
    </button>
  </form>
  <button class="btn-primary-custom" onclick="openModal('modal-add-user')">
    <i class="bi bi-person-plus"></i> Add User
  </button>
</div>

<!-- Users table -->
<div class="card">
  <div class="card-header-custom">
    <h5><i class="bi bi-people me-2" style="color:var(--blue)"></i>System Users</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?></span>
  </div>
  <div style="overflow-x:auto;">
    <table class="table-custom">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Assigned Rooms</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2.5rem;">No users found.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $i => $u): ?>
          <tr>
            <td style="color:var(--muted);font-size:.78rem;"><?= $i + 1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.65rem;">
                <div class="user-avatar" style="width:30px;height:30px;font-size:.7rem;background:<?= $u['role']==='admin'?'var(--blue)':'var(--success)' ?>">
                  <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                </div>
                <span style="font-weight:600;"><?= htmlspecialchars($u['full_name']) ?></span>
              </div>
            </td>
            <td style="color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <span class="badge-pill <?= $u['role']==='admin' ? 'badge-admin' : 'badge-instr' ?>">
                <i class="bi <?= $u['role']==='admin' ? 'bi-shield-fill' : 'bi-person-fill' ?>"></i>
                <?= ucfirst($u['role']) ?>
              </span>
            </td>
            <td>
              <?php if ($u['room_count'] > 0): ?>
                <span style="font-size:.8rem;color:var(--text)"><?= htmlspecialchars($u['assigned_rooms']) ?></span>
                <?php if ($u['role'] === 'instructor'): ?>
                  <button class="btn-sm-action" style="margin-left:.35rem;"
                    onclick='openAssignModal(<?= $u["id"] ?>, "<?= htmlspecialchars(addslashes($u['full_name'])) ?>")'>
                    <i class="bi bi-plus"></i>
                  </button>
                <?php endif; ?>
              <?php elseif ($u['role'] === 'instructor'): ?>
                <button class="btn-sm-action"
                  onclick='openAssignModal(<?= $u["id"] ?>, "<?= htmlspecialchars(addslashes($u['full_name'])) ?>")'
                  style="border-style:dashed;color:var(--blue);border-color:var(--blue);">
                  <i class="bi bi-plus-circle"></i> Assign Room
                </button>
              <?php else: ?>
                <span style="color:var(--muted);font-size:.78rem;">N/A</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:.82rem;">
              <?= date('M j, Y', strtotime($u['created_at'])) ?>
            </td>
            <td>
              <div style="display:flex;gap:.35rem;">
                <button class="btn-sm-action"
                  onclick='openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)'>
                  <i class="bi bi-pencil"></i> Edit
                </button>
                <?php if ((int)$u['id'] !== (int)current_user()['id']): ?>
                <button class="btn-sm-action danger"
                  onclick="openDeleteUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
                  <i class="bi bi-trash"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══ ADD USER MODAL ═══ -->
<div class="modal-backdrop-custom" id="modal-add-user">
  <div class="modal-box">
    <div class="modal-header-custom">
      <h5><i class="bi bi-person-plus me-2" style="color:var(--blue)"></i>Add New User</h5>
      <button class="modal-close" onclick="closeModal('modal-add-user')">&#x2715;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="modal-body-custom">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

          <div style="grid-column:span 2">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Maria Santos" required>
          </div>

          <div style="grid-column:span 2">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" placeholder="instructor@school.edu" required>
          </div>

          <div>
            <label class="form-label">Role *</label>
            <select name="role" class="form-select">
              <option value="instructor">Instructor</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <div>
            <label class="form-label">Password * <span style="font-size:.7rem;color:var(--muted);text-transform:none">(min. 8 chars)</span></label>
            <input type="password" name="password" class="form-control" placeholder="Temporary password" required>
          </div>

        </div>
        <p style="font-size:.78rem;color:var(--muted);margin-top:1rem;">
          <i class="bi bi-info-circle me-1"></i>
          Instructors can self-register too. Use this to create accounts manually or to add Admins.
        </p>
      </div>
      <div class="modal-footer-custom">
        <button type="button" class="btn-cancel" onclick="closeModal('modal-add-user')">Cancel</button>
        <button type="submit" class="btn-submit"><i class="bi bi-check-lg me-1"></i>Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ EDIT USER MODAL ═══ -->
<div class="modal-backdrop-custom" id="modal-edit-user">
  <div class="modal-box">
    <div class="modal-header-custom">
      <h5><i class="bi bi-pencil-square me-2" style="color:var(--blue)"></i>Edit User</h5>
      <button class="modal-close" onclick="closeModal('modal-edit-user')">&#x2715;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="edit-uid">
      <div class="modal-body-custom">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

          <div style="grid-column:span 2">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" id="edit-uname" class="form-control" required>
          </div>

          <div style="grid-column:span 2">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" id="edit-uemail" class="form-control" required>
          </div>

          <div>
            <label class="form-label">Role</label>
            <select name="role" id="edit-urole" class="form-select">
              <option value="instructor">Instructor</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <div>
            <label class="form-label">New Password <span style="font-size:.7rem;color:var(--muted);text-transform:none">(leave blank to keep)</span></label>
            <input type="password" name="password" class="form-control" placeholder="Enter new password">
          </div>

        </div>
      </div>
      <div class="modal-footer-custom">
        <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-user')">Cancel</button>
        <button type="submit" class="btn-submit"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ DELETE USER MODAL ═══ -->
<div class="modal-backdrop-custom" id="modal-delete-user">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-header-custom">
      <h5 style="color:var(--danger)"><i class="bi bi-person-x me-2"></i>Delete User</h5>
      <button class="modal-close" onclick="closeModal('modal-delete-user')">&#x2715;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" id="del-uid">
      <div class="modal-body-custom">
        <p style="color:var(--muted);line-height:1.6;">
          Are you sure you want to delete <strong id="del-uname" style="color:var(--text)"></strong>?
          All their reports and room assignments will also be removed. This cannot be undone.
        </p>
      </div>
      <div class="modal-footer-custom">
        <button type="button" class="btn-cancel" onclick="closeModal('modal-delete-user')">Cancel</button>
        <button type="submit" class="btn-danger-submit"><i class="bi bi-trash me-1"></i>Yes, Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ ASSIGN ROOM MODAL ═══ -->
<div class="modal-backdrop-custom" id="modal-assign">
  <div class="modal-box">
    <div class="modal-header-custom">
      <h5><i class="bi bi-door-open me-2" style="color:var(--blue)"></i>Manage Room Assignments</h5>
      <button class="modal-close" onclick="closeModal('modal-assign')">&#x2715;</button>
    </div>
    <div class="modal-body-custom">
      <p style="font-size:.85rem;color:var(--muted);margin-bottom:1.2rem;">
        Assigning rooms for: <strong id="assign-uname" style="color:var(--text)"></strong>
      </p>

      <!-- Current assignments -->
      <div id="current-assignments" style="margin-bottom:1.25rem;"></div>

      <!-- Add new assignment -->
      <form method="POST">
        <input type="hidden" name="action" value="assign_room">
        <input type="hidden" name="instructor_id" id="assign-uid">
        <label class="form-label">Assign to Room</label>
        <div style="display:flex;gap:.6rem;">
          <select name="room_id" class="form-select" required>
            <option value="">— Select a room —</option>
            <?php foreach ($rooms as $rm): ?>
              <option value="<?= $rm['id'] ?>"><?= htmlspecialchars($rm['room_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-submit" style="white-space:nowrap;">
            <i class="bi bi-plus-lg me-1"></i>Assign
          </button>
        </div>
      </form>
    </div>
    <div class="modal-footer-custom">
      <button type="button" class="btn-cancel" onclick="closeModal('modal-assign')">Close</button>
    </div>
  </div>
</div>

<script>
// Existing assignments data from PHP
const assignMap = <?= json_encode($assign_map) ?>;

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-backdrop-custom').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

function openEditUserModal(u) {
  document.getElementById('edit-uid').value    = u.id;
  document.getElementById('edit-uname').value  = u.full_name;
  document.getElementById('edit-uemail').value = u.email;
  document.getElementById('edit-urole').value  = u.role;
  openModal('modal-edit-user');
}

function openDeleteUserModal(id, name) {
  document.getElementById('del-uid').value = id;
  document.getElementById('del-uname').textContent = name;
  openModal('modal-delete-user');
}

function openAssignModal(uid, name) {
  document.getElementById('assign-uid').value = uid;
  document.getElementById('assign-uname').textContent = name;

  // Render current assignments
  const box  = document.getElementById('current-assignments');
  const list = assignMap[uid] || [];
  if (list.length === 0) {
    box.innerHTML = '<p style="font-size:.82rem;color:var(--muted)">No rooms assigned yet.</p>';
  } else {
    box.innerHTML = '<p style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:.5rem;">Current assignments</p>'
      + list.map(a => `
        <div style="display:flex;align-items:center;justify-content:space-between;
                    background:var(--bg);border:1px solid var(--border);border-radius:8px;
                    padding:.55rem .8rem;margin-bottom:.4rem;">
          <span style="font-size:.85rem;font-weight:600;color:var(--text)">
            <i class="bi bi-door-open me-1" style="color:var(--blue)"></i>
            ${a.room_name}
          </span>
          <form method="POST" style="margin:0" onsubmit="return confirm('Remove this assignment?')">
            <input type="hidden" name="action" value="remove_assignment">
            <input type="hidden" name="assignment_id" value="${a.id}">
            <button type="submit" class="btn-sm-action danger" style="font-size:.75rem;padding:.2rem .5rem">
              <i class="bi bi-x-lg"></i> Remove
            </button>
          </form>
        </div>
      `).join('');
  }
  openModal('modal-assign');
}
</script>

<?php close_layout(); ?>