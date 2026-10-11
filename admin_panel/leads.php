<?php
$pageTitle = 'Leads Management';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ===================== AJAX HANDLERS ===================== */
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // CSRF check for all actions except maybe 'get' (read-only), but we'll include it for consistency
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'];

    // UPDATE FIELD (status / priority)
    if ($action === 'update_field') {
        $id = (int)$_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];

        $allowedFields = ['status', 'priority'];
        $allowedStatuses = ['New', 'Contacted', 'Follow-up', 'Converted', 'Lost'];
        $allowedPriorities = ['Low', 'Medium', 'High'];

        if (!in_array($field, $allowedFields)) {
            echo json_encode(['success' => false, 'message' => 'Invalid field']);
            exit;
        }
        if ($field === 'status' && !in_array($value, $allowedStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        if ($field === 'priority' && !in_array($value, $allowedPriorities)) {
            echo json_encode(['success' => false, 'message' => 'Invalid priority']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE leads_data SET $field = ? WHERE id = ?");
        $stmt->bind_param("si", $value, $id);
        $stmt->execute();

        echo json_encode(['success' => true]);
        exit;
    }

    // GET LEAD (for editing)
    if ($action === 'get') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("SELECT * FROM leads_data WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $lead = $stmt->get_result()->fetch_assoc();

        echo json_encode(['success' => true, 'lead' => $lead]);
        exit;
    }

    // SAVE (ADD / EDIT)
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $course = trim($_POST['course_interest']);
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $followup = $_POST['next_followup_date'] ?: null;
        $notes = trim($_POST['notes']);

        $errors = [];
        if (empty($name)) $errors[] = 'Name is required';
        if (empty($phone)) $errors[] = 'Phone is required';
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            exit;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE leads_data SET name=?, phone=?, email=?, course_interest=?, status=?, priority=?, next_followup_date=?, notes=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $name, $phone, $email, $course, $status, $priority, $followup, $notes, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO leads_data (name, phone, email, course_interest, status, priority, next_followup_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $name, $phone, $email, $course, $status, $priority, $followup, $notes);
        }
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => $id > 0 ? 'Lead updated' : 'Lead added']);
        exit;
    }

    // DELETE (soft)
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE leads_data SET is_deleted=1 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Lead deleted']);
        exit;
    }

    // BULK DELETE (ids[] array)
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $conn->prepare("UPDATE leads_data SET is_deleted=1 WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
        }
        echo json_encode(['success' => true, 'message' => 'Leads deleted']);
        exit;
    }

    // BULK STATUS UPDATE (ids[] array)
    if ($action === 'bulk_status') {
        $ids = $_POST['ids'] ?? [];
        $status = $_POST['status'];
        $allowedStatuses = ['New', 'Contacted', 'Follow-up', 'Converted', 'Lost'];
        if (!empty($ids) && in_array($status, $allowedStatuses)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $conn->prepare("UPDATE leads_data SET status = ? WHERE id IN ($placeholders)");
            array_unshift($ids, $status);
            $types = 's' . $types;
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
        }
        echo json_encode(['success' => true, 'message' => 'Status updated']);
        exit;
    }

    // EXPORT CSV (ids[] array)
    if ($action === 'export') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $conn->prepare("SELECT name, phone, email, course_interest, status, priority, next_followup_date, notes, created_at FROM leads_data WHERE id IN ($placeholders) AND is_deleted=0 ORDER BY created_at DESC");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT name, phone, email, course_interest, status, priority, next_followup_date, notes, created_at FROM leads_data WHERE is_deleted=0 ORDER BY created_at DESC");
        }

        $csv = fopen('php://temp', 'w');
        fputcsv($csv, ['Name', 'Phone', 'Email', 'Course', 'Status', 'Priority', 'Next Follow-up', 'Notes', 'Created']);

        while ($row = $result->fetch_assoc()) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        echo json_encode(['success' => true, 'csv' => base64_encode($csvContent)]);
        exit;
    }
}

