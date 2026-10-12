<?php
// admin_users.php - Secure admin management with prepared statements and CSRF
$pageTitle = 'Manage Admins';
include 'includes/config.php';
include 'includes/functions.php';
require_once 'includes/password_reset.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
if (!$conn) {
    die("Database connection not established.");
}

if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_admins')) {
    header("Location: unauthorized.php");
    exit;
}

if ($conn) { ensureResetSchema($conn); }

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== AJAX HANDLERS ====================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_admins')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    // CSRF validation for all POST actions
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'];

    // Get admin data for editing
    if ($action === 'get' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("SELECT id, name, username, email, role_id, status FROM admins WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'admin' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
        }
        exit;
    }

    // Add admin
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'A valid email address is required']);
            exit;
        }
        if (empty($name) || empty($username) || empty($password) || $role_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Name, username, password and role are required']);
            exit;
        }

        // Check if username exists
        $checkStmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $checkStmt->bind_param('s', $username);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }

        $emailCheck = $conn->prepare("SELECT id FROM admins WHERE email = ?");
        $emailCheck->bind_param('s', $email);
        $emailCheck->execute();
        if ($emailCheck->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already used by another admin']);
            exit;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (name, username, email, password, role_id, status, created_at) VALUES (?, ?, NULLIF(?, ''), ?, ?, ?, NOW())");
        $stmt->bind_param('ssssis', $name, $username, $email, $hashed, $role_id, $status);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Admin added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }

    // Edit admin
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'A valid email address is required']);
            exit;
        }
        if ($id <= 0 || empty($name) || empty($username) || $role_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        // Check username uniqueness excluding current admin
        $checkStmt = $conn->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $checkStmt->bind_param('si', $username, $id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }

        $emailCheck = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $emailCheck->bind_param('si', $email, $id);
        $emailCheck->execute();
        if ($emailCheck->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already used by another admin']);
            exit;
        }

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET name = ?, username = ?, email = NULLIF(?, ''), password = ?, role_id = ?, status = ? WHERE id = ?");
            $stmt->bind_param('ssssisi', $name, $username, $email, $hashed, $role_id, $status, $id);
        } else {
            $stmt = $conn->prepare("UPDATE admins SET name = ?, username = ?, email = NULLIF(?, ''), role_id = ?, status = ? WHERE id = ?");
            $stmt->bind_param('sssisi', $name, $username, $email, $role_id, $status, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Admin updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }

    // Generate password reset link (emailed if the admin has an email, always returned for manual sharing)
    if ($action === 'reset_link') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT id, name, email FROM admins WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
            exit;
        }
        $link = createResetLink($conn, (int)$target['id']);
        $emailed = false;
        if (filter_var($target['email'], FILTER_VALIDATE_EMAIL)) {
            $emailed = sendResetEmail($target['email'], $target['name'], $link);
        }
        echo json_encode([
            'success' => true,
            'emailed' => $emailed,
            'link' => $link,
            'message' => $emailed ? 'Reset link emailed to ' . $target['email'] : 'Reset link generated'
        ]);
        exit;
    }

    // Toggle status
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';

        if ($id <= 0 || !in_array($newStatus, ['active', 'blocked'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }

        if ($id == $_SESSION['admin_id'] && $newStatus == 'blocked') {
            echo json_encode(['success' => false, 'message' => 'You cannot block your own account']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE admins SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes or admin not found']);
        }
        exit;
    }

    // Delete admin
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        if ($id == $_SESSION['admin_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Admin deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// ==================== MAIN PAGE LOGIC ====================
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
$types = '';

if ($role_filter && is_numeric($role_filter)) {
    $where[] = "a.role_id = ?";
    $params[] = (int)$role_filter;
    $types .= 'i';
}
if ($status_filter && in_array($status_filter, ['active', 'blocked'])) {
    $where[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if ($search) {
    $where[] = "(a.name LIKE ? OR a.username LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$countSQL = "SELECT COUNT(*) as cnt FROM admins a $whereSQL";
$countStmt = $conn->prepare($countSQL);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total_rows = $countStmt->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $limit);

// Fetch admins
$query = "SELECT a.*, r.role_name 
          FROM admins a 
          LEFT JOIN roles r ON a.role_id = r.id 
          $whereSQL 
          ORDER BY a.id DESC 
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$allParams = $params;
$allParams[] = $limit;
$allParams[] = $offset;
$allTypes = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$admins = $stmt->get_result();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    /* ===== Admin Users Page Styles ===== */
    .admin-container {
        padding: 1.5rem;
        max-width: 1400px;
        margin: 0 auto;
    }
    .admin-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .admin-header h2 {
        font-size: 1.8rem;
        font-weight: 600;
        color: #2b2d42;
        margin: 0;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 0.5rem;
    }
    .btn-primary, .btn-secondary {
        padding: 0.6rem 1.2rem;
        border-radius: 40px;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid transparent;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    .btn-primary {
        background: #4361ee;
        color: white;
    }
    .btn-primary:hover {
        background: #3a56d4;
    }
    .btn-secondary {
        background: white;
        border-color: #e9ecef;
        color: #2b2d42;
    }
    .btn-secondary:hover {
        background: #f8f9fa;
    }
    .filters {
        background: white;
        padding: 1.2rem;
        border-radius: 16px;
        border: 1px solid #e9ecef;
        margin-bottom: 2rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
    }
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        flex: 1;
    }
    .search-box {
        flex: 2;
        min-width: 250px;
    }
    .search-box input {
        width: 100%;
        padding: 0.6rem 1rem;
        border: 1px solid #e9ecef;
        border-radius: 30px;
        font-size: 0.9rem;
    }
    .filter-select {
        padding: 0.6rem 1rem;
        border: 1px solid #e9ecef;
        border-radius: 30px;
        background: white;
        font-size: 0.9rem;
        min-width: 150px;
    }
    .table-responsive {
        overflow-x: auto;
        background: white;
        border-radius: 16px;
        border: 1px solid #e9ecef;
        margin-bottom: 1.5rem;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th {
        text-align: left;
        padding: 1rem;
        background: #f8f9fa;
        font-weight: 600;
        color: #2b2d42;
        border-bottom: 2px solid #e9ecef;
    }
    td {
        padding: 1rem;
        border-bottom: 1px solid #e9ecef;
        color: #4a5568;
    }
    tr:last-child td {
        border-bottom: none;
    }
    .badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: capitalize;
    }
    .badge-active {
        background: #d1fae5;
        color: #06d6a0;
    }
    .badge-blocked {
        background: #ffe3e3;
        color: #ef476f;
    }
    .action-btn {
        background: none;
        border: none;
        font-size: 1rem;
        cursor: pointer;
        margin: 0 0.25rem;
        padding: 0.5rem;
        border-radius: 8px;
        transition: 0.2s;
    }
    .action-btn.edit {
        color: #4361ee;
    }
    .action-btn.edit:hover {
        background: #e9ecff;
    }
    .action-btn.reset { color: #06b6d4; }
    .action-btn.reset:hover { background: #e0f7fb; }
    .action-btn.toggle {
        color: #ffb703;
    }
    .action-btn.toggle:hover {
        background: #fff4d6;
    }
    .action-btn.delete {
        color: #ef476f;
    }
    .action-btn.delete:hover {
        background: #ffe3e3;
    }
    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }
    .pagination a, .pagination span {
        padding: 0.5rem 1rem;
        border: 1px solid #e9ecef;
        border-radius: 30px;
        text-decoration: none;
        color: #4361ee;
        transition: 0.2s;
    }
    .pagination a:hover {
        background: #f8f9fa;
    }
    .pagination .active {
        background: #4361ee;
        color: white;
        border-color: #4361ee;
    }
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
    .modal.hidden {
        display: none;
    }
    .modal-content {
        background: white;
        padding: 2rem;
        border-radius: 20px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-content h3 {
        margin-top: 0;
        margin-bottom: 1.5rem;
        color: #2b2d42;
    }
    .modal-content label {
        display: block;
        margin-bottom: 0.3rem;
        font-weight: 500;
        color: #4a5568;
    }
    .modal-content input, .modal-content select {
        width: 100%;
        padding: 0.7rem;
        margin-bottom: 1rem;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        font-size: 0.9rem;
    }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 1rem;
    }
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 50px;
        color: white;
        font-weight: 500;
        z-index: 2000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
    }
    .toast.success {
        background: #06d6a0;
    }
    .toast.error {
        background: #ef476f;
    }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
</style>

<div class="admin-container">
    <div class="admin-header">
        <h2>Manage Admins</h2>
        <button onclick="openModal('add')" class="btn-primary">
            <i class="fas fa-plus"></i> Add New Admin
        </button>
    </div>

    <!-- Filter Form -->
    <form method="GET" class="filters">
        <div class="filter-group">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search by name or username..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="role" class="filter-select">
                <option value="">All Roles</option>
                <?php
                $rolesForFilter = $conn->query("SELECT id, role_name FROM roles ORDER BY role_name");
                while ($r = $rolesForFilter->fetch_assoc()):
                    $selected = ($role_filter == $r['id']) ? 'selected' : '';
                ?>
                <option value="<?= $r['id'] ?>" <?= $selected ?>><?= ucfirst(str_replace('_', ' ', $r['role_name'])) ?></option>
                <?php endwhile; ?>
            </select>
            <select name="status" class="filter-select">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter=='active'?'selected':'' ?>>Active</option>
                <option value="blocked" <?= $status_filter=='blocked'?'selected':'' ?>>Blocked</option>
            </select>
            <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="?" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
        </div>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($admins && $admins->num_rows > 0): ?>
                    <?php while ($admin = $admins->fetch_assoc()): ?>
                    <tr data-id="<?= $admin['id'] ?>">
                        <td><?= $admin['id'] ?></td>
                        <td><?= htmlspecialchars($admin['name']) ?></td>
                        <td><?= htmlspecialchars($admin['username']) ?></td>
                        <td><?= $admin['role_name'] ? ucfirst(str_replace('_', ' ', $admin['role_name'])) : 'N/A' ?></td>
                        <td>
                            <span class="badge <?= $admin['status'] == 'active' ? 'badge-active' : 'badge-blocked' ?>">
                                <?= $admin['status'] ?>
                            </span>
                        </td>
                        <td><?= $admin['last_login'] ? date('d M Y H:i', strtotime($admin['last_login'])) : 'Never' ?></td>
                        <td>
                            <button onclick="openModal('edit', <?= $admin['id'] ?>)" class="action-btn edit" title="Edit"><i class="fas fa-edit"></i></button>
                            <button onclick="sendResetLink(<?= $admin['id'] ?>)" class="action-btn reset" title="Reset Password"><i class="fas fa-key"></i></button>
                            <button onclick="toggleStatus(<?= $admin['id'] ?>, '<?= $admin['status'] ?>')" class="action-btn toggle" title="<?= $admin['status'] == 'active' ? 'Block' : 'Activate' ?>">
                                <i class="fas <?= $admin['status'] == 'active' ? 'fa-lock' : 'fa-unlock-alt' ?>"></i>
                            </button>
                            <button onclick="deleteAdmin(<?= $admin['id'] ?>)" class="action-btn delete" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; padding: 40px;">No admins found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">Previous</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
            <span class="active"><?= $i ?></span>
            <?php else: ?>
            <a href="?page=<?= $i ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page+1 ?>&role=<?= urlencode($role_filter) ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div id="adminModal" class="modal hidden">
    <div class="modal-content">
        <h3 id="modalTitle">Add New Admin</h3>
        <form id="adminForm" onsubmit="event.preventDefault(); submitForm();">
            <input type="hidden" name="id" id="adminId">
            <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label>Full Name</label>
            <input type="text" id="name" required>
            <label>Username</label>
            <input type="text" id="username" required>
            <label>Email * (used for password reset)</label>
            <input type="email" id="email" required placeholder="admin@example.com">
            <label>Password</label>
            <input type="password" id="password">
            <p id="passwordHint" style="font-size:0.8rem; color:#6c757d; margin-top:-10px; margin-bottom:10px; display:none;">Leave blank to keep current password (when editing)</p>
            <label>Role</label>
            <select id="role_id" required>
                <?php
                $rolesForDropdown = $conn->query("SELECT id, role_name FROM roles ORDER BY role_name");
                while ($r = $rolesForDropdown->fetch_assoc()):
                ?>
                <option value="<?= $r['id'] ?>"><?= ucfirst(str_replace('_', ' ', $r['role_name'])) ?></option>
                <?php endwhile; ?>
            </select>
            <label>Status</label>
            <select id="status">
                <option value="active">Active</option>
                <option value="blocked">Blocked</option>
            </select>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" id="modalSubmit">Add Admin</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
let currentAction = 'add';
let currentId = null;

function openModal(action, id = null) {
    currentAction = action;
    currentId = id;
    const modal = document.getElementById('adminModal');
    const title = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('modalSubmit');
    const passwordHint = document.getElementById('passwordHint');

    document.getElementById('adminId').value = '';
    document.getElementById('name').value = '';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('email').value = '';
    document.getElementById('role_id').value = '1';
    document.getElementById('status').value = 'active';

    if (action === 'add') {
        title.innerText = 'Add New Admin';
        submitBtn.innerText = 'Add Admin';
        passwordHint.style.display = 'none';
    } else if (action === 'edit' && id) {
        title.innerText = 'Edit Admin';
        submitBtn.innerText = 'Update Admin';
        passwordHint.style.display = 'block';
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'get', id: id, csrf_token: csrfToken })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('adminId').value = data.admin.id;
                document.getElementById('name').value = data.admin.name;
                document.getElementById('username').value = data.admin.username;
                document.getElementById('email').value = data.admin.email || '';
                document.getElementById('role_id').value = data.admin.role_id;
                document.getElementById('status').value = data.admin.status;
            } else {
                showMessage(data.message, 'error');
            }
        });
    }
    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById('adminModal').classList.add('hidden');
}

function submitForm() {
    const action = currentAction === 'add' ? 'add' : 'edit';
    const params = new URLSearchParams();
    params.append('action', action);
    params.append('csrf_token', csrfToken);
    if (currentId) params.append('id', currentId);
    params.append('name', document.getElementById('name').value);
    params.append('username', document.getElementById('username').value);
    params.append('password', document.getElementById('password').value);
    params.append('email', document.getElementById('email').value);
    params.append('role_id', document.getElementById('role_id').value);
    params.append('status', document.getElementById('status').value);

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            closeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}

function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'blocked' : 'active';
    if (!confirm(`Are you sure you want to make this admin ${newStatus}?`)) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'toggle_status', id: id, status: newStatus, csrf_token: csrfToken })
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

function sendResetLink(id) {
    if (!confirm('Generate a password reset link for this admin? (Emailed if they have an email set.)')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'reset_link', id: id, csrf_token: csrfToken })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) { showMessage(data.message, 'error'); return; }
        showMessage(data.message, 'success');
        if (!data.emailed) {
            // No email on file (or mail failed): show link so it can be shared manually
            window.prompt('Share this one-time reset link with the admin (valid 30 minutes):', data.link);
        }
    });
}

function deleteAdmin(id) {
    if (!confirm('Are you sure you want to delete this admin? This action cannot be undone.')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'delete', id: id, csrf_token: csrfToken })
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

function showMessage(msg, type) {
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php include 'includes/footer.php'; ?>