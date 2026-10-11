<?php
// payments.php - Admin panel payments page with Add Payment, Manual Email, and full CRUD
$pageTitle = 'Payments';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_payments')) {
    header("Location: unauthorized.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== EXPORT TO EXCEL ====================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_payments')) {
        die('Access denied');
    }

    $status_filter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');

    $where = [];
    $params = [];
    $types = '';

    $hasSoftDelete = $conn->query("SHOW COLUMNS FROM registrations LIKE 'is_deleted'")->num_rows > 0;
    if ($hasSoftDelete) {
        $where[] = "(r.is_deleted IS NULL OR r.is_deleted = 0)";
    }

    if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
        $where[] = "p.payment_status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    if (!empty($search)) {
        $where[] = "(p.registration_id LIKE ? OR p.payer_name LIKE ? OR r.full_name LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }
    $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "
        SELECT 
            p.*,
            r.full_name, r.email, r.total_amount AS course_fee,
            CASE WHEN r.net_amount > 0 THEN r.net_amount ELSE GREATEST(COALESCE(r.total_amount,0) - COALESCE(r.discount_amount,0), 0) END AS course_fee_net,
            r.coupon_code, r.discount_amount, r.net_amount,
            r.platform_fee, r.gst_amount, r.final_amount,
            (SELECT COALESCE(SUM(p2.amount), 0) 
             FROM payments p2
             JOIN registrations r2 ON p2.registration_id = r2.registration_id
             WHERE p2.registration_id = r.registration_id 
               AND p2.payment_status = 'approved'
               AND (r2.is_deleted IS NULL OR r2.is_deleted = 0)
            ) AS total_paid,
            (SELECT COUNT(*) FROM registrations r2 
             WHERE r2.email = r.email 
               AND (r2.is_deleted IS NULL OR r2.is_deleted = 0)
            ) AS reg_count
        FROM payments p
        LEFT JOIN registrations r ON p.registration_id = r.registration_id
        $whereSQL
        ORDER BY p.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="payments_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'Payment ID', 'Registration ID', 'Student', 'Email',
        'This Payment (₹)', 'Platform Fee + GST (₹)', 'Coupon', 'Discount (₹)',
        'Total Paid (₹)', 'Total Course Fee after Discount (₹)', 'Method',
        'Transaction ID', 'Status', 'Date'
    ]);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['registration_id'] ?? '',
            $row['payer_name'] ?: $row['full_name'] ?? '',
            $row['email'] ?? '',
            number_format($row['amount'], 2),
            number_format(($row['platform_fee'] ?? 0) + ($row['gst_amount'] ?? 0), 2),
            $row['coupon_code'] ?? '',
            number_format($row['discount_amount'] ?? 0, 2),
            number_format($row['total_paid'] ?? 0, 2),
            number_format($row['course_fee_net'] ?? 0, 2),
            $row['payment_method'] ?? '',
            $row['transaction_id'] ?? '',
            $row['payment_status'] ?? '',
            date('d M Y', strtotime($row['created_at']))
        ]);
    }

    fclose($output);
    exit;
}