/* ===================== PAGINATION & FILTERS ===================== */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$search = $_GET['search'] ?? '';
$followupFilter = $_GET['followup'] ?? ''; // 'today', 'overdue', 'upcoming'

// Build the WHERE clause with parameters
$conditions = ["is_deleted = 0"];
$params = [];
$types = "";

if ($statusFilter) {
    $conditions[] = "status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if ($priorityFilter) {
    $conditions[] = "priority = ?";
    $params[] = $priorityFilter;
    $types .= "s";
}
if ($search) {
    $conditions[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}
if ($followupFilter === 'today') {
    $conditions[] = "next_followup_date = CURDATE()";
} elseif ($followupFilter === 'overdue') {
    $conditions[] = "next_followup_date < CURDATE()";
} elseif ($followupFilter === 'upcoming') {
    $conditions[] = "next_followup_date > CURDATE()";
}

$where = implode(" AND ", $conditions);

// Count total
$countSql = "SELECT COUNT(*) c FROM leads_data WHERE $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['c'];
$totalPages = ceil($totalRows / $limit);

// Fetch leads
$sql = "SELECT * FROM leads_data WHERE $where ORDER BY 
        CASE 
            WHEN next_followup_date < CURDATE() THEN 1   -- overdue first
            WHEN next_followup_date = CURDATE() THEN 2   -- today
            ELSE 3                                        -- future or none
        END,
        next_followup_date ASC,
        created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
// Append limit and offset to params
$allParams = array_merge($params, [$limit, $offset]);
$allTypes = $types . "ii";
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$leads = $stmt->get_result();

/* ===================== STATS ===================== */
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) c FROM leads_data WHERE is_deleted=0")->fetch_assoc()['c'];
$stats['new'] = $conn->query("SELECT COUNT(*) c FROM leads_data WHERE status='New' AND is_deleted=0")->fetch_assoc()['c'];
$stats['converted'] = $conn->query("SELECT COUNT(*) c FROM leads_data WHERE status='Converted' AND is_deleted=0")->fetch_assoc()['c'];
$stats['followup_today'] = $conn->query("SELECT COUNT(*) c FROM leads_data WHERE next_followup_date = CURDATE() AND is_deleted=0")->fetch_assoc()['c'];
$stats['overdue'] = $conn->query("SELECT COUNT(*) c FROM leads_data WHERE next_followup_date < CURDATE() AND is_deleted=0")->fetch_assoc()['c'];
$stats['upcoming'] = $conn->query("SELECT COUNT(*) c FROM leads_data WHERE next_followup_date > CURDATE() AND is_deleted=0")->fetch_assoc()['c'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    .badge { padding:4px 10px; border-radius:20px; font-size:12px; font-weight:500; }
    .status-New { background:#e5f0ff; color:#1d4ed8; }
    .status-Contacted { background:#fff7ed; color:#c2410c; }
    .status-Follow-up { background:#fef9c3; color:#854d0e; }
    .status-Converted { background:#dcfce7; color:#166534; }
    .status-Lost { background:#fee2e2; color:#991b1b; }
    .priority-Low { background:#e6f7e6; color:#2e7d32; }
    .priority-Medium { background:#fff4e5; color:#b85c00; }
    .priority-High { background:#ffe5e5; color:#b71c1c; }
    .followup-today { background:#fff3cd; font-weight:600; }
    .followup-overdue { background:#f8d7da; font-weight:600; }
    .toast { position:fixed; top:20px; right:20px; padding:10px 18px; background:#111827; color:white; border-radius:30px; font-size:14px; z-index:9999; }
    .loading { opacity:0.5; pointer-events:none; }
</style>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold">Leads Management</h2>
        <div class="flex gap-2">
            <button onclick="exportCSV()" class="bg-gray-600 text-white px-4 py-2 rounded-lg">📥 Export CSV</button>
            <button onclick="openModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg">➕ Add Lead</button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-6 gap-4">
        <div class="bg-white p-4 rounded-lg shadow">Total: <b><?php echo $stats['total']; ?></b></div>
        <div class="bg-white p-4 rounded-lg shadow">New: <b><?php echo $stats['new']; ?></b></div>
        <div class="bg-white p-4 rounded-lg shadow">Converted: <b><?php echo $stats['converted']; ?></b></div>
        <div class="bg-white p-4 rounded-lg shadow">Follow‑up Today: <b><?php echo $stats['followup_today']; ?></b></div>
        <div class="bg-white p-4 rounded-lg shadow">Overdue: <b><?php echo $stats['overdue']; ?></b></div>
        <div class="bg-white p-4 rounded-lg shadow">Upcoming: <b><?php echo $stats['upcoming']; ?></b></div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white p-4 rounded-lg shadow">
        <form method="GET" class="flex flex-wrap gap-4 items-center">
            <input type="text" name="search" placeholder="Search name, phone, email" value="<?php echo htmlspecialchars($search); ?>" class="border p-2 rounded flex-1 min-w-[200px]">
            
            <select name="status" class="border p-2 rounded">
                <option value="">All Status</option>
                <?php foreach(['New','Contacted','Follow-up','Converted','Lost'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $statusFilter==$s?'selected':''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="priority" class="border p-2 rounded">
                <option value="">All Priority</option>
                <?php foreach(['Low','Medium','High'] as $p): ?>
                    <option value="<?php echo $p; ?>" <?php echo $priorityFilter==$p?'selected':''; ?>><?php echo $p; ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="followup" class="border p-2 rounded">
                <option value="">Follow-up</option>
                <option value="today" <?php echo $followupFilter=='today'?'selected':''; ?>>Due Today</option>
                <option value="overdue" <?php echo $followupFilter=='overdue'?'selected':''; ?>>Overdue</option>
                <option value="upcoming" <?php echo $followupFilter=='upcoming'?'selected':''; ?>>Upcoming</option>
            </select>
            
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">Apply Filters</button>
            <a href="leads.php" class="bg-gray-300 text-gray-800 px-4 py-2 rounded">Reset</a>
        </form>
    </div>

    <!-- Bulk Actions -->
    <div class="flex gap-2 items-center">
        <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
        <label for="selectAll">Select All</label>
        <select id="bulkStatus" class="border p-2 rounded">
            <option value="">Change Status</option>
            <?php foreach(['New','Contacted','Follow-up','Converted','Lost'] as $s): ?>
                <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
            <?php endforeach; ?>
        </select>
        <button onclick="bulkStatus()" class="bg-blue-600 text-white px-3 py-2 rounded">Apply</button>
        <button onclick="bulkDelete()" class="bg-red-600 text-white px-3 py-2 rounded">Delete Selected</button>
    </div>

    <!-- Leads Table -->
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-3"><input type="checkbox" id="selectAllHeader" onclick="toggleAll(this)"></th>
                    <th class="p-3 text-left">Lead</th>
                    <th class="p-3 text-left">Contact</th>
                    <th class="p-3 text-left">Course</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Priority</th>
                    <th class="p-3 text-left">Next Follow‑up</th>
                    <th class="p-3 text-left">Created</th>
                    <th class="p-3 text-left">Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php while($row = $leads->fetch_assoc()): 
                $followupClass = '';
                $today = date('Y-m-d');
                if ($row['next_followup_date'] == $today) $followupClass = 'followup-today';
                elseif ($row['next_followup_date'] < $today) $followupClass = 'followup-overdue';
            ?>
                <tr id="row-<?php echo $row['id']; ?>" class="border-b hover:bg-gray-50 <?php echo $followupClass; ?>">
                    <td class="p-3"><input type="checkbox" class="rowCheckbox" value="<?php echo $row['id']; ?>"></td>
                    <td class="p-3 font-medium"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td class="p-3">
                        <?php echo htmlspecialchars($row['phone']); ?><br>
                        <small class="text-gray-500"><?php echo htmlspecialchars($row['email']); ?></small>
                    </td>
                    <td class="p-3"><?php echo htmlspecialchars($row['course_interest']); ?></td>
                    <td class="p-3">
                        <select onchange="updateField(<?php echo $row['id']; ?>,'status',this.value, this)" 
                                class="badge status-<?php echo $row['status']; ?>"
                                data-old-value="<?php echo $row['status']; ?>">
                            <?php foreach(['New','Contacted','Follow-up','Converted','Lost'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo ($row['status']==$s)?'selected':''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="p-3">
                        <select onchange="updateField(<?php echo $row['id']; ?>,'priority',this.value, this)" 
                                class="badge priority-<?php echo $row['priority']; ?>"
                                data-old-value="<?php echo $row['priority']; ?>">
                            <?php foreach(['Low','Medium','High'] as $p): ?>
                                <option value="<?php echo $p; ?>" <?php echo ($row['priority']==$p)?'selected':''; ?>><?php echo $p; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="p-3">
                        <?php if ($row['next_followup_date']): ?>
                            <?php echo date('d M Y', strtotime($row['next_followup_date'])); ?>
                            <?php if ($row['next_followup_date'] == $today): ?>
                                <span class="text-xs font-bold text-orange-600">(Today)</span>
                            <?php elseif ($row['next_followup_date'] < $today): ?>
                                <span class="text-xs font-bold text-red-600">(Overdue)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="p-3"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                    <td class="p-3">
                        <button onclick="editLead(<?php echo $row['id']; ?>)" class="text-blue-600 hover:underline" title="Edit">✏️</button>
                        <button onclick="deleteLead(<?php echo $row['id']; ?>)" class="text-red-600 hover:underline ml-2" title="Delete">🗑️</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if($totalPages > 1): ?>
    <div class="flex justify-center gap-2">
        <?php for($i=1; $i<=$totalPages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&search=<?php echo urlencode($search); ?>&followup=<?php echo urlencode($followupFilter); ?>" class="px-3 py-1 border <?php echo $i==$page?'bg-indigo-600 text-white':'bg-white'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div id="leadModal" class="fixed inset-0 hidden bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white w-full max-w-lg p-6 rounded-lg">
        <h3 class="text-lg font-bold mb-4" id="modalTitle">Add Lead</h3>
        <form id="leadForm" onsubmit="saveLead(event)">
            <input type="hidden" name="id" id="leadId">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <input name="name" id="name" placeholder="Name *" class="w-full border p-2 mb-2" required>
            <input name="phone" id="phone" placeholder="Phone *" class="w-full border p-2 mb-2" required>
            <input name="email" id="email" placeholder="Email" type="email" class="w-full border p-2 mb-2">
            <input name="course_interest" id="course" placeholder="Course" class="w-full border p-2 mb-2">

            <select name="status" id="status" class="w-full border p-2 mb-2">
                <option value="New">New</option>
                <option value="Contacted">Contacted</option>
                <option value="Follow-up">Follow-up</option>
                <option value="Converted">Converted</option>
                <option value="Lost">Lost</option>
            </select>

            <select name="priority" id="priority" class="w-full border p-2 mb-2">
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
            </select>

            <input name="next_followup_date" id="followup" type="date" class="w-full border p-2 mb-2">

            <textarea name="notes" id="notes" placeholder="Notes" class="w-full border p-2 mb-2"></textarea>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
let csrf = '<?php echo $_SESSION['csrf_token']; ?>';

function toast(msg, success = true) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.style.background = success ? '#111827' : '#b91c1c';
    t.innerText = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

function openModal(leadId = null) {
    document.getElementById('leadModal').classList.remove('hidden');
    if (!leadId) {
        document.getElementById('leadForm').reset();
        document.getElementById('leadId').value = '';
        document.getElementById('modalTitle').innerText = 'Add Lead';
    }
}

function closeModal() {
    document.getElementById('leadModal').classList.add('hidden');
}

// Save lead (add/edit)
function saveLead(e) {
    e.preventDefault();
    const form = document.getElementById('leadForm');
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'save');

    fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                toast(data.message);
                location.reload();
            } else {
                toast(data.message, false);
            }
        });
}

// Edit lead
function editLead(id) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ ajax: 1, action: 'get', id: id, csrf_token: csrf })
    })
    .then(res => res.json())
    .then(data => {
        const l = data.lead;
        openModal(id);
        document.getElementById('modalTitle').innerText = 'Edit Lead';
        document.getElementById('leadId').value = l.id;
        document.getElementById('name').value = l.name;
        document.getElementById('phone').value = l.phone;
        document.getElementById('email').value = l.email;
        document.getElementById('course').value = l.course_interest;
        document.getElementById('status').value = l.status;
        document.getElementById('priority').value = l.priority;
        document.getElementById('followup').value = l.next_followup_date;
        document.getElementById('notes').value = l.notes;
    });
}

// Delete lead
function deleteLead(id) {
    if (!confirm('Delete lead?')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ ajax: 1, action: 'delete', id: id, csrf_token: csrf })
    })
    .then(res => res.json())
    .then(data => {
        toast(data.message);
        document.getElementById('row-' + id).remove();
    });
}

// Inline update (status/priority) – updates badge class on success
function updateField(id, field, value, selectElement) {
    // Store old value for class removal
    const oldValue = selectElement.getAttribute('data-old-value');
    // Optimistically update the class to show new badge (optional, but user-friendly)
    selectElement.classList.remove(`${field}-${oldValue}`);
    selectElement.classList.add(`${field}-${value}`);
    selectElement.setAttribute('data-old-value', value);

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ ajax: 1, action: 'update_field', id: id, field: field, value: value, csrf_token: csrf })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            // Revert class on error
            selectElement.classList.remove(`${field}-${value}`);
            selectElement.classList.add(`${field}-${oldValue}`);
            selectElement.setAttribute('data-old-value', oldValue);
            toast('Update failed', false);
        }
    });
}

