<?php
$pageTitle = 'Inquiries';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Permission
if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_inquiries')) {
    header("Location: unauthorized.php");
    exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ================= AJAX ================= */
if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF']);
            exit;
        }

        $action = $_POST['action'];
        $id = (int)$_POST['id'];

        if ($action === 'mark_completed') {
            $stmt = $conn->prepare("UPDATE contact_enquiries SET status='completed' WHERE id=? AND is_deleted=0");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Marked Completed']);
        } elseif ($action === 'mark_pending') {
            $stmt = $conn->prepare("UPDATE contact_enquiries SET status='pending' WHERE id=? AND is_deleted=0");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Marked Pending']);
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("UPDATE contact_enquiries SET is_deleted=1 WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Deleted']);
        }
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'get_message') {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT message FROM contact_enquiries WHERE id=? AND is_deleted=0");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) {
            echo json_encode(['success' => true, 'message' => $row['message']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Not found']);
        }
        exit;
    }
}

/* ================= FILTERS ================= */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "is_deleted = 0";
$params = [];
$types = "";

if ($status && in_array($status, ['pending', 'completed'])) {
    $where .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR message LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

// Count total
$countSql = "SELECT COUNT(*) as cnt FROM contact_enquiries WHERE $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['cnt'];
$totalPages = ceil($totalRows / $limit);

// Fetch inquiries
$sql = "SELECT id, full_name, phone, email, message, status, created_at 
        FROM contact_enquiries 
        WHERE $where 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$allParams = array_merge($params, [$limit, $offset]);
$allTypes = $types . "ii";
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$inquiries = $stmt->get_result();

/* ================= STATS (using prepared statements for consistency) ================= */
$stats = [];
$statQueries = [
    'total'     => "SELECT COUNT(*) as cnt FROM contact_enquiries WHERE is_deleted=0",
    'pending'   => "SELECT COUNT(*) as cnt FROM contact_enquiries WHERE status='pending' AND is_deleted=0",
    'completed' => "SELECT COUNT(*) as cnt FROM contact_enquiries WHERE status='completed' AND is_deleted=0"
];

foreach ($statQueries as $key => $query) {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats[$key] = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
.badge { padding:4px 10px; border-radius:20px; font-size:12px; font-weight:500; }
.badge-pending { background:#fff7ed; color:#c2410c; }
.badge-completed { background:#dcfce7; color:#166534; }

.toast {
    position:fixed;
    top:20px;
    right:20px;
    padding:12px 20px;
    background:#1f2937;
    color:white;
    border-radius:40px;
    z-index: 9999;
    font-size:14px;
    box-shadow:0 4px 12px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease;
}
.toast.error {
    background:#dc2626;
}
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">Inquiries</h2>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <span class="text-gray-500 text-sm">Total</span>
            <p class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <span class="text-gray-500 text-sm">Pending</span>
            <p class="text-2xl font-bold text-orange-600"><?= $stats['pending'] ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <span class="text-gray-500 text-sm">Completed</span>
            <p class="text-2xl font-bold text-green-600"><?= $stats['completed'] ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" placeholder="Search name, phone, email, message..." 
                   value="<?= htmlspecialchars($search) ?>"
                   class="border border-gray-300 p-2 rounded-lg flex-1 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">

            <select name="status" class="border border-gray-300 p-2 rounded-lg">
                <option value="">All Status</option>
                <option value="pending" <?= $status=='pending' ? 'selected' : '' ?>>Pending</option>
                <option value="completed" <?= $status=='completed' ? 'selected' : '' ?>>Completed</option>
            </select>

            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-filter mr-1"></i> Apply
            </button>
            <a href="inquiries.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition text-center">
                <i class="fas fa-times mr-1"></i> Reset
            </a>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="p-4 text-left font-semibold text-gray-600">Name</th>
                        <th class="p-4 text-left font-semibold text-gray-600">Contact</th>
                        <th class="p-4 text-left font-semibold text-gray-600">Message</th>
                        <th class="p-4 text-left font-semibold text-gray-600">Status</th>
                        <th class="p-4 text-left font-semibold text-gray-600">Date</th>
                        <th class="p-4 text-left font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $inquiries->fetch_assoc()): ?>
                    <tr id="row-<?= $row['id'] ?>" class="border-b border-gray-100 hover:bg-gray-50 transition">
                        <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars($row['full_name']) ?></td>
                        <td class="p-4">
                            <div class="text-gray-800"><?= htmlspecialchars($row['phone']) ?></div>
                            <div class="text-gray-500 text-xs"><?= htmlspecialchars($row['email']) ?></div>
                        </td>
                        <td class="p-4">
                            <span class="text-indigo-600 cursor-pointer hover:underline text-sm"
                                  onclick="viewMessage(<?= $row['id'] ?>)">
                                <?= htmlspecialchars(mb_substr($row['message'], 0, 40)) ?>
                                <?= strlen($row['message']) > 40 ? '…' : '' ?>
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="badge badge-<?= htmlspecialchars($row['status']) ?>">
                                <?= ucfirst(htmlspecialchars($row['status'])) ?>
                            </span>
                        </td>
                        <td class="p-4 text-gray-600"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                        <td class="p-4 whitespace-nowrap">
                            <?php if($row['status'] == 'pending'): ?>
                                <button onclick="markCompleted(<?= $row['id'] ?>)" 
                                        class="text-green-600 hover:text-green-800 p-2 rounded-full hover:bg-green-50 transition" title="Mark Completed">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                            <?php else: ?>
                                <button onclick="markPending(<?= $row['id'] ?>)" 
                                        class="text-yellow-600 hover:text-yellow-800 p-2 rounded-full hover:bg-yellow-50 transition" title="Mark Pending">
                                    <i class="fas fa-undo-alt"></i>
                                </button>
                            <?php endif; ?>
                            <button onclick="deleteInquiry(<?= $row['id'] ?>)" 
                                    class="text-red-600 hover:text-red-800 p-2 rounded-full hover:bg-red-50 transition" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($inquiries->num_rows == 0): ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center text-gray-500">No inquiries found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if($totalPages > 1): ?>
    <div class="flex justify-center gap-2">
        <?php for($i=1; $i<=$totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"
               class="px-4 py-2 rounded-lg border transition <?= $i==$page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' ?>">
               <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Message Modal -->
<div id="messageModal" class="fixed inset-0 hidden bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Full Message</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalMessage" class="text-gray-700 whitespace-pre-wrap leading-relaxed"></div>
            <div class="mt-6 flex justify-end">
                <button onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const csrf = '<?= $_SESSION['csrf_token'] ?>';

function toast(msg, isError = false) {
    const t = document.createElement('div');
    t.className = 'toast' + (isError ? ' error' : '');
    t.innerHTML = '<i class="fas ' + (isError ? 'fa-exclamation-circle' : 'fa-check-circle') + ' mr-2"></i>' + msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

function viewMessage(id) {
    fetch('?ajax=1&action=get_message&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalMessage').innerText = data.message;
                document.getElementById('messageModal').classList.remove('hidden');
            } else {
                toast('Could not load message', true);
            }
        })
        .catch(() => toast('Network error', true));
}

function closeModal() {
    document.getElementById('messageModal').classList.add('hidden');
}

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
});

function markCompleted(id) {
    if (!confirm('Mark this inquiry as completed?')) return;
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            ajax: 1,
            action: 'mark_completed',
            id: id,
            csrf_token: csrf
        })
    })
    .then(r => r.json())
    .then(data => {
        toast(data.message);
        if (data.success) location.reload();
        else toast(data.message, true);
    });
}

function markPending(id) {
    if (!confirm('Mark this inquiry as pending?')) return;
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            ajax: 1,
            action: 'mark_pending',
            id: id,
            csrf_token: csrf
        })
    })
    .then(r => r.json())
    .then(data => {
        toast(data.message);
        if (data.success) location.reload();
        else toast(data.message, true);
    });
}

function deleteInquiry(id) {
    if (!confirm('Are you sure you want to delete this inquiry? This action cannot be undone.')) return;
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            ajax: 1,
            action: 'delete',
            id: id,
            csrf_token: csrf
        })
    })
    .then(r => r.json())
    .then(data => {
        toast(data.message);
        if (data.success) {
            document.getElementById('row-' + id).remove();
        } else {
            toast(data.message, true);
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>