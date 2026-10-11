<?php
// registration_view.php - Secure view page with all new columns (fixed null-safe)
$pageTitle = 'View Registration';
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch registration with prepared statement
$stmt = $conn->prepare("SELECT * FROM registrations WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();

if (!$reg) {
    $_SESSION['error'] = "Registration not found.";
    header("Location: registrations.php");
    exit;
}

// Fee paid so far (approved payments only)
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS paid FROM payments WHERE registration_id = ? AND payment_status = 'approved' AND (is_deleted IS NULL OR is_deleted = 0)");
$stmt->bind_param('s', $reg['registration_id']);
$stmt->execute();
$feePaid = (float)$stmt->get_result()->fetch_assoc()['paid'];

$paymentStatusLabels = ['pending' => 'Pending', 'partial' => 'Partially Paid', 'paid' => 'Fully Paid', 'refunded' => 'Refunded'];
$paymentStatusClasses = ['pending' => 'bg-gray-100 text-gray-800', 'partial' => 'bg-yellow-100 text-yellow-800', 'paid' => 'bg-green-100 text-green-800', 'refunded' => 'bg-red-100 text-red-800'];
$curPayStatus = $reg['payment_status'] ?? 'pending';

// Fetch other registrations with the same email (excluding current)
$stmt = $conn->prepare("
    SELECT id, registration_id, course_name, created_at, status
    FROM registrations
    WHERE email = ? AND id != ? AND (is_deleted IS NULL OR is_deleted = 0)
    ORDER BY created_at DESC
");
$stmt->bind_param('si', $reg['email'], $id);
$stmt->execute();
$otherRegs = $stmt->get_result();
$totalRegs = $otherRegs->num_rows + 1; // including current

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
@media print {
    #sidebar, .no-print, .btn-group, .no-print * {
        display: none !important;
    }
    main, .container, .bg-white {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        box-shadow: none !important;
    }
    body {
        background: white;
        margin: 0;
        padding: 0;
    }
    .print-header {
        text-align: center;
        margin-bottom: 20px;
        font-size: 24px;
        font-weight: bold;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
    }
    .print-header h1 {
        font-size: 28px;
        margin: 0;
    }
    .print-header p {
        font-size: 12px;
        margin: 5px 0 0;
        color: #666;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    .border {
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }
    a[href]:after {
        content: none !important;
    }
}
.btn-group {
    display: flex;
    gap: 10px;
    align-items: center;
}
.print-header {
    display: none;
}
@media print {
    .print-header {
        display: block;
    }
}
</style>

<div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Print header -->
    <div class="print-header">
        <h1>Perfect Institute of English & Personality Development</h1>
        <p>Shimla, Himachal Pradesh | Phone: +91 90154-84696 | Email: info@perfectinstitute.com</p>
        <h3>Registration Details</h3>
    </div>

    <div class="flex items-center justify-between mb-6 no-print">
        <h2 class="text-2xl font-bold text-gray-800">Registration Details</h2>
        <div class="btn-group">
            <button onclick="window.print();" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-download mr-2"></i> Export (PDF)
            </button>
            <a href="registration_edit.php?id=<?php echo (int)$reg['id']; ?>" class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
            <a href="registrations.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>
    </div>

    <!-- Rejoined badge -->
    <?php if ($totalRegs > 1): ?>
        <div class="mb-4 p-3 bg-purple-50 border-l-4 border-purple-500 rounded no-print">
            <p class="text-purple-800">
                <i class="fas fa-users mr-2"></i> 
                This student has registered <strong><?php echo (int)$totalRegs; ?></strong> time(s). 
                <?php if ($otherRegs->num_rows > 0): ?>
                    Below are their other registrations.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Personal Information -->
        <div class="border rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Personal Information</h3>
            <dl class="space-y-2">
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Registration ID:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['registration_id'] ?? ''); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Full Name:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['full_name'] ?? ''); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Date of Birth:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo !empty($reg['dob']) && $reg['dob'] != '0000-00-00' ? date('d M Y', strtotime($reg['dob'])) : '—'; ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Gender:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars(ucfirst($reg['gender'] ?? '—')); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Nationality:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['nationality'] ?? 'Indian'); ?></dd>
                </div>

                <!-- ID Type & Number -->
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">ID Type:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['id_type'] ?? 'Aadhaar'); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">ID Number:</dt>
                    <dd class="w-2/3 text-sm text-gray-900">
                        <?php 
                        $idNum = $reg['id_number'] ?? $reg['aadhaar_number'] ?? '';
                        echo $idNum ? htmlspecialchars($idNum) : '—';
                        ?>
                    </dd>
                </div>

            </dl>
        </div>

        <!-- Contact Information -->
        <div class="border rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Contact Information</h3>
            <dl class="space-y-2">
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Contact:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['contact'] ?? ''); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Email:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['email'] ?? ''); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">City:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['city'] ?? '—'); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">State:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['state'] ?? '—'); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Country:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['country'] ?? 'India'); ?></dd>
                </div>
            </dl>
        </div>

        <!-- Education & Course -->
        <div class="border rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Education & Course</h3>
            <dl class="space-y-2">
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Course Name:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['course_name'] ?? ''); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Additional Course:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['add_course'] ?? '—'); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Course Start Date:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo !empty($reg['course_start_date']) && $reg['course_start_date'] != '0000-00-00' ? date('d M Y', strtotime($reg['course_start_date'])) : '—'; ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Admission Date:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php $adm = (!empty($reg['admission_date']) && $reg['admission_date'] != '0000-00-00') ? $reg['admission_date'] : ($reg['created_at'] ?? ''); echo $adm ? date('d M Y', strtotime($adm)) : '—'; ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Education Level:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['education_level'] ?? '—'); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Organisation:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['organisation'] ?? '—'); ?></dd>
                </div>
            </dl>
        </div>

        <!-- Payment & Status -->
        <div class="border rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Payment & Status</h3>
            <dl class="space-y-2">
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Admission Status:</dt>
                    <dd class="w-2/3">
                        <span class="px-2 py-1 text-xs rounded-full 
                            <?php 
                                if ($reg['status'] == 'approved') echo 'bg-green-100 text-green-800';
                                elseif ($reg['status'] == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                elseif ($reg['status'] == 'rejected') echo 'bg-red-100 text-red-800';
                                else echo 'bg-gray-100 text-gray-800';
                            ?>">
                            <?php echo htmlspecialchars($reg['status'] ?? 'pending'); ?>
                        </span>
                    </dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Payment Status:</dt>
                    <dd class="w-2/3">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $paymentStatusClasses[$curPayStatus] ?? 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo htmlspecialchars($paymentStatusLabels[$curPayStatus] ?? $curPayStatus); ?>
                        </span>
                    </dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Payment Method:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['payment_method'] ?? '—'); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Fee Paid:</dt>
                    <dd class="w-2/3 text-sm text-gray-900">₹<?php echo number_format($feePaid, 2); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Total Amount:</dt>
                    <dd class="w-2/3 text-sm text-gray-900">₹<?php echo number_format((float)($reg['total_amount'] ?? 0), 2); ?></dd>
                </div>
                <?php if (!empty($reg['coupon_code'])): ?>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Coupon Code:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo htmlspecialchars($reg['coupon_code'] ?? ''); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Discount:</dt>
                    <dd class="w-2/3 text-sm text-gray-900">-₹<?php echo number_format((float)($reg['discount_amount'] ?? 0), 2); ?></dd>
                </div>
                <?php endif; ?>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Net Amount:</dt>
                    <dd class="w-2/3 text-sm text-gray-900">₹<?php echo number_format((float)($reg['net_amount'] ?? 0), 2); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Platform Fee (2%):</dt>
                    <dd class="w-2/3 text-sm text-gray-900">₹<?php echo number_format((float)($reg['platform_fee'] ?? 0), 2); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">GST (18%):</dt>
                    <dd class="w-2/3 text-sm text-gray-900">₹<?php echo number_format((float)($reg['gst_amount'] ?? 0), 2); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500 font-semibold">Final Amount:</dt>
                    <dd class="w-2/3 text-sm font-semibold text-gray-900">₹<?php echo number_format((float)($reg['final_amount'] ?? 0), 2); ?></dd>
                </div>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Registration Date:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo !empty($reg['created_at']) ? date('d M Y, h:i A', strtotime($reg['created_at'])) : '—'; ?></dd>
                </div>
                <?php if (!empty($reg['admin_note'])): ?>
                <div class="flex">
                    <dt class="w-1/3 text-sm font-medium text-gray-500">Admin Note:</dt>
                    <dd class="w-2/3 text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($reg['admin_note'] ?? '')); ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <!-- Other Registrations by this Student -->
    <?php if ($otherRegs->num_rows > 0): ?>
        <div class="mt-8 no-print">
            <h3 class="text-lg font-semibold mb-3 text-gray-700 border-b pb-2">Other Registrations by this Student</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reg ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($other = $otherRegs->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-3 text-sm font-mono"><?php echo htmlspecialchars($other['registration_id'] ?? ''); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($other['course_name'] ?? ''); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo !empty($other['created_at']) ? date('d M Y', strtotime($other['created_at'])) : '—'; ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php 
                                        if (($other['status'] ?? '') == 'approved') echo 'bg-green-100 text-green-800';
                                        elseif (($other['status'] ?? '') == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                        elseif (($other['status'] ?? '') == 'rejected') echo 'bg-red-100 text-red-800';
                                        else echo 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?php echo htmlspecialchars($other['status'] ?? 'pending'); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <a href="registration_view.php?id=<?php echo (int)($other['id'] ?? 0); ?>" class="text-indigo-600 hover:text-indigo-800">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>