// Helper to get selected IDs as array
function getSelectedIds() {
    return Array.from(document.querySelectorAll('.rowCheckbox:checked')).map(cb => cb.value);
}

// Send bulk action with FormData (proper array handling)
function sendBulkAction(action, additionalData = {}) {
    const ids = getSelectedIds();
    if (!ids.length) {
        toast('Select at least one lead', false);
        return false;
    }
    if (action === 'bulk_status' && !additionalData.status) {
        toast('Select a status', false);
        return false;
    }

    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', action);
    formData.append('csrf_token', csrf);
    ids.forEach(id => formData.append('ids[]', id));
    for (let key in additionalData) {
        formData.append(key, additionalData[key]);
    }

    fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            toast(data.message);
            if (action === 'bulk_delete') {
                ids.forEach(id => document.getElementById('row-' + id)?.remove());
            } else {
                location.reload(); // refresh to show updated statuses
            }
        });
    return true;
}

function bulkDelete() {
    if (!confirm('Delete selected leads?')) return;
    sendBulkAction('bulk_delete');
}

function bulkStatus() {
    const status = document.getElementById('bulkStatus').value;
    if (!status) {
        toast('Select a status', false);
        return;
    }
    sendBulkAction('bulk_status', { status: status });
}

function exportCSV() {
    const ids = getSelectedIds();
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'export');
    formData.append('csrf_token', csrf);
    ids.forEach(id => formData.append('ids[]', id));

    fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const a = document.createElement('a');
                a.href = 'data:text/csv;base64,' + data.csv;
                a.download = 'leads_export.csv';
                a.click();
            } else {
                toast('Export failed', false);
            }
        });
}

function toggleAll(source) {
    document.querySelectorAll('.rowCheckbox').forEach(cb => cb.checked = source.checked);
    if (document.getElementById('selectAllHeader')) document.getElementById('selectAllHeader').checked = source.checked;
    if (document.getElementById('selectAll')) document.getElementById('selectAll').checked = source.checked;
}
</script>

<?php include 'includes/footer.php'; ?>