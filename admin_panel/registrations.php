<?php
// registrations.php - Enhanced listing with admin note, quick add payment, and new columns
$pageTitle = 'Registrations';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Permission check for the whole page
if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_registrations')) {
    header("Location: unauthorized.php");
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== EXPORT TO EXCEL ====================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_registrations')) {
        die('Access denied');
    }

    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $whereConditions = [];
    $params = [];
    $types = '';

    // Soft delete condition
    $checkDeleted = $conn->query("SHOW COLUMNS FROM registrations LIKE 'is_deleted'");
    $hasSoftDelete = $checkDeleted && $checkDeleted->num_rows > 0;
    if ($hasSoftDelete) {
        $whereConditions[] = "(is_deleted IS NULL OR is_deleted = 0)";
    }

    if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
        $whereConditions[] = "status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    if (!empty($search)) {
        $whereConditions[] = "(full_name LIKE ? OR registration_id LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }
    $where_clause = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);

    $sql = "
        SELECT r.*, 
        (SELECT COALESCE(SUM(pp.amount), 0) FROM payments pp
         WHERE pp.registration_id = r.registration_id AND pp.payment_status = 'approved'
           AND (pp.is_deleted IS NULL OR pp.is_deleted = 0)) as fee_paid,
            (SELECT COUNT(*) FROM registrations r2 
             WHERE r2.email IS NOT NULL 
               AND r2.email = r.email 
               AND (r2.is_deleted IS NULL OR r2.is_deleted = 0)
            ) as reg_count
        FROM registrations r
        $where_clause
        ORDER BY r.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="registrations_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    // Headers – added new columns
    fputcsv($output, [
        'Registration ID',
        'Name',
        'Course',
        'Contact',
        'Email',
        'Rejoined (Total)',
        'Status',
        'Payment Status',
        'Total Amount (₹)',
        'Fee Paid (₹)',
        'Coupon Code',
        'Discount (₹)',
        'Net Amount (₹)',
        'Platform Fee (₹)',
        'GST (₹)',
        'Final Amount (₹)',
        'Payment Method',
        'Admin Note',
        'Registration Date'
    ]);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['registration_id'],
            $row['full_name'],
            $row['course_name'],
            $row['contact'],
            $row['email'],
            $row['reg_count'] > 1 ? 'Yes (' . $row['reg_count'] . ')' : 'No',
            $row['status'],
            $row['payment_status'],
            number_format($row['total_amount'] ?? 0, 2),
            number_format($row['fee_paid'] ?? 0, 2),
            $row['coupon_code'] ?? '',
            number_format($row['discount_amount'] ?? 0, 2),
            number_format($row['net_amount'] ?? 0, 2),
            number_format($row['platform_fee'] ?? 0, 2),
            number_format($row['gst_amount'] ?? 0, 2),
            number_format(($row['final_amount'] ?? 0) > 0 ? $row['final_amount'] : max(0, ($row['total_amount'] ?? 0) - ($row['discount_amount'] ?? 0)), 2),
            $row['payment_method'] ?? '',
            $row['admin_note'] ?? '',
            date('d M Y', strtotime($row['created_at']))
        ]);
    }

    fclose($output);
    exit;
}

