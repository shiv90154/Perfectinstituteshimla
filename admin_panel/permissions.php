<?php
// permissions.php - Secure role & permission management with prepared statements and CSRF
$pageTitle = 'Permission Details';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
if (!$conn) {
    die("Database connection not established.");
}

if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_roles')) {
    header("Location: unauthorized.php");
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== AUTO‑SEED SIDEBAR PERMISSIONS ====================
$sidebar_permissions = [
    ['permission_key' => 'view_dashboard',           'description' => 'View Dashboard'],
    ['permission_key' => 'manage_registrations',     'description' => 'Manage Registrations'],
    ['permission_key' => 'manage_rejoined_students', 'description' => 'View Rejoined Students'],
    ['permission_key' => 'manage_payments',          'description' => 'Manage Payments'],
    ['permission_key' => 'manage_content',           'description' => 'Manage Content (Blogs & Videos)'],
    ['permission_key' => 'manage_leads',             'description' => 'Manage Leads'],
    ['permission_key' => 'manage_inquiries',         'description' => 'Manage Inquiries'],
    ['permission_key' => 'manage_courses',           'description' => 'Manage Courses'],
    ['permission_key' => 'manage_finance',           'description' => 'Manage Finance'],
    ['permission_key' => 'manage_admins',            'description' => 'Manage Admin Users'],
    ['permission_key' => 'manage_roles',             'description' => 'Manage Roles & Permissions']
];

foreach ($sidebar_permissions as $perm) {
    $checkStmt = $conn->prepare("SELECT id FROM permissions WHERE permission_key = ?");
    $checkStmt->bind_param('s', $perm['permission_key']);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows == 0) {
        $insStmt = $conn->prepare("INSERT INTO permissions (permission_key, description) VALUES (?, ?)");
        $insStmt->bind_param('ss', $perm['permission_key'], $perm['description']);
        $insStmt->execute();
        $insStmt->close();
    }
    $checkStmt->close();
}