// ==================== AJAX HANDLER ====================
if (isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject', 'delete', 'edit', 'send_email'])) {
    header('Content-Type: application/json');

    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_payments')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'];

    // ----- SEND MANUAL EMAIL -----
    if ($action === 'send_email') {
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT p.*, r.full_name, r.email, r.course_name, r.registration_id
            FROM payments p
            JOIN registrations r ON p.registration_id = r.registration_id
            WHERE p.id = ? AND (r.is_deleted IS NULL OR r.is_deleted = 0)
        ");
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();

        if (!$payment) {
            echo json_encode(['success' => false, 'message' => 'Payment or student not found']);
            exit;
        }

        $mailerPath = __DIR__ . '/../mailer/mailer.php';
        if (!file_exists($mailerPath)) {
            echo json_encode(['success' => false, 'message' => 'Mailer not configured']);
            exit;
        }

        require_once $mailerPath;
        $emailSent = false;
        if (function_exists('sendManualPaymentEmail')) {
            $emailSent = sendManualPaymentEmail($payment_id, $conn);
        } else {
            if (function_exists('buildEmailBody') && function_exists('sendEmail')) {
                $extra = [
                    'reg_id' => $payment['registration_id'],
                    'course' => $payment['course_name'],
                    'amount' => $payment['amount']
                ];
                $subject = "Payment Confirmation – Perfect Institute (Ref: {$payment['registration_id']})";
                $body = buildEmailBody($payment['full_name'], 'payment_received', $extra);
                $altBody = "Your payment of ₹{$payment['amount']} has been recorded. Registration ID: {$payment['registration_id']}.";
                $emailSent = sendEmail($payment['email'], $payment['full_name'], $subject, $body, $altBody);
            }
        }

        echo json_encode(['success' => $emailSent, 'message' => $emailSent ? 'Email sent successfully' : 'Failed to send email']);
        exit;
    }

    // For approve/reject/delete/edit actions, we need an ID
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    // Fetch payment (delete also needs it to resync the registration afterwards)
    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }

    // For approve/reject, we need student details
    if ($action === 'approve' || $action === 'reject') {
        $stmt = $conn->prepare("SELECT full_name, email, course_name, registration_id FROM registrations WHERE registration_id = ? AND (is_deleted IS NULL OR is_deleted = 0)");
        $stmt->bind_param('s', $payment['registration_id']);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
    }

    $success = false;
    $message = '';
    $new_total_paid = null;

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE payments SET payment_status='approved' WHERE id=?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $message = "Payment approved successfully.";
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE payments SET payment_status='rejected' WHERE id=?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $message = "Payment rejected.";
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM payments WHERE id=?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $message = "Payment deleted.";
    } elseif ($action === 'edit') {
        $amount = (float)($_POST['amount'] ?? 0);
        $transaction_id = trim($_POST['transaction_id'] ?? '');
        $payment_method = $_POST['payment_method'] ?? '';
        $payment_status = $_POST['payment_status'] ?? '';

        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']);
            exit;
        }

        // Handle new screenshot uploads
        $uploaded_files = [];
        if (!empty($_FILES['new_screenshots']['name'][0])) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            $target_dir = __DIR__ . '/../uploads/payments/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

            foreach ($_FILES['new_screenshots']['name'] as $index => $name) {
                if ($_FILES['new_screenshots']['error'][$index] !== UPLOAD_ERR_OK) continue;
                $tmp_name = $_FILES['new_screenshots']['tmp_name'][$index];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) continue;
                $unique_name = $payment['registration_id'] . '_edit_' . time() . '_' . $index . '.' . $ext;
                if (move_uploaded_file($tmp_name, $target_dir . $unique_name)) {
                    $uploaded_files[] = $unique_name;
                }
            }
        }

        $existing = json_decode($payment['screenshot'] ?? '[]', true) ?: [];
        $all_screenshots = array_merge($existing, $uploaded_files);
        $screenshots_json = json_encode($all_screenshots);

        $stmt = $conn->prepare("UPDATE payments SET amount=?, transaction_id=?, payment_method=?, payment_status=?, screenshot=? WHERE id=?");
        $stmt->bind_param('dssssi', $amount, $transaction_id, $payment_method, $payment_status, $screenshots_json, $id);
        $success = $stmt->execute();
        $message = "Payment updated successfully.";
    }

    // Keep registrations.payment_status (pending / partial / paid) in step with payments
    if ($success && isset($payment['registration_id'])) {
        syncRegistrationPaymentStatus($conn, $payment['registration_id']);
    }

    // After success, compute new total_paid if applicable
    if ($success && in_array($action, ['approve', 'reject', 'edit'])) {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(p.amount), 0) as total_paid 
            FROM payments p
            JOIN registrations r ON p.registration_id = r.registration_id
            WHERE p.registration_id = ? AND p.payment_status = 'approved'
            AND (r.is_deleted IS NULL OR r.is_deleted = 0)
        ");
        $stmt->bind_param('s', $payment['registration_id']);
        $stmt->execute();
        $new_total_paid = $stmt->get_result()->fetch_assoc()['total_paid'];
    }

    if ($success) {
        // Send email for approve/reject (only if student exists)
        if (($action === 'approve' || $action === 'reject') && isset($student) && $student) {
            $mailerPath = __DIR__ . '/../mailer/mailer.php';
            if (file_exists($mailerPath)) {
                require_once $mailerPath;
                if (function_exists('buildEmailBody') && function_exists('sendEmail')) {
                    $extra = [
                        'reg_id' => $student['registration_id'],
                        'course' => $student['course_name'],
                        'amount' => $payment['amount']
                    ];
                    $type = ($action === 'approve') ? 'approved' : 'rejected';
                    $subject = ($action === 'approve') ? "Payment Approved – Perfect Institute" : "Payment Status Update – Perfect Institute";
                    $body = buildEmailBody($student['full_name'], $type, $extra);
                    $altBody = ($action === 'approve') 
                        ? "Your payment of ₹{$payment['amount']} has been approved."
                        : "Your payment of ₹{$payment['amount']} has been rejected.";
                    sendEmail($student['email'], $student['full_name'], $subject, $body, $altBody);
                }
            }
        }

        $response = ['success' => true, 'message' => $message];
        if ($new_total_paid !== null) {
            $response['total_paid'] = $new_total_paid;
            $response['registration_id'] = $payment['registration_id'];
        }
        if ($action === 'edit') {
            $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $response['payment'] = $stmt->get_result()->fetch_assoc();
        }
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Operation failed']);
    }
    exit;
}

