<?php
// registration_edit.php - Secure edit page with new columns (read-only for financial fields)
$pageTitle = 'Edit Registration';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_registrations')) {
    header("Location: unauthorized.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

// Fetch registration data
$stmt = $conn->prepare("SELECT * FROM registrations WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();
if (!$reg) {
    $_SESSION['error'] = "Registration not found.";
    header("Location: registrations.php");
    exit;
}

// Check if this email has multiple registrations (excluding current)
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM registrations WHERE email = ? AND id != ?");
$stmt->bind_param('si', $reg['email'], $id);
$stmt->execute();
$otherCount = $stmt->get_result()->fetch_assoc()['cnt'];
$isRejoined = ($otherCount > 0);

// Fetch courses for dropdown
$coursesResult = $conn->query("SELECT title, type FROM courses WHERE is_deleted = 0 ORDER BY title");
$courses = [];
while ($row = $coursesResult->fetch_assoc()) {
    $courses[] = $row;
}

$id_types = ['Aadhaar', 'PAN', 'Voter ID', 'Passport', 'Other'];

// ==================== PROCESS FORM SUBMISSION ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    // Sanitize inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? 'Male';
    $nationality = trim($_POST['nationality'] ?? 'Indian');
    $id_type = $_POST['id_type'] ?? 'Aadhaar';
    $id_number = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id_number'] ?? '');
    $contact = preg_replace('/[^0-9]/', '', $_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? 'India');
    $education_level = trim($_POST['education_level'] ?? '');
    $organisation = trim($_POST['organisation'] ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $add_course = !empty($_POST['add_course']) ? trim($_POST['add_course']) : null;
    $course_start_date = !empty($_POST['course_start_date']) ? $_POST['course_start_date'] : null;
    $admission_date = !empty($_POST['admission_date']) ? $_POST['admission_date'] : null;
    $status = $_POST['status'] ?? 'pending';
    $payment_status = $_POST['payment_status'] ?? 'pending';
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    if (!in_array($payment_status, ['pending', 'partial', 'paid', 'refunded'], true)) {
        $payment_status = 'pending';
    }
    // Discount comes from the coupon on the registration (read-only); recompute net & final from the total
    $discount_amount = min((float)($reg['discount_amount'] ?? 0), $total_amount);
    $net_amount = max(0, $total_amount - $discount_amount);
    $final_amount = $net_amount + (float)($reg['platform_fee'] ?? 0) + (float)($reg['gst_amount'] ?? 0);
    $admin_note = trim($_POST['admin_note'] ?? '');

    // Validation
    if (empty($full_name) || empty($contact) || empty($email) || empty($course_name)) {
        $error = "Full Name, Contact, Email, and Course are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!empty($id_number) && $id_type === 'Aadhaar' && strlen($id_number) != 12) {
        $error = "Aadhaar number must be exactly 12 digits.";
    } else {
        if (empty($error)) {
            // Query table columns once
            $cols = $conn->query("SHOW COLUMNS FROM registrations")->fetch_all(MYSQLI_ASSOC);
            $existingColumns = array_column($cols, 'Field');

            $setParts = [];
            $params = [];
            $types = '';

            // Define all possible editable fields
            $editableFields = [
                'full_name' => 's',
                'dob' => 's',
                'gender' => 's',
                'nationality' => 's',
                'contact' => 's',
                'email' => 's',
                'city' => 's',
                'state' => 's',
                'country' => 's',
                'education_level' => 's',
                'organisation' => 's',
                'course_name' => 's',
                'add_course' => 's',
                'course_start_date' => 's',
                'admission_date' => 's',
                'status' => 's',
                'payment_status' => 's',
                'total_amount' => 'd',
                'net_amount' => 'd',
                'final_amount' => 'd'
            ];

            // Add new columns if they exist
            if (in_array('id_type', $existingColumns)) {
                $editableFields['id_type'] = 's';
                $editableFields['id_number'] = 's';
            } elseif (in_array('aadhaar_number', $existingColumns)) {
                $editableFields['aadhaar_number'] = 's';
            }
            if (in_array('admin_note', $existingColumns)) {
                $editableFields['admin_note'] = 's';
            }

            // Map values
            $fieldValues = [
                'full_name' => $full_name,
                'dob' => $dob,
                'gender' => $gender,
                'nationality' => $nationality,
                'contact' => $contact,
                'email' => $email,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'education_level' => $education_level,
                'organisation' => $organisation,
                'course_name' => $course_name,
                'add_course' => $add_course,
                'course_start_date' => $course_start_date,
                'admission_date' => $admission_date,
                'status' => $status,
                'payment_status' => $payment_status,
                'total_amount' => $total_amount,
                'net_amount' => $net_amount,
                'final_amount' => $final_amount
            ];

            if (in_array('id_type', $existingColumns)) {
                $fieldValues['id_type'] = $id_type;
                $fieldValues['id_number'] = $id_number;
            } elseif (in_array('aadhaar_number', $existingColumns)) {
                $fieldValues['aadhaar_number'] = $id_number;
            }
            if (in_array('admin_note', $existingColumns)) {
                $fieldValues['admin_note'] = $admin_note;
            }

            // Build SET and params
            foreach ($editableFields as $col => $type) {
                if (in_array($col, $existingColumns) && array_key_exists($col, $fieldValues)) {
                    $setParts[] = "$col = ?";
                    $params[] = $fieldValues[$col];
                    $types .= $type;
                }
            }

            if (empty($setParts)) {
                $error = "No fields to update.";
            } else {
                $sql = "UPDATE registrations SET " . implode(", ", $setParts) . " WHERE id = ?";
                $params[] = $id;
                $types .= 'i';

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Registration updated successfully.";
                    header("Location: registrations.php");
                    exit;
                } else {
                    $error = "Update failed: " . $conn->error;
                }
            }
        }
    }
}

// Fee paid so far and final amount for display
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS paid FROM payments WHERE registration_id = ? AND payment_status = 'approved' AND (is_deleted IS NULL OR is_deleted = 0)");
$stmt->bind_param('s', $reg['registration_id']);
$stmt->execute();
$feePaid = (float)$stmt->get_result()->fetch_assoc()['paid'];
$finalDisplay = (float)($reg['final_amount'] ?? 0);
if ($finalDisplay <= 0) {
    $finalDisplay = max(0, (float)($reg['total_amount'] ?? 0) - (float)($reg['discount_amount'] ?? 0));
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Edit Registration: <?php echo htmlspecialchars($reg['registration_id'] ?? ''); ?></h2>
        <div class="space-x-2">
            <a href="registration_view.php?id=<?php echo (int)($reg['id'] ?? 0); ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i> Back to View
            </a>
        </div>
    </div>

    <?php if ($isRejoined): ?>
        <div class="mb-4 p-3 bg-purple-50 border-l-4 border-purple-500 rounded">
            <p class="text-purple-800">
                <i class="fas fa-users mr-2"></i> 
                This student has <?php echo (int)$otherCount; ?> other registration(s) with the same email. 
                <a href="registrations.php?search=<?php echo urlencode($reg['email'] ?? ''); ?>" class="underline">View all</a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error ?? ''); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Personal Information -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Personal Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($reg['full_name'] ?? ''); ?>" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" name="dob" value="<?php echo htmlspecialchars($reg['dob'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                        <select name="gender" class="w-full px-3 py-2 border rounded-lg">
                            <option value="Male" <?php echo ($reg['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($reg['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($reg['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
                        <input type="text" name="nationality" value="<?php echo htmlspecialchars($reg['nationality'] ?? 'Indian'); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    
                    <!-- ID Type and Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                        <select name="id_type" class="w-full px-3 py-2 border rounded-lg">
                            <?php foreach ($id_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo (isset($reg['id_type']) && $reg['id_type'] == $type) ? 'selected' : ''; ?>><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Number <span class="text-gray-500 text-xs">(Optional)</span></label>
                        <input type="text" name="id_number" value="<?php echo htmlspecialchars($reg['id_number'] ?? $reg['aadhaar_number'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg" placeholder="Enter ID number">
                    </div>
                    
                </div>
            </div>

            <!-- Contact Information -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Contact Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number *</label>
                        <input type="text" name="contact" value="<?php echo htmlspecialchars($reg['contact'] ?? ''); ?>" required maxlength="10" pattern="[0-9]{10}" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($reg['email'] ?? ''); ?>" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($reg['city'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" value="<?php echo htmlspecialchars($reg['state'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <input type="text" name="country" value="<?php echo htmlspecialchars($reg['country'] ?? 'India'); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
            </div>

            <!-- Education & Course -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Education & Course</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Name *</label>
                        <select name="course_name" required class="w-full px-3 py-2 border rounded-lg">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): 
                                $option = $c['title'] . ' - ' . ucfirst($c['type']);
                                $selected = ($reg['course_name'] ?? '') == $option ? 'selected' : '';
                            ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Additional Course</label>
                        <select name="add_course" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">None</option>
                            <?php foreach ($courses as $c): 
                                $option = $c['title'] . ' - ' . ucfirst($c['type']);
                                $selected = ($reg['add_course'] ?? '') == $option ? 'selected' : '';
                            ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Start Date</label>
                        <input type="date" name="course_start_date" value="<?php echo htmlspecialchars($reg['course_start_date'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Admission Date</label>
                        <input type="date" name="admission_date" value="<?php echo htmlspecialchars(!empty($reg['admission_date']) && $reg['admission_date'] != '0000-00-00' ? $reg['admission_date'] : date('Y-m-d', strtotime($reg['created_at'] ?? 'now'))); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Education Level</label>
                        <input type="text" name="education_level" value="<?php echo htmlspecialchars($reg['education_level'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Organisation</label>
                        <input type="text" name="organisation" value="<?php echo htmlspecialchars($reg['organisation'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
            </div>

            <!-- Status, Payment & Admin Note -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Status, Payment & Notes</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Admission Status</label>
                        <select name="status" class="w-full px-3 py-2 border rounded-lg">
                            <option value="pending" <?php echo ($reg['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo ($reg['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo ($reg['status'] ?? '') == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                        <select name="payment_status" class="w-full px-3 py-2 border rounded-lg">
                            <?php foreach (['pending' => 'Pending', 'partial' => 'Partially Paid', 'paid' => 'Fully Paid', 'refunded' => 'Refunded'] as $val => $lab): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($reg['payment_status'] ?? '') == $val ? 'selected' : ''; ?>><?php echo $lab; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount (₹)</label>
                        <input type="number" step="0.01" name="total_amount" value="<?php echo (float)($reg['total_amount'] ?? 0); ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>

                    <!-- Read-only Financial Breakdown (auto) -->
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <p class="text-sm font-medium text-gray-600 mb-2">Payment Breakdown (Auto)</p>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Fee Paid (approved payments)</dt>
                                <dd class="font-semibold text-green-700">₹<?php echo number_format($feePaid, 2); ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Discount<?php echo !empty($reg['coupon_code']) ? ' (' . htmlspecialchars($reg['coupon_code']) . ')' : ''; ?></dt>
                                <dd class="text-red-600">-₹<?php echo number_format((float)($reg['discount_amount'] ?? 0), 2); ?></dd>
                            </div>
                            <?php if ((float)($reg['platform_fee'] ?? 0) + (float)($reg['gst_amount'] ?? 0) > 0): ?>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Platform Fee + GST</dt>
                                <dd>₹<?php echo number_format((float)$reg['platform_fee'] + (float)$reg['gst_amount'], 2); ?></dd>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between font-semibold border-t border-gray-300 pt-1 mt-1">
                                <dt class="text-gray-700">Final Amount (after discount)</dt>
                                <dd class="text-indigo-700">₹<?php echo number_format($finalDisplay, 2); ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Payment Method</dt>
                                <dd><?php echo htmlspecialchars($reg['payment_method'] ?? '—'); ?></dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Admin Note -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Admin Note / Additional Details</label>
                        <textarea name="admin_note" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="Any special instructions, fee adjustments, or remarks..."><?php echo htmlspecialchars($reg['admin_note'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-save mr-2"></i> Update Registration
            </button>
            <a href="registrations.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>