// ==================== AJAX HANDLER ====================
if (isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject', 'delete'])) {
    header('Content-Type: application/json');

    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_registrations')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'];

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    if ($action == 'approve' || $action == 'reject') {
        $stmt = $conn->prepare("SELECT full_name, email, course_name, registration_id, final_amount FROM registrations WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
    }

    $success = false;
    $message = '';
    $affected = 0;

    if ($action == 'approve') {
        // When approving, also set payment_status to 'paid' if final_amount > 0 (assuming full payment)
        $stmt = $conn->prepare("UPDATE registrations SET status='approved' WHERE id=?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $affected = $stmt->affected_rows;
        if ($success) {
            syncRegistrationPaymentStatus($conn, $student['registration_id']);
        }
        $message = "Registration approved.";
    } elseif ($action == 'reject') {
        $stmt = $conn->prepare("UPDATE registrations SET status='rejected' WHERE id=?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $affected = $stmt->affected_rows;
        $message = "Registration rejected.";
    } elseif ($action == 'delete') {
        $check = $conn->query("SHOW COLUMNS FROM registrations LIKE 'is_deleted'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE registrations SET is_deleted=1 WHERE id=?");
        } else {
            $regIdStmt = $conn->prepare("SELECT registration_id FROM registrations WHERE id=?");
            $regIdStmt->bind_param('i', $id);
            $regIdStmt->execute();
            $regIdResult = $regIdStmt->get_result();
            if ($regRow = $regIdResult->fetch_assoc()) {
                $delPayments = $conn->prepare("DELETE FROM payments WHERE registration_id=?");
                $delPayments->bind_param('s', $regRow['registration_id']);
                $delPayments->execute();
            }
            $stmt = $conn->prepare("DELETE FROM registrations WHERE id=?");
        }
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $affected = $stmt->affected_rows;
        $message = "Registration deleted.";
    }

    if ($success && $affected > 0) {
        if ($action == 'approve' || $action == 'reject') {
            $mailerPath = __DIR__ . '/../mailer/mailer.php';
            if (file_exists($mailerPath)) {
                require_once $mailerPath;
                if (function_exists('buildEmailBody') && function_exists('sendEmail')) {
                    $extra = [
                        'reg_id' => $student['registration_id'],
                        'course' => $student['course_name'],
                        'amount' => $student['final_amount'] ?? 0
                    ];
                    $type = ($action == 'approve') ? 'approved' : 'rejected';
                    $subject = ($action == 'approve') ? "Admission Approved – Perfect Institute" : "Admission Status Update – Perfect Institute";
                    $body = buildEmailBody($student['full_name'], $type, $extra);
                    $altBody = ($action == 'approve')
                        ? "Your admission has been approved. Registration ID: {$student['registration_id']}."
                        : "Your admission has been rejected. Please contact support.";

                    sendEmail($student['email'], $student['full_name'], $subject, $body, $altBody);
                }
            }
        }

        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Operation failed or no changes.']);
    }
    exit;
}

// ==================== MAIN PAGE LOGIC ====================
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$whereConditions = [];
$params = [];
$types = '';

$checkDeleted = $conn->query("SHOW COLUMNS FROM registrations LIKE 'is_deleted'");
$hasSoftDelete = $checkDeleted && $checkDeleted->num_rows > 0;
if ($hasSoftDelete) {
    $whereConditions[] = "(is_deleted IS NULL OR is_deleted = 0)";
}

if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $whereConditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if (!empty($search)) {
    $whereConditions[] = "(full_name LIKE ? OR registration_id LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}
$where_clause = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);

// Count total
$countSql = "SELECT COUNT(*) as cnt FROM registrations $where_clause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total_rows = $countStmt->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $limit);

// Fetch with all columns
$sql = "
    SELECT r.*, 
        (SELECT COALESCE(SUM(pp.amount), 0) FROM payments pp
         WHERE pp.registration_id = r.registration_id AND pp.payment_status = 'approved'
           AND (pp.is_deleted IS NULL OR pp.is_deleted = 0)) as fee_paid,
        (SELECT COUNT(*) FROM registrations r2 
         WHERE r2.email IS NOT NULL 
           AND r2.email = r.email 
           AND (r2.is_deleted IS NULL OR r2.is_deleted = 0)
        ) as reg_count
    FROM registrations r
    $where_clause
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $allParams = array_merge($params, [$limit, $offset]);
    $types .= 'ii';
    $stmt->bind_param($types, ...$allParams);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$registrations = $stmt->get_result();

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Toast style -->
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
    .admin-note-cell {
        max-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: help;
    }
</style>

<div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Registrations</h2>
        <div class="flex gap-2 mt-3 md:mt-0">
            <a href="registration_add.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-plus-circle mr-2"></i> Add New Registration
            </a>
            <a href="?export=excel&<?php echo http_build_query(['status' => $status_filter, 'search' => $search]); ?>" 
               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-file-excel mr-2"></i> Export Excel
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-center">
        <span><?php echo htmlspecialchars($message); ?></span>
        <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Filters and Search -->
    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <div class="flex-1">
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" placeholder="Search by name, ID, email..." value="<?php echo htmlspecialchars($search); ?>" 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        <div class="flex gap-2">
            <a href="?status=pending<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
               class="px-4 py-2 <?php echo $status_filter=='pending' ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-lg transition">
                Pending
            </a>
            <a href="?status=approved<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
               class="px-4 py-2 <?php echo $status_filter=='approved' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-lg transition">
                Approved
            </a>
            <a href="?status=rejected<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
               class="px-4 py-2 <?php echo $status_filter=='rejected' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-lg transition">
                Rejected
            </a>
            <a href="?" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                Clear
            </a>
        </div>
    </div>

    <!-- Registrations Table – added new columns -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reg ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rejoined</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount (₹)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fee Paid (₹)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coupon</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount (₹)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Final Amount (₹)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admin Note</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($registrations && $registrations->num_rows > 0): ?>
                    <?php while ($r = $registrations->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition" data-id="<?php echo $r['id']; ?>">
                        <td class="px-4 py-3 text-sm font-mono"><?php echo htmlspecialchars($r['registration_id']); ?></td>
                        <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($r['full_name']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($r['course_name']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($r['contact']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($r['email']); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php 
                            $regCount = isset($r['reg_count']) ? (int)$r['reg_count'] : 0;
                            if ($regCount > 1): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800" title="This student has <?= $regCount ?> registrations">Yes (<?= $regCount ?>)</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 status-cell">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?php 
                                    if ($r['status'] == 'approved') echo 'bg-green-100 text-green-800';
                                    elseif ($r['status'] == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                    elseif ($r['status'] == 'rejected') echo 'bg-red-100 text-red-800';
                                    else echo 'bg-gray-100 text-gray-800';
                                ?>">
                                <?php echo $r['status']; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                            $psLabels = ['pending' => 'Pending', 'partial' => 'Partially Paid', 'paid' => 'Paid', 'refunded' => 'Refunded'];
                            $psClasses = ['pending' => 'bg-gray-100 text-gray-800', 'partial' => 'bg-yellow-100 text-yellow-800', 'paid' => 'bg-green-100 text-green-800', 'refunded' => 'bg-red-100 text-red-800'];
                            $ps = $r['payment_status'] ?? 'pending';
                            ?>
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $psClasses[$ps] ?? 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo htmlspecialchars($psLabels[$ps] ?? $ps); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">₹<?php echo number_format($r['total_amount'] ?? 0, 2); ?></td>
                        <td class="px-4 py-3 text-sm">₹<?php echo number_format($r['fee_paid'] ?? 0, 2); ?></td>
                        <td class="px-4 py-3 text-sm font-mono"><?php echo htmlspecialchars($r['coupon_code'] ?? ''); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo ($r['discount_amount'] ?? 0) > 0 ? '-₹' . number_format($r['discount_amount'], 2) : '—'; ?></td>
                        <td class="px-4 py-3 text-sm font-semibold">₹<?php
                            $finalAmt = (float)($r['final_amount'] ?? 0);
                            if ($finalAmt <= 0) { $finalAmt = max(0, (float)($r['total_amount'] ?? 0) - (float)($r['discount_amount'] ?? 0)); }
                            echo number_format($finalAmt, 2); ?></td>
                        <td class="px-4 py-3 text-sm admin-note-cell" title="<?php echo htmlspecialchars($r['admin_note'] ?? ''); ?>">
                            <?php echo htmlspecialchars(mb_strimwidth($r['admin_note'] ?? '', 0, 30, '...')); ?>
                        </td>
                        <td class="px-4 py-3 text-sm"><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                        <td class="px-4 py-3 text-sm space-x-2 actions-cell">
                            <?php if (file_exists('registration_view.php')): ?>
                            <a href="registration_view.php?id=<?php echo $r['id']; ?>" class="text-blue-600 hover:text-blue-800" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (file_exists('registration_edit.php')): ?>
                            <a href="registration_edit.php?id=<?php echo $r['id']; ?>" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($r['status'] == 'pending'): ?>
                            <button class="text-green-600 hover:text-green-800 ajax-action" title="Approve" data-action="approve" data-id="<?php echo $r['id']; ?>">
                                <i class="fas fa-check-circle"></i>
                            </button>
                            <button class="text-red-600 hover:text-red-800 ajax-action" title="Reject" data-action="reject" data-id="<?php echo $r['id']; ?>">
                                <i class="fas fa-times-circle"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (file_exists('payment_add.php')): ?>
                            <a href="payment_add.php?reg_id=<?php echo urlencode($r['registration_id']); ?>" class="text-green-600 hover:text-green-800" title="Add Payment">
                                <i class="fas fa-rupee-sign"></i>
                            </a>
                            <?php endif; ?>
                            <button class="text-red-600 hover:text-red-800 ajax-action" title="Delete" data-action="delete" data-id="<?php echo $r['id']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="16" class="px-4 py-8 text-center text-gray-500">No registrations found.</td>
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
            <a href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
               class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-l-md">Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                <span class="px-3 py-2 bg-indigo-600 border border-indigo-600 text-sm font-medium text-white"><?php echo $i; ?></span>
                <?php else: ?>
                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
                   class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
               class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-r-md">Next</a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- AJAX Script -->
<script>
const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ajax-action').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const action = this.dataset.action;
            const id = this.dataset.id;
            let confirmMsg = '';
            if (action === 'approve') confirmMsg = 'Approve this registration?';
            else if (action === 'reject') confirmMsg = 'Reject this registration?';
            else if (action === 'delete') confirmMsg = 'Are you sure you want to delete this registration? This action cannot be undone.';
            
            if (!confirm(confirmMsg)) return;

            this.style.pointerEvents = 'none';
            this.style.opacity = '0.5';

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: action, id: id, csrf_token: csrfToken })
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    const row = this.closest('tr');
                    if (action === 'delete') {
                        row.remove();
                    } else {
                        const statusCell = row.querySelector('.status-cell span');
                        if (statusCell) {
                            if (action === 'approve') {
                                statusCell.className = 'px-2 py-1 text-xs rounded-full bg-green-100 text-green-800';
                                statusCell.textContent = 'approved';
                            } else if (action === 'reject') {
                                statusCell.className = 'px-2 py-1 text-xs rounded-full bg-red-100 text-red-800';
                                statusCell.textContent = 'rejected';
                            }
                        }
                        const actionsCell = row.querySelector('.actions-cell');
                        if (actionsCell) {
                            const approveReject = actionsCell.querySelectorAll('.ajax-action[data-action="approve"], .ajax-action[data-action="reject"]');
                            approveReject.forEach(btn => btn.remove());
                        }
                    }
                } else {
                    showToast(data.message || 'Error occurred', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showToast('Network error: ' + error.message, 'error');
            })
            .finally(() => {
                this.style.pointerEvents = '';
                this.style.opacity = '';
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>