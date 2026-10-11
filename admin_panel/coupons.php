<?php
// coupons.php - Admin panel for coupon management (full CRUD with AJAX)
$pageTitle = 'Coupon Management';
include 'includes/config.php';
include 'includes/functions.php';   // hasPermission() function

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Permission check for the whole page
if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_coupons')) {
    header("Location: unauthorized.php");
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== AJAX HANDLER (embedded) ====================
if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // Re-check permission for AJAX
    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_coupons')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token for state-changing actions
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        $action = $_POST['action'] ?? '';

        // Delete coupon (hard delete – you can change to soft delete if needed)
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $conn->query("DELETE FROM coupons WHERE id = $id");
            if ($conn->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Coupon deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Coupon not found']);
            }
            exit;
        }

        // Get coupon data for editing
        if ($action === 'get') {
            $id = (int)$_POST['id'];
            $result = $conn->query("SELECT * FROM coupons WHERE id = $id");
            if ($row = $result->fetch_assoc()) {
                echo json_encode(['success' => true, 'coupon' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Coupon not found']);
            }
            exit;
        }

        // Add or edit coupon
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $discount_type = $_POST['discount_type'] ?? 'percentage';
            $discount_value = (float)($_POST['discount_value'] ?? 0);
            $max_uses = (int)($_POST['max_uses'] ?? 1);
            $min_order_amount = (float)($_POST['min_order_amount'] ?? 0);
            $valid_from = $_POST['valid_from'] ?? '';
            $valid_to = $_POST['valid_to'] ?? '';
            $status = $_POST['status'] ?? 'active';

            if (empty($code) || $discount_value <= 0 || empty($valid_from) || empty($valid_to)) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                exit;
            }
            if (strtotime($valid_from) > strtotime($valid_to)) {
                echo json_encode(['success' => false, 'message' => 'Valid From date must be before Valid To date']);
                exit;
            }

            // Escape
            $code = $conn->real_escape_string($code);
            $discount_type = $conn->real_escape_string($discount_type);
            $status = $conn->real_escape_string($status);

            if ($id > 0) {
                // Update
                $sql = "UPDATE coupons SET 
                        code = '$code',
                        discount_type = '$discount_type',
                        discount_value = $discount_value,
                        max_uses = $max_uses,
                        min_order_amount = $min_order_amount,
                        valid_from = '$valid_from',
                        valid_to = '$valid_to',
                        status = '$status'
                        WHERE id = $id";
                if ($conn->query($sql)) {
                    echo json_encode(['success' => true, 'message' => 'Coupon updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                }
            } else {
                // Insert
                $sql = "INSERT INTO coupons (code, discount_type, discount_value, max_uses, min_order_amount, valid_from, valid_to, status, used_count, created_at) 
                        VALUES ('$code', '$discount_type', $discount_value, $max_uses, $min_order_amount, '$valid_from', '$valid_to', '$status', 0, NOW())";
                if ($conn->query($sql)) {
                    echo json_encode(['success' => true, 'message' => 'Coupon added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                }
            }
            exit;
        }
    }
}

// ==================== MAIN PAGE LOGIC ====================
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "1=1";
if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $where .= " AND code LIKE '%$search_esc%'";
}

$total_result = $conn->query("SELECT COUNT(*) as cnt FROM coupons WHERE $where");
$total_rows = $total_result->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $limit);

$coupons = $conn->query("SELECT * FROM coupons WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Toast and modal styles (already in courses.php, but added here for completeness) -->
<style>
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
    .toast.success { background: #06d6a0; }
    .toast.error { background: #ef476f; }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    #couponModal.hidden { display: none; }
</style>

<div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Coupon Management</h2>
        <button onclick="openCouponModal('add')" class="mt-3 md:mt-0 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            <i class="fas fa-plus-circle mr-2"></i> Add New Coupon
        </button>
    </div>

    <!-- Search -->
    <div class="mb-6">
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" placeholder="Search by coupon code..." value="<?php echo htmlspecialchars($search); ?>" 
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="?" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Coupons Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Used / Max</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Min Order</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valid</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($coupons && $coupons->num_rows > 0): ?>
                    <?php while ($row = $coupons->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition" data-id="<?php echo $row['id']; ?>">
                        <td class="px-4 py-3 text-sm"><?php echo $row['id']; ?></td>
                        <td class="px-4 py-3 text-sm font-mono font-bold"><?php echo htmlspecialchars($row['code']); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo ucfirst($row['discount_type']); ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo $row['discount_type'] == 'percentage' ? $row['discount_value'] . '%' : '₹' . number_format($row['discount_value'], 2); ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo $row['used_count'] . ' / ' . $row['max_uses']; ?>
                        </td>
                        <td class="px-4 py-3 text-sm">₹<?php echo number_format($row['min_order_amount'], 2); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo date('d M Y', strtotime($row['valid_from'])) . ' – ' . date('d M Y', strtotime($row['valid_to'])); ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $row['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm space-x-2">
                            <button onclick="openCouponModal('edit', <?php echo $row['id']; ?>)" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteCoupon(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">No coupons found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-center mt-6">
        <nav class="inline-flex rounded-md shadow">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-l-md">Previous</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                <span class="px-3 py-2 bg-indigo-600 border border-indigo-600 text-sm font-medium text-white"><?php echo $i; ?></span>
                <?php else: ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-r-md">Next</a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Coupon Modal (Add/Edit) -->
<div id="couponModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeCouponModal()"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
            <h3 class="text-lg font-bold mb-4" id="modalTitle">Add New Coupon</h3>
            <form id="couponForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="couponId">

                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Coupon Code *</label>
                    <input type="text" name="code" id="code" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 uppercase" placeholder="e.g., PERFECT10">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type</label>
                        <select name="discount_type" id="discount_type" class="w-full px-3 py-2 border rounded-lg">
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value *</label>
                        <input type="number" step="0.01" name="discount_value" id="discount_value" required class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., 10">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Uses</label>
                        <input type="number" name="max_uses" id="max_uses" value="1" min="1" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Order Amount</label>
                        <input type="number" step="0.01" name="min_order_amount" id="min_order_amount" value="0" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valid From *</label>
                        <input type="date" name="valid_from" id="valid_from" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valid To *</label>
                        <input type="date" name="valid_to" id="valid_to" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status" class="w-full px-3 py-2 border rounded-lg">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeCouponModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for AJAX -->
<script>
// Toast notification
function showToast(msg, type) {
    const msgDiv = document.createElement('div');
    msgDiv.className = `toast ${type}`;
    msgDiv.textContent = msg;
    document.body.appendChild(msgDiv);
    setTimeout(() => {
        msgDiv.style.opacity = '0';
        setTimeout(() => msgDiv.remove(), 500);
    }, 3000);
}

// Open modal for add/edit
function openCouponModal(action, id = null) {
    const modal = document.getElementById('couponModal');
    const form = document.getElementById('couponForm');
    const modalTitle = document.getElementById('modalTitle');

    form.reset();
    document.getElementById('couponId').value = '';
    document.getElementById('status').value = 'active';
    document.getElementById('max_uses').value = 1;
    document.getElementById('min_order_amount').value = 0;

    if (action === 'add') {
        modalTitle.innerText = 'Add New Coupon';
    } else if (action === 'edit' && id) {
        modalTitle.innerText = 'Edit Coupon';
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                ajax: 1, 
                action: 'get', 
                id: id,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const c = data.coupon;
                document.getElementById('couponId').value = c.id;
                document.getElementById('code').value = c.code;
                document.getElementById('discount_type').value = c.discount_type;
                document.getElementById('discount_value').value = c.discount_value;
                document.getElementById('max_uses').value = c.max_uses;
                document.getElementById('min_order_amount').value = c.min_order_amount;
                document.getElementById('valid_from').value = c.valid_from;
                document.getElementById('valid_to').value = c.valid_to;
                document.getElementById('status').value = c.status;
            } else {
                showToast(data.message, 'error');
            }
        });
    }
    modal.classList.remove('hidden');
}

function closeCouponModal() {
    document.getElementById('couponModal').classList.add('hidden');
}

// Handle form submission
document.getElementById('couponForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('ajax', '1');
    formData.append('action', 'save');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeCouponModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    });
});

// Delete coupon
function deleteCoupon(id) {
    if (!confirm('Are you sure you want to delete this coupon?')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            ajax: 1, 
            action: 'delete', 
            id: id,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            document.querySelector(`tr[data-id="${id}"]`).remove();
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>