// ==================== AJAX HANDLERS ====================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_roles')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'];

    // ---------- ROLE HANDLERS ----------
    if ($action === 'role_get') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT id, role_name FROM roles WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'role' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Role not found']);
        }
        exit;
    }

    if ($action === 'role_add') {
        $role_name = trim($_POST['role_name'] ?? '');
        if (empty($role_name)) {
            echo json_encode(['success' => false, 'message' => 'Role name required']);
            exit;
        }
        $checkStmt = $conn->prepare("SELECT id FROM roles WHERE role_name = ?");
        $checkStmt->bind_param('s', $role_name);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Role already exists']);
            exit;
        }
        $checkStmt->close();

        $insStmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
        $insStmt->bind_param('s', $role_name);
        if ($insStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Role added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add role']);
        }
        $insStmt->close();
        exit;
    }

    if ($action === 'role_edit') {
        $id = (int)($_POST['id'] ?? 0);
        $role_name = trim($_POST['role_name'] ?? '');
        if ($id <= 0 || empty($role_name)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        $checkStmt = $conn->prepare("SELECT id FROM roles WHERE role_name = ? AND id != ?");
        $checkStmt->bind_param('si', $role_name, $id);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Role name already exists']);
            exit;
        }
        $checkStmt->close();

        $updStmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE id = ?");
        $updStmt->bind_param('si', $role_name, $id);
        if ($updStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update role']);
        }
        $updStmt->close();
        exit;
    }

    if ($action === 'role_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $delStmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
        $delStmt->bind_param('i', $id);
        $delStmt->execute();
        if ($delStmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Role not found']);
        }
        $delStmt->close();
        exit;
    }

    // ---------- PERMISSION HANDLERS ----------
    if ($action === 'permission_get') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT id, permission_key, description FROM permissions WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'permission' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Permission not found']);
        }
        exit;
    }

    if ($action === 'permission_add') {
        $key = trim($_POST['permission_key'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (empty($key)) {
            echo json_encode(['success' => false, 'message' => 'Permission key required']);
            exit;
        }
        $checkStmt = $conn->prepare("SELECT id FROM permissions WHERE permission_key = ?");
        $checkStmt->bind_param('s', $key);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Permission key already exists']);
            exit;
        }
        $checkStmt->close();

        $insStmt = $conn->prepare("INSERT INTO permissions (permission_key, description) VALUES (?, ?)");
        $insStmt->bind_param('ss', $key, $desc);
        if ($insStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Permission added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add permission']);
        }
        $insStmt->close();
        exit;
    }

    if ($action === 'permission_edit') {
        $id = (int)($_POST['id'] ?? 0);
        $key = trim($_POST['permission_key'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($id <= 0 || empty($key)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        $checkStmt = $conn->prepare("SELECT id FROM permissions WHERE permission_key = ? AND id != ?");
        $checkStmt->bind_param('si', $key, $id);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Permission key already exists']);
            exit;
        }
        $checkStmt->close();

        $updStmt = $conn->prepare("UPDATE permissions SET permission_key = ?, description = ? WHERE id = ?");
        $updStmt->bind_param('ssi', $key, $desc, $id);
        if ($updStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update permission']);
        }
        $updStmt->close();
        exit;
    }

    if ($action === 'permission_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $delStmt = $conn->prepare("DELETE FROM permissions WHERE id = ?");
        $delStmt->bind_param('i', $id);
        $delStmt->execute();
        if ($delStmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Permission deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Permission not found']);
        }
        $delStmt->close();
        exit;
    }

    // ---------- PERMISSION TOGGLE ----------
    if ($action === 'permission_toggle') {
        $role_id = (int)($_POST['role_id'] ?? 0);
        $perm_id = (int)($_POST['permission_id'] ?? 0);
        $set = isset($_POST['set']) && $_POST['set'] == '1';

        if ($role_id <= 0 || $perm_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        if ($set) {
            $insStmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $insStmt->bind_param('ii', $role_id, $perm_id);
            if ($insStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Permission assigned']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Assignment failed (maybe already exists?)']);
            }
            $insStmt->close();
        } else {
            $delStmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?");
            $delStmt->bind_param('ii', $role_id, $perm_id);
            $delStmt->execute();
            if ($delStmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Permission removed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Removal failed']);
            }
            $delStmt->close();
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// ==================== MAIN PAGE DATA ====================
$roles = $conn->query("SELECT * FROM roles ORDER BY id");
if (!$roles) {
    die("Error fetching roles: " . $conn->error);
}

$permissions = $conn->query("SELECT * FROM permissions ORDER BY id");
if (!$permissions) {
    die("Error fetching permissions: " . $conn->error);
}

$rpResult = $conn->query("SELECT role_id, permission_id FROM role_permissions");
if (!$rpResult) {
    die("Error fetching role permissions: " . $conn->error);
}

$rpArray = [];
while ($rp = $rpResult->fetch_assoc()) {
    $rpArray[$rp['role_id']][$rp['permission_id']] = true;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    /* (Keep existing CSS unchanged) */
    .permissions-container { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 24px; max-width: 1400px; margin: 0 auto; }
    .permissions-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; }
    .permissions-header h2 { font-size: 1.8rem; font-weight: 600; color: #2c3e50; margin: 0; }
    .btn-group { display: flex; gap: 10px; }
    .btn-primary { background: #4361ee; color: white; border: none; padding: 10px 20px; border-radius: 30px; font-size: 0.9rem; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
    .btn-primary:hover { background: #3a56d4; transform: translateY(-2px); }
    .btn-success { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 30px; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
    .btn-success:hover { background: #0e9f6e; transform: translateY(-2px); }
    .table-responsive { overflow-x: auto; }
    .matrix-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; border: 1px solid #e9ecef; }
    .matrix-table th { background: #f8f9fa; color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; padding: 12px 8px; text-align: left; border-bottom: 2px solid #dee2e6; }
    .matrix-table th.role-header { text-align: center; background: #f1f3f5; }
    .matrix-table td { padding: 12px 8px; border-bottom: 1px solid #e9ecef; }
    .matrix-table td.permission-cell { text-align: center; }
    .matrix-table tr:hover { background: #f8f9fa; }
    .permission-label { display: flex; justify-content: space-between; align-items: center; }
    .action-icons { display: flex; gap: 6px; }
    .icon-btn { background: none; border: none; cursor: pointer; font-size: 0.85rem; padding: 4px 8px; border-radius: 4px; transition: 0.2s; }
    .icon-btn.edit { color: #f59e0b; }
    .icon-btn.edit:hover { background: #fef3c7; }
    .icon-btn.delete { color: #ef4444; }
    .icon-btn.delete:hover { background: #fee2e2; }
    .role-header-content { display: flex; align-items: center; justify-content: center; gap: 5px; }
    .permission-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #4361ee; }
    .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .modal.hidden { display: none; }
    .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; padding: 30px; position: relative; }
    .modal h3 { margin-top: 0; margin-bottom: 20px; color: #2c3e50; }
    .modal label { display: block; margin-bottom: 5px; font-size: 0.85rem; color: #495057; }
    .modal input, .modal select, .modal textarea { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; }
    .modal textarea { min-height: 80px; resize: vertical; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
    .btn-secondary { background: #e9ecef; color: #495057; border: none; padding: 8px 16px; border-radius: 30px; font-size: 0.9rem; cursor: pointer; transition: 0.2s; }
    .btn-secondary:hover { background: #dee2e6; }
    .toast { position: fixed; top: 20px; right: 20px; padding: 12px 24px; border-radius: 8px; color: white; font-size: 0.9rem; z-index: 2000; animation: fadeIn 0.3s; }
    .toast.success { background: #10b981; }
    .toast.error { background: #ef4444; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    [x-cloak] { display: none !important; }
</style>

<div class="permissions-container">
    <div class="permissions-header">
        <h2>Role & Permission Management</h2>
        <div class="btn-group">
            <button onclick="openRoleModal('add')" class="btn-primary">
                <i class="fas fa-plus"></i> Add Role
            </button>
            <button onclick="openPermissionModal('add')" class="btn-success">
                <i class="fas fa-plus"></i> Add Permission
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="matrix-table">
            <thead>
                <tr>
                    <th>Permission</th>
                    <?php
                    if ($roles->num_rows > 0) $roles->data_seek(0);
                    while ($role = $roles->fetch_assoc()):
                    ?>
                    <th class="role-header">
                        <div class="role-header-content">
                            <span><?php echo ucfirst(str_replace('_', ' ', $role['role_name'])); ?></span>
                            <button onclick="openRoleModal('edit', <?php echo $role['id']; ?>)" class="icon-btn edit" title="Edit Role">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteRole(<?php echo $role['id']; ?>)" class="icon-btn delete" title="Delete Role">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </th>
                    <?php endwhile; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($permissions->num_rows > 0) $permissions->data_seek(0);
                while ($perm = $permissions->fetch_assoc()):
                ?>
                <tr>
                    <td>
                        <div class="permission-label">
                            <span><?php echo htmlspecialchars($perm['description'] ?: $perm['permission_key']); ?></span>
                            <div class="action-icons">
                                <button onclick="openPermissionModal('edit', <?php echo $perm['id']; ?>)" class="icon-btn edit" title="Edit Permission">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deletePermission(<?php echo $perm['id']; ?>)" class="icon-btn delete" title="Delete Permission">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </td>
                    <?php
                    if ($roles->num_rows > 0) $roles->data_seek(0);
                    while ($role = $roles->fetch_assoc()):
                        $checked = isset($rpArray[$role['id']][$perm['id']]) ? 'checked' : '';
                    ?>
                    <td class="permission-cell">
                        <input type="checkbox" 
                               class="permission-checkbox toggle-permission"
                               data-role-id="<?php echo $role['id']; ?>"
                               data-perm-id="<?php echo $perm['id']; ?>"
                               <?php echo $checked; ?>>
                    </td>
                    <?php endwhile; ?>
                </tr>
                <?php endwhile; ?>

                <?php if ($permissions->num_rows == 0): ?>
                <tr>
                    <td colspan="<?php echo $roles->num_rows + 1; ?>" style="text-align:center; padding:40px;">
                        No permissions found. <button onclick="openPermissionModal('add')" class="btn-success btn-sm">Add one</button>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($roles->num_rows == 0): ?>
    <div style="text-align:center; padding:20px; background:#f8f9fa; border-radius:8px; margin-top:20px;">
        <p>No roles defined. <button onclick="openRoleModal('add')" class="btn-primary btn-sm">Add a role</button></p>
    </div>
    <?php endif; ?>
</div>

<!-- Role Modal -->
<div id="roleModal" class="modal hidden">
    <div class="modal-content">
        <h3 id="roleModalTitle">Add New Role</h3>
        <form id="roleForm" onsubmit="event.preventDefault(); submitRoleForm();">
            <input type="hidden" id="roleId">
            <input type="hidden" id="csrf_role" value="<?= $_SESSION['csrf_token'] ?>">
            <label>Role Name</label>
            <input type="text" id="roleName" required>
            <div class="modal-footer">
                <button type="button" onclick="closeRoleModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" id="roleSubmitBtn">Add Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Permission Modal -->
<div id="permissionModal" class="modal hidden">
    <div class="modal-content">
        <h3 id="permissionModalTitle">Add New Permission</h3>
        <form id="permissionForm" onsubmit="event.preventDefault(); submitPermissionForm();">
            <input type="hidden" id="permissionId">
            <input type="hidden" id="csrf_perm" value="<?= $_SESSION['csrf_token'] ?>">
            <label>Permission Key</label>
            <input type="text" id="permissionKey" required>
            <label>Description</label>
            <textarea id="permissionDescription"></textarea>
            <div class="modal-footer">
                <button type="button" onclick="closePermissionModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-success" id="permissionSubmitBtn">Add Permission</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = '<?= $_SESSION['csrf_token'] ?>';

function showMessage(msg, type) {
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Permission toggle via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.toggle-permission');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const roleId = this.dataset.roleId;
            const permId = this.dataset.permId;
            const checked = this.checked;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ 
                    action: 'permission_toggle', 
                    role_id: roleId, 
                    permission_id: permId, 
                    set: checked ? 1 : 0,
                    csrf_token: csrfToken
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                } else {
                    showMessage(data.message, 'error');
                    this.checked = !checked;
                }
            })
            .catch(error => {
                showMessage('Network error', 'error');
                this.checked = !checked;
            });
        });
    });
});

// Role modal handling
let currentRoleAction = 'add';
let currentRoleId = null;

function openRoleModal(action, id = null) {
    currentRoleAction = action;
    currentRoleId = id;
    const modal = document.getElementById('roleModal');
    const title = document.getElementById('roleModalTitle');
    const submitBtn = document.getElementById('roleSubmitBtn');

    document.getElementById('roleId').value = '';
    document.getElementById('roleName').value = '';

    if (action === 'add') {
        title.innerText = 'Add New Role';
        submitBtn.innerText = 'Add Role';
    } else if (action === 'edit' && id) {
        title.innerText = 'Edit Role';
        submitBtn.innerText = 'Update Role';
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'role_get', id: id, csrf_token: csrfToken })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('roleId').value = data.role.id;
                document.getElementById('roleName').value = data.role.role_name;
            } else {
                showMessage(data.message, 'error');
            }
        });
    }
    modal.classList.remove('hidden');
}

function closeRoleModal() {
    document.getElementById('roleModal').classList.add('hidden');
}

function submitRoleForm() {
    const action = currentRoleAction === 'add' ? 'role_add' : 'role_edit';
    const params = new URLSearchParams();
    params.append('action', action);
    params.append('csrf_token', csrfToken);
    if (currentRoleId) params.append('id', currentRoleId);
    params.append('role_name', document.getElementById('roleName').value);

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            closeRoleModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}

// Permission modal handling
let currentPermissionAction = 'add';
let currentPermissionId = null;

function openPermissionModal(action, id = null) {
    currentPermissionAction = action;
    currentPermissionId = id;
    const modal = document.getElementById('permissionModal');
    const title = document.getElementById('permissionModalTitle');
    const submitBtn = document.getElementById('permissionSubmitBtn');

    document.getElementById('permissionId').value = '';
    document.getElementById('permissionKey').value = '';
    document.getElementById('permissionDescription').value = '';

    if (action === 'add') {
        title.innerText = 'Add New Permission';
        submitBtn.innerText = 'Add Permission';
    } else if (action === 'edit' && id) {
        title.innerText = 'Edit Permission';
        submitBtn.innerText = 'Update Permission';
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'permission_get', id: id, csrf_token: csrfToken })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('permissionId').value = data.permission.id;
                document.getElementById('permissionKey').value = data.permission.permission_key;
                document.getElementById('permissionDescription').value = data.permission.description || '';
            } else {
                showMessage(data.message, 'error');
            }
        });
    }
    modal.classList.remove('hidden');
}

function closePermissionModal() {
    document.getElementById('permissionModal').classList.add('hidden');
}

function submitPermissionForm() {
    const action = currentPermissionAction === 'add' ? 'permission_add' : 'permission_edit';
    const params = new URLSearchParams();
    params.append('action', action);
    params.append('csrf_token', csrfToken);
    if (currentPermissionId) params.append('id', currentPermissionId);
    params.append('permission_key', document.getElementById('permissionKey').value);
    params.append('description', document.getElementById('permissionDescription').value);

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            closePermissionModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}

// Delete role
function deleteRole(id) {
    if (!confirm('Are you sure you want to delete this role? This will remove all permissions for this role.')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'role_delete', id: id, csrf_token: csrfToken })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}

// Delete permission
function deletePermission(id) {
    if (!confirm('Are you sure you want to delete this permission?')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'permission_delete', id: id, csrf_token: csrfToken })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>