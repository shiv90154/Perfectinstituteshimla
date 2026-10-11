<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/payment_config.php';
$db = (new Database())->getConnection();

// Get reg_id from GET or session
$reg_id = $_GET['reg_id'] ?? $_SESSION['registration_id'] ?? '';
if (!$reg_id) {
    header("Location: index.php");
    exit;
}

// Fetch registration details
$stmt = $db->prepare("
    SELECT registration_id, full_name, email, contact, course_name, 
           total_amount, coupon_code, discount_amount, net_amount,
           platform_fee, gst_amount, final_amount, payment_status, payment_method
    FROM registrations WHERE registration_id = ?
");
$stmt->execute([$reg_id]);
$reg = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$reg) {
    die('Registration not found');
}

// If already paid, redirect
if ($reg['payment_status'] === 'paid') {
    header("Location: payment-success.php?reg_id=" . urlencode($reg_id));
    exit;
}

// For offline payment submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offline_payment'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please refresh the page.";
    }

    $payment_method = $_POST['payment_method'] ?? '';
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $amount_paid = (float)($_POST['amount'] ?? 0);
    if (!$payment_method) $errors[] = "Please select a payment method";
    if ($amount_paid <= 0) $errors[] = "Amount must be greater than zero";

    $uploaded_files = [];
    if (!empty($_FILES['screenshots']['name'][0])) {
        $allowed = ['jpg','jpeg','png','pdf'];
        $max_size = 5*1024*1024;
        foreach ($_FILES['screenshots']['name'] as $i => $name) {
            if ($_FILES['screenshots']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed) || $_FILES['screenshots']['size'][$i] > $max_size) {
                $errors[] = "Invalid file: $name";
                continue;
            }
            $dir = __DIR__ . '/../uploads/payments/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $unique = $reg_id . '_' . time() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($_FILES['screenshots']['tmp_name'][$i], $dir . $unique)) {
                $uploaded_files[] = $unique;
            }
        }
    }

    if (empty($errors)) {
        $screenshots_json = json_encode($uploaded_files);
        $stmt = $db->prepare("
            INSERT INTO payments (
                registration_id, payment_method, transaction_id, screenshot,
                amount, payment_status, is_offline, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', 1, NOW())
        ");
        $stmt->execute([$reg_id, $payment_method, $transaction_id, $screenshots_json, $amount_paid]);

        $db->prepare("UPDATE registrations SET payment_method = ? WHERE registration_id = ?")->execute([$payment_method, $reg_id]);

        $_SESSION['payment_submitted'] = true;
        header("Location: payment-success.php?reg_id=" . urlencode($reg_id));
        exit;
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Compute online total (net + 2% + GST) for display
$net = (float)$reg['net_amount'];
$platform_fee = $net * (PLATFORM_FEE_PERCENT / 100);
$gst = $platform_fee * (GST_ON_FEE_PERCENT / 100);
$online_total = $net + $platform_fee + $gst;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment – Perfect Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background:#f5f7fa; padding:2rem; }
        .container { max-width:1000px; margin:0 auto; background:#fff; border-radius:2rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.2); overflow:hidden; }
        .header { background:linear-gradient(135deg,#0a192f,#1e3a5f); padding:2rem; text-align:center; color:#fff; }
        .header h1 { font-size:2rem; }
        .body { padding:2rem; }
        .amount-box { background:#f1f5f9; padding:1.5rem; border-radius:1.2rem; margin-bottom:2rem; display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .amount-item { display:flex; justify-content:space-between; padding:0.5rem 0; border-bottom:1px solid #e2e8f0; }
        .amount-item.total { font-weight:700; font-size:1.2rem; border-bottom:none; }
        .tabs { display:flex; gap:1rem; margin-bottom:2rem; border-bottom:2px solid #e2e8f0; }
        .tab { padding:0.8rem 1.5rem; cursor:pointer; font-weight:600; color:#64748b; border-bottom:3px solid transparent; transition:0.2s; }
        .tab.active { color:#0a192f; border-bottom-color:#0a192f; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        .form-group { margin-bottom:1.2rem; }
        label { display:block; font-weight:600; font-size:0.9rem; margin-bottom:0.3rem; }
        input, select { width:100%; padding:0.75rem 1rem; border:1.5px solid #e2e8f0; border-radius:1rem; font-size:0.95rem; background:white; }
        input:focus, select:focus { outline:none; border-color:#0a192f; }
        .btn { padding:0.8rem 2rem; border:none; border-radius:2rem; font-weight:600; cursor:pointer; transition:0.2s; }
        .btn-primary { background:#0a192f; color:#fff; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 10px 20px rgba(0,0,0,0.1); }
        .btn-success { background:#10b981; color:#fff; }
        .btn-success:hover { transform:translateY(-2px); }
        .btn-block { width:100%; }
        .error-box { background:#fff5f5; border-left:5px solid #e53e3e; padding:1rem; border-radius:1rem; margin-bottom:1.5rem; color:#c53030; }
        .error-box ul { padding-left:1.2rem; }
        .note { font-size:0.85rem; color:#475569; margin-top:0.5rem; }
        .qr-box { text-align:center; margin:1rem 0; }
        .qr-box img { max-width:180px; border-radius:1rem; }
        .payment-details { background:#f1f5f9; padding:1.5rem; border-radius:1.2rem; margin-bottom:1.5rem; }
        .payment-details h3 { color:#0a192f; margin-bottom:0.5rem; }
        .payment-details p { margin-bottom:0.25rem; font-size:0.95rem; }
        .cash-message { 
            background:#eef2ff; 
            padding:1.5rem; 
            border-radius:1.2rem; 
            text-align:center;
            border:2px dashed #0a192f;
            margin-bottom:1.5rem;
        }
        .cash-message i { font-size:2rem; color:#0a192f; display:block; margin-bottom:0.5rem; }
        .cash-message h3 { color:#0a192f; margin-bottom:0.5rem; }
        .cash-message p { color:#475569; }
        .hidden { display: none !important; }
        @media (max-width:640px) { 
            .amount-box { grid-template-columns:1fr; } 
            .tabs { flex-direction:column; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-credit-card"></i> Complete Payment</h1>
        <p>Registration ID: <?= htmlspecialchars($reg_id) ?></p>
    </div>
    <div class="body">
        <?php if (!empty($errors)): ?>
            <div class="error-box"><ul><?php foreach($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul></div>
        <?php endif; ?>

        <!-- Amount Breakdown (only net amount shown, fees added dynamically) -->
        <div class="amount-box" id="amountBox">
            <div class="amount-item"><span>Course Fee</span><span>₹ <?= number_format($reg['total_amount'],2) ?></span></div>
            <?php if ($reg['discount_amount'] > 0): ?>
                <div class="amount-item"><span>Discount (<?= htmlspecialchars($reg['coupon_code']) ?>)</span><span style="color:#16a34a;">- ₹ <?= number_format($reg['discount_amount'],2) ?></span></div>
            <?php endif; ?>
            <div class="amount-item"><span>Net Amount</span><span>₹ <?= number_format($reg['net_amount'],2) ?></span></div>
            <!-- These two rows will be shown/hidden via JavaScript for online only -->
            <div class="amount-item online-fee" style="display:none;"><span>Platform Fee (2%)</span><span>₹ <?= number_format($platform_fee,2) ?></span></div>
            <div class="amount-item online-fee" style="display:none;"><span>GST on Fee (18%)</span><span>₹ <?= number_format($gst,2) ?></span></div>
            <div class="amount-item total" id="totalDisplay"><span>Total Payable</span><span id="totalAmountSpan">₹ <?= number_format($reg['net_amount'],2) ?></span></div>
        </div>

        <!-- Payment Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="online">Pay Here Online (Razorpay)</div>
            <div class="tab" data-tab="offline">Pay at Institute</div>
        </div>

        <!-- Online Tab -->
        <div id="online" class="tab-content active">
            <p style="margin-bottom:1.5rem;">Pay securely via UPI, Cards, Net Banking. Click the button below.</p>
            <button id="payOnlineBtn" class="btn btn-primary btn-block"><i class="fas fa-credit-card"></i> Pay ₹ <?= number_format($online_total,2) ?> Now</button>
            <p class="note"><i class="fas fa-lock"></i> Secure transaction powered by Razorpay</p>
        </div>

        <!-- Offline Tab -->
        <div id="offline" class="tab-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="offline_payment" value="1">

                <div class="form-group">
                    <label>Payment Method <span class="required">*</span></label>
                    <select name="payment_method" id="paymentMethod" required>
                        <option value="">Select</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cash">Cash (at institute)</option>
                    </select>
                </div>

                <div id="bankDetails" class="payment-details hidden">
                    <div class="qr-box">
                        <img src="/images/upi-qr.jpeg" alt="UPI QR">
                        <p><strong>UPI ID:</strong> 9805962233@okbizaxis</p>
                    </div>
                    <h3><i class="fas fa-university"></i> Bank Transfer</h3>
                    <p><strong>A/c Name:</strong> Perfect Institute of Languages</p>
                    <p><strong>Current A/c No.:</strong> 50200098682052</p>
                    <p><strong>IFSC Code:</strong> HDFC0004116</p>
                </div>

                <div id="cashMessage" class="cash-message hidden">
                    <i class="fas fa-hand-holding-usd"></i>
                    <h3>Pay in Cash at Our Institute</h3>
                    <p>Visit our institute to make the payment in cash.</p>
                    <p style="margin-top:0.5rem; font-size:0.9rem;">
                        <i class="fas fa-map-marker-alt"></i> 
                        <strong>Address:</strong> Perfect Institute, Shimla, Himachal Pradesh
                    </p>
                    <p style="font-size:0.8rem; color:#64748b; margin-top:0.5rem;">
                        <i class="fas fa-clock"></i> Office Hours: 9:00 AM – 7:00 PM
                    </p>
                </div>

                <div class="form-group">
                    <label>Amount Paying (₹)</label>
                    <input type="number" name="amount" step="0.01" value="<?= htmlspecialchars($reg['net_amount']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Transaction ID / Reference</label>
                    <input type="text" name="transaction_id" placeholder="Optional (for UPI/Bank Transfer)">
                </div>
                <div class="form-group">
                    <label>Payment Screenshot (optional, multiple)</label>
                    <input type="file" name="screenshots[]" multiple accept=".jpg,.jpeg,.png,.pdf">
                    <p class="note">Upload screenshot of UPI/Bank transfer. Not required for Cash.</p>
                </div>
                <button type="submit" class="btn btn-success btn-block"><i class="fas fa-check-circle"></i> Submit Offline Payment</button>
            </form>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    // ==================== TAB SWITCHING ====================
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(target).classList.add('active');

            // Update total amount display
            updateTotalDisplay(target);
        });
    });

    // ==================== UPDATE TOTAL DISPLAY ====================
    const netAmount = <?= $reg['net_amount'] ?>;
    const platformFee = netAmount * <?= PLATFORM_FEE_PERCENT / 100 ?>;
    const gst = platformFee * <?= GST_ON_FEE_PERCENT / 100 ?>;
    const onlineTotal = netAmount + platformFee + gst;

    function updateTotalDisplay(tab) {
        const totalSpan = document.getElementById('totalAmountSpan');
        const feeRows = document.querySelectorAll('.online-fee');
        if (tab === 'online') {
            feeRows.forEach(row => row.style.display = 'flex');
            totalSpan.textContent = '₹ ' + onlineTotal.toFixed(2);
            // Update button text as well
            document.getElementById('payOnlineBtn').innerHTML = '<i class="fas fa-credit-card"></i> Pay ₹ ' + onlineTotal.toFixed(2) + ' Now';
        } else {
            feeRows.forEach(row => row.style.display = 'none');
            totalSpan.textContent = '₹ ' + netAmount.toFixed(2);
        }
    }

    // ==================== TOGGLE BANK/CASH DETAILS ====================
    const paymentMethod = document.getElementById('paymentMethod');
    const bankDetails = document.getElementById('bankDetails');
    const cashMessage = document.getElementById('cashMessage');

    function togglePaymentDetails() {
        const selected = paymentMethod.value;
        if (selected === 'Cash') {
            bankDetails.style.display = 'none';
            cashMessage.style.display = 'block';
        } else if (selected === 'UPI' || selected === 'Bank Transfer') {
            bankDetails.style.display = 'block';
            cashMessage.style.display = 'none';
        } else {
            bankDetails.style.display = 'none';
            cashMessage.style.display = 'none';
        }
    }

    paymentMethod.addEventListener('change', togglePaymentDetails);
    togglePaymentDetails();

    // ==================== ONLINE PAYMENT (RAZORPAY) ====================
    document.getElementById('payOnlineBtn').addEventListener('click', function() {
        const regId = '<?= htmlspecialchars($reg_id) ?>';
        fetch('create-order.php?reg_id=' + encodeURIComponent(regId))
            .then(res => res.json())
            .then(data => {
                if (!data.order_id) {
                    alert('Error creating order: ' + (data.message || 'Unknown error'));
                    return;
                }
                var options = {
                    "key": data.key_id,
                    "amount": data.amount,
                    "currency": data.currency,
                    "name": "Perfect Institute",
                    "description": "Registration Fee",
                    "order_id": data.order_id,
                    "handler": function(response) {
                        fetch('verify-payment.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_signature: response.razorpay_signature,
                                reg_id: regId
                            })
                        })
                        .then(res => res.json())
                        .then(result => {
                            if (result.success) {
                                alert('Payment successful!');
                                window.location.href = 'payment-success.php?reg_id=' + encodeURIComponent(regId);
                            } else {
                                alert('Payment verification failed. Please contact support.');
                            }
                        })
                        .catch(() => alert('Verification error'));
                    },
                    "prefill": {
                        "name": "<?= htmlspecialchars($reg['full_name']) ?>",
                        "email": "<?= htmlspecialchars($reg['email']) ?>",
                        "contact": "<?= htmlspecialchars($reg['contact']) ?>"
                    },
                    "theme": {"color": "#0a192f"}
                };
                var rzp = new Razorpay(options);
                rzp.open();
            })
            .catch(err => {
                alert('Error: ' + err.message);
            });
    });

    // Set initial state: online tab active → show fees
    updateTotalDisplay('online');
</script>
</body>
</html>