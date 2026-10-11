<?php
// payment_add.php - Manual payment addition for admin
$pageTitle = 'Add New Payment';
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

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// Pre-fill registration_id if passed via GET
$prefill_reg_id = trim($_GET['reg_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
        $registration_id = trim($_POST['registration_id'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? '';
        $transaction_id = trim($_POST['transaction_id'] ?? '');
        $payment_status = $_POST['payment_status'] ?? 'pending';
        $payer_name = trim($_POST['payer_name'] ?? '');
        $payer_mobile = trim($_POST['payer_mobile'] ?? '');
        $payer_email = trim($_POST['payer_email'] ?? '');

        // Basic validation
        if (empty($registration_id)) {
            $error = "Registration ID is required.";
        } elseif ($amount <= 0) {
            $error = "Amount must be greater than zero.";
        } elseif (!in_array($payment_method, ['upi', 'bank', 'cash'])) {
            $error = "Invalid payment method.";
        } else {
            // Verify registration exists and is not soft-deleted
            $checkStmt = $conn->prepare("SELECT id, full_name FROM registrations WHERE registration_id = ? AND (is_deleted IS NULL OR is_deleted = 0)");
            $checkStmt->bind_param('s', $registration_id);
            $checkStmt->execute();
            $reg = $checkStmt->get_result()->fetch_assoc();
            if (!$reg) {
                $error = "Registration ID not found or has been deleted.";
            } else {
                // Handle screenshot uploads
                $uploaded_files = [];
                if (!empty($_FILES['payment_screenshots']['name'][0])) {
                    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                    $target_dir = __DIR__ . '/../uploads/payments/';
                    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

                    foreach ($_FILES['payment_screenshots']['name'] as $index => $name) {
                        if ($_FILES['payment_screenshots']['error'][$index] !== UPLOAD_ERR_OK) continue;
                        $tmp_name = $_FILES['payment_screenshots']['tmp_name'][$index];
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed)) continue;
                        $unique_name = $registration_id . '_manual_' . time() . '_' . $index . '.' . $ext;
                        if (move_uploaded_file($tmp_name, $target_dir . $unique_name)) {
                            $uploaded_files[] = $unique_name;
                        }
                    }
                }
                $screenshots_json = json_encode($uploaded_files);

                // Insert payment
                $insertStmt = $conn->prepare("
                    INSERT INTO payments (registration_id, amount, payment_method, transaction_id, payment_status, screenshot, payer_name, payer_mobile, payer_email, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insertStmt->bind_param('sdsssssss', $registration_id, $amount, $payment_method, $transaction_id, $payment_status, $screenshots_json, $payer_name, $payer_mobile, $payer_email);

                if ($insertStmt->execute()) {
                    // Recalculate registration payment_status (pending / partial / paid)
                    syncRegistrationPaymentStatus($conn, $registration_id);

                    $success = "Payment added successfully for Registration ID: " . htmlspecialchars($registration_id);
                    // Clear form except registration_id
                    $_POST = [];
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="bg-white rounded-lg shadow-lg p-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Add New Payment</h2>
        <a href="payments.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i> Back to Payments
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Registration ID *</label>
                <input type="text" name="registration_id" required 
                       value="<?= htmlspecialchars($prefill_reg_id ?: ($_POST['registration_id'] ?? '')) ?>"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                <p class="text-xs text-gray-500 mt-1">Enter the student's registration ID (e.g., REG2026XXXXX).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" required 
                       value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                <select name="payment_method" required class="w-full px-3 py-2 border rounded-lg">
                    <option value="">Select Method</option>
                    <option value="upi" <?= ($_POST['payment_method'] ?? '') === 'upi' ? 'selected' : '' ?>>UPI</option>
                    <option value="bank" <?= ($_POST['payment_method'] ?? '') === 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="cash" <?= ($_POST['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Transaction ID (Optional)</label>
                <input type="text" name="transaction_id" 
                       value="<?= htmlspecialchars($_POST['transaction_id'] ?? '') ?>"
                       class="w-full px-3 py-2 border rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                <select name="payment_status" class="w-full px-3 py-2 border rounded-lg">
                    <option value="pending" <?= (($_POST['payment_status'] ?? 'pending') === 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= (($_POST['payment_status'] ?? '') === 'approved') ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= (($_POST['payment_status'] ?? '') === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payer Name (Optional)</label>
                <input type="text" name="payer_name" 
                       value="<?= htmlspecialchars($_POST['payer_name'] ?? '') ?>"
                       class="w-full px-3 py-2 border rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payer Mobile (Optional)</label>
                <input type="text" name="payer_mobile" 
                       value="<?= htmlspecialchars($_POST['payer_mobile'] ?? '') ?>"
                       class="w-full px-3 py-2 border rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payer Email (Optional)</label>
                <input type="email" name="payer_email" 
                       value="<?= htmlspecialchars($_POST['payer_email'] ?? '') ?>"
                       class="w-full px-3 py-2 border rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Screenshots (Optional, multiple)</label>
                <input type="file" name="payment_screenshots[]" multiple accept=".jpg,.jpeg,.png,.pdf" class="w-full px-3 py-2 border rounded-lg">
                <p class="text-xs text-gray-500 mt-1">You can select multiple files. Max 5MB each.</p>
            </div>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
            <a href="payments.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-save mr-2"></i> Save Payment
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>