// ==================== MAIN PAGE LOGIC ====================
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
$types = '';

$hasSoftDelete = $conn->query("SHOW COLUMNS FROM registrations LIKE 'is_deleted'")->num_rows > 0;
if ($hasSoftDelete) {
    $where[] = "(r.is_deleted IS NULL OR r.is_deleted = 0)";
}

if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $where[] = "p.payment_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if (!empty($search)) {
    $where[] = "(p.registration_id LIKE ? OR p.payer_name LIKE ? OR r.full_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$countSQL = "SELECT COUNT(*) AS cnt FROM payments p LEFT JOIN registrations r ON p.registration_id = r.registration_id $whereSQL";
$countStmt = $conn->prepare($countSQL);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total_rows = $countStmt->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $limit);

// Fetch payments with new columns from registrations
$sql = "
    SELECT 
        p.*,
        r.full_name, r.email, r.total_amount AS course_fee,
            CASE WHEN r.net_amount > 0 THEN r.net_amount ELSE GREATEST(COALESCE(r.total_amount,0) - COALESCE(r.discount_amount,0), 0) END AS course_fee_net,
        r.coupon_code, r.discount_amount, r.net_amount,
        r.platform_fee, r.gst_amount, r.final_amount,
        (SELECT COALESCE(SUM(p2.amount), 0) 
         FROM payments p2
         JOIN registrations r2 ON p2.registration_id = r2.registration_id
         WHERE p2.registration_id = r.registration_id 
           AND p2.payment_status = 'approved'
           AND (r2.is_deleted IS NULL OR r2.is_deleted = 0)
        ) AS total_paid,
        (SELECT COUNT(*) FROM registrations r2 
         WHERE r2.email = r.email 
           AND (r2.is_deleted IS NULL OR r2.is_deleted = 0)
        ) AS reg_count
    FROM payments p
    LEFT JOIN registrations r ON p.registration_id = r.registration_id
    $whereSQL
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$allParams = $params;
$allParams[] = $limit;
$allParams[] = $offset;
$allTypes = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$payments = $stmt->get_result();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Toast & Modal Styles -->
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
    .screenshot-list { list-style: none; padding: 0; margin: 0; }
    .screenshot-list li { display: inline-block; margin-right: 5px; margin-bottom: 5px; }
    .screenshot-list li a { 
        display: inline-block; 
        padding: 4px 8px; 
        background: #eef2f6; 
        border-radius: 4px; 
        font-size: 12px; 
        color: #1e40af; 
        text-decoration: none;
        transition: all 0.2s;
    }
    .screenshot-list li a:hover { background: #d1d5db; }
    .tooltip {
        position: relative;
        cursor: help;
    }
    .tooltip:hover::after {
        content: attr(data-tip);
        position: absolute;
        background: #333;
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 100;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
    }
</style>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeEditModal()"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center border-b border-gray-200 pb-3 mb-4">
                    <h3 class="text-xl font-semibold text-gray-900" id="modal-title">
                        <i class="fas fa-edit mr-2 text-indigo-600"></i>Edit Payment
                    </h3>
                    <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="editForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="edit">

                    <div class="space-y-4">
                        <!-- Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">₹</span>
                                <input type="number" step="0.01" min="0.01" name="amount" id="edit_amount" required
                                       class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>

                        <!-- Transaction ID -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
                            <input type="text" name="transaction_id" id="edit_transaction_id" placeholder="e.g., UPI reference number"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="text-xs text-gray-500 mt-1">Optional – enter if available</p>
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" id="edit_payment_method" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="upi">UPI</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="cash">Cash</option>
                            </select>
                        </div>

                        <!-- Payment Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                            <select name="payment_status" id="edit_payment_status" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <!-- Add New Screenshots -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Add New Screenshots</label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-indigo-400 transition">
                                <input type="file" name="new_screenshots[]" multiple accept=".jpg,.jpeg,.png,.pdf" 
                                       class="hidden" id="fileInput">
                                <label for="fileInput" class="cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2"></i>
                                    <p class="text-sm text-gray-600">Click to select or drag & drop</p>
                                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, PDF (Max 5MB each)</p>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Existing screenshots will be kept. New files will be added.</p>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                <button type="button" id="modalUpdateBtn" 
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                    <i class="fas fa-save mr-2"></i> Update Payment
                </button>
                <button type="button" onclick="closeEditModal()" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Payments</h2>
        <div class="flex gap-2 mt-3 md:mt-0">
            <a href="payment_add.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-plus-circle mr-2"></i> Add New Payment
            </a>
            <a href="?export=excel&<?php echo http_build_query(['status' => $status_filter, 'search' => $search]); ?>" 
               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-file-excel mr-2"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col md:flex-row gap-4 mb-6">
        <form method="GET" class="flex-1 flex gap-2">
            <input type="text" name="search" placeholder="Search by Reg ID, Student..." value="<?= htmlspecialchars($search ?? '') ?>" class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"><i class="fas fa-search"></i></button>
        </form>
        <div class="flex gap-2">
            <a href="?status=pending<?= $search ? "&search=".urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg transition <?= $status_filter=='pending' ? 'bg-yellow-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">Pending</a>
            <a href="?status=approved<?= $search ? "&search=".urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg transition <?= $status_filter=='approved' ? 'bg-green-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">Approved</a>
            <a href="?status=rejected<?= $search ? "&search=".urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg transition <?= $status_filter=='rejected' ? 'bg-red-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">Rejected</a>
            <a href="?" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Clear</a>
        </div>
    </div>

    <!-- Table with new columns – all htmlspecialchars are null-safe -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reg ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rejoined</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">This Payment</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Platform Fee + GST</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coupon</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Paid</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Course Fee <span class="normal-case font-normal">(after discount)</span></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Txn ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Screenshots</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($payments && $payments->num_rows > 0): ?>
                    <?php while ($p = $payments->fetch_assoc()): 
                        $total_paid = $p['total_paid'] ?? 0;
                        // Platform fee + GST (registration level: r.* columns override p.* in this query)
                        $feeAndGst = (float)($p['platform_fee'] ?? 0) + (float)($p['gst_amount'] ?? 0);
                    ?>
                    <tr data-id="<?= htmlspecialchars($p['id'] ?? '') ?>" data-reg-id="<?= htmlspecialchars($p['registration_id'] ?? '') ?>">
                        <td class="px-4 py-3 text-sm"><?= htmlspecialchars($p['id'] ?? '') ?></td>
                        <td class="px-4 py-3 text-sm font-mono"><?= htmlspecialchars($p['registration_id'] ?? '') ?></td>
                        <td class="px-4 py-3 text-sm"><?= htmlspecialchars($p['payer_name'] ?? $p['full_name'] ?? '') ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php if (($p['reg_count'] ?? 0) > 1): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">Yes (<?= htmlspecialchars($p['reg_count'] ?? 0) ?>)</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm amount font-semibold">₹<?= number_format($p['amount'] ?? 0, 2) ?></td>
                        <td class="px-4 py-3 text-sm platform-fee">₹<?= number_format($feeAndGst, 2) ?></td>
                        <td class="px-4 py-3 text-sm font-mono"><?= htmlspecialchars(($p['coupon_code'] ?? '') !== '' ? $p['coupon_code'] : '—') ?></td>
                        <td class="px-4 py-3 text-sm discount-amount"><?= ($p['discount_amount'] ?? 0) > 0 ? '-₹' . number_format($p['discount_amount'], 2) : '—' ?></td>
                        <td class="px-4 py-3 text-sm total-paid">₹<?= number_format($total_paid, 2) ?></td>
                        <td class="px-4 py-3 text-sm final-amount">₹<?= number_format($p['course_fee_net'] ?? 0, 2) ?></td>
                        <td class="px-4 py-3 text-sm method"><?= htmlspecialchars(ucfirst($p['payment_method'] ?? '—')) ?></td>
                        <td class="px-4 py-3 text-sm txn-id"><?= htmlspecialchars($p['transaction_id'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-sm screenshots-cell">
                            <?php
                            $screenshots = json_decode($p['screenshot'] ?? '[]', true) ?: [];
                            if (!empty($screenshots)): ?>
                                <ul class="screenshot-list">
                                <?php foreach ($screenshots as $file): ?>
                                    <li><a href="../uploads/payments/<?= urlencode($file) ?>" target="_blank"><i class="fas fa-image"></i> <?= htmlspecialchars(basename($file)) ?></a></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="px-4 py-3 status-cell">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?= ($p['payment_status'] ?? '') === 'approved' ? 'bg-green-100 text-green-800' : 
                                   (($p['payment_status'] ?? '') === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                <?= htmlspecialchars($p['payment_status'] ?? 'pending') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm"><?= !empty($p['created_at']) ? date('d M Y', strtotime($p['created_at'])) : '—' ?></td>
                        <td class="px-4 py-3 text-sm space-x-2 actions-cell">
                            <button class="text-blue-600 hover:text-blue-800 edit-btn" 
                                    data-id="<?= htmlspecialchars($p['id'] ?? '') ?>" 
                                    data-amount="<?= htmlspecialchars($p['amount'] ?? '') ?>" 
                                    data-txn="<?= htmlspecialchars($p['transaction_id'] ?? '') ?>" 
                                    data-method="<?= htmlspecialchars($p['payment_method'] ?? '') ?>" 
                                    data-status="<?= htmlspecialchars($p['payment_status'] ?? '') ?>" 
                                    title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="text-purple-600 hover:text-purple-800 send-email-btn" 
                                    data-id="<?= htmlspecialchars($p['id'] ?? '') ?>" 
                                    title="Send Email"><i class="fas fa-envelope"></i></button>
                            <?php if (($p['payment_status'] ?? '') === 'pending'): ?>
                                <button class="text-green-600 hover:text-green-800 ajax-action" data-action="approve" data-id="<?= htmlspecialchars($p['id'] ?? '') ?>" title="Approve"><i class="fas fa-check-circle"></i></button>
                                <button class="text-red-600 hover:text-red-800 ajax-action" data-action="reject" data-id="<?= htmlspecialchars($p['id'] ?? '') ?>" title="Reject"><i class="fas fa-times-circle"></i></button>
                            <?php endif; ?>
                            <button class="text-red-600 hover:text-red-800 ajax-action" data-action="delete" data-id="<?= htmlspecialchars($p['id'] ?? '') ?>" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="16" class="text-center py-8 text-gray-500">No payments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-center mt-6">
        <nav class="inline-flex rounded-md shadow">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&status=<?= urlencode($status_filter ?? '') ?>&search=<?= urlencode($search ?? '') ?>" class="px-3 py-2 bg-white border rounded-l-md hover:bg-gray-50">Previous</a>
            <?php endif; ?>
            <?php for ($i=1; $i<=$total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="px-3 py-2 bg-indigo-600 text-white border"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter ?? '') ?>&search=<?= urlencode($search ?? '') ?>" class="px-3 py-2 bg-white border hover:bg-gray-50"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&status=<?= urlencode($status_filter ?? '') ?>&search=<?= urlencode($search ?? '') ?>" class="px-3 py-2 bg-white border rounded-r-md hover:bg-gray-50">Next</a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';

function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function updateTotalPaidForRegistration(regId, newTotal) {
    document.querySelectorAll(`tr[data-reg-id="${regId}"]`).forEach(row => {
        const totalPaidCell = row.querySelector('.total-paid');
        if (totalPaidCell) {
            totalPaidCell.innerHTML = '₹' + parseFloat(newTotal).toFixed(2);
        }
    });
}

// Modal logic
const modal = document.getElementById('editModal');

function closeEditModal() {
    modal.classList.add('hidden');
}

// Close on backdrop click
modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        closeEditModal();
    }
});

// Edit button handlers
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_amount').value = this.dataset.amount;
        document.getElementById('edit_transaction_id').value = this.dataset.txn;
        document.getElementById('edit_payment_method').value = this.dataset.method;
        document.getElementById('edit_payment_status').value = this.dataset.status;
        modal.classList.remove('hidden');
    });
});

// Update button handler
document.getElementById('modalUpdateBtn').addEventListener('click', function() {
    document.getElementById('editForm').dispatchEvent(new Event('submit'));
});

// Form submit
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    const updateBtn = document.getElementById('modalUpdateBtn');
    const originalText = updateBtn.innerHTML;
    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
    updateBtn.disabled = true;

    fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeEditModal();
                if (data.total_paid !== undefined) {
                    updateTotalPaidForRegistration(data.registration_id, data.total_paid);
                }
                const row = document.querySelector(`tr[data-id="${formData.get('id')}"]`);
                if (row && data.payment) {
                    row.querySelector('.amount').innerHTML = '₹' + parseFloat(data.payment.amount).toFixed(2);
                    row.querySelector('.method').textContent = data.payment.payment_method.charAt(0).toUpperCase() + data.payment.payment_method.slice(1);
                    row.querySelector('.txn-id').textContent = data.payment.transaction_id || '—';
                    
                    const statusSpan = row.querySelector('.status-cell span');
                    const statusClass = data.payment.payment_status === 'approved' ? 'bg-green-100 text-green-800' :
                                       (data.payment.payment_status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
                    statusSpan.className = `px-2 py-1 text-xs rounded-full ${statusClass}`;
                    statusSpan.textContent = data.payment.payment_status;
                    
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Network error: ' + error.message, 'error');
        })
        .finally(() => {
            updateBtn.innerHTML = originalText;
            updateBtn.disabled = false;
        });
});

// AJAX actions (approve/reject/delete)
document.querySelectorAll('.ajax-action').forEach(btn => {
    btn.addEventListener('click', function() {
        const action = this.dataset.action;
        const id = this.dataset.id;
        let confirmMsg = `Are you sure you want to ${action} this payment?`;
        if (action === 'delete') confirmMsg = 'Are you sure you want to delete this payment? This action cannot be undone.';
        
        if (!confirm(confirmMsg)) return;
        
        this.style.pointerEvents = 'none';
        this.style.opacity = '0.5';

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action, id, csrf_token: csrfToken })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                const row = this.closest('tr');
                if (action === 'delete') {
                    row.remove();
                } else {
                    if (data.total_paid !== undefined) {
                        updateTotalPaidForRegistration(data.registration_id, data.total_paid);
                    }
                    const statusSpan = row.querySelector('.status-cell span');
                    statusSpan.className = 'px-2 py-1 text-xs rounded-full ' + 
                        (action === 'approve' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
                    statusSpan.textContent = action === 'approve' ? 'approved' : 'rejected';
                    row.querySelectorAll('.ajax-action[data-action="approve"], .ajax-action[data-action="reject"]').forEach(b => b.remove());
                }
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Error: ' + error.message, 'error');
        })
        .finally(() => {
            this.style.pointerEvents = '';
            this.style.opacity = '';
        });
    });
});

// Manual email
document.querySelectorAll('.send-email-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        if (!confirm('Send payment confirmation email to student?')) return;
        
        this.style.pointerEvents = 'none';
        this.style.opacity = '0.5';
        
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'send_email', payment_id: id, csrf_token: csrfToken })
        })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
        })
        .finally(() => {
            this.style.pointerEvents = '';
            this.style.opacity = '';
        });
    });
});

// File drag & drop
const fileInput = document.getElementById('fileInput');
const dropZone = fileInput?.closest('.border-dashed');

if (dropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, e => {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('border-indigo-400', 'bg-indigo-50'));
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('border-indigo-400', 'bg-indigo-50'));
    });
    
    dropZone.addEventListener('drop', e => {
        fileInput.files = e.dataTransfer.files;
    });
}
</script>

<?php include 'includes/footer.php'; ?>