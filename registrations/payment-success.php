<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
$db = (new Database())->getConnection();

$reg_id = $_GET['reg_id'] ?? $_SESSION['registration_id'] ?? '';
if (!$reg_id) {
    header("Location: index.php");
    exit;
}

$stmt = $db->prepare("SELECT registration_id, full_name, course_name, final_amount, payment_status FROM registrations WHERE registration_id = ?");
$stmt->execute([$reg_id]);
$reg = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$reg) die('Registration not found');

// Fetch latest payment
$stmt = $db->prepare("SELECT payment_method, transaction_id, amount, payment_status, is_offline, created_at FROM payments WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$reg_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

$status = $payment['payment_status'] ?? 'pending';
$status_label = $status == 'approved' ? 'Approved' : ($status == 'pending' ? 'Pending' : 'Rejected');
$status_class = $status == 'approved' ? 'approved' : ($status == 'pending' ? 'pending' : 'rejected');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Status – Perfect Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background:#f5f7fa; padding:2rem; display:flex; justify-content:center; align-items:center; min-height:100vh; }
        .card { max-width:600px; width:100%; background:#fff; border-radius:2rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.2); padding:2rem; text-align:center; }
        .icon { font-size:4rem; margin-bottom:1rem; }
        .icon.success { color:#10b981; }
        .icon.pending { color:#f59e0b; }
        .icon.rejected { color:#ef4444; }
        h1 { margin-bottom:0.5rem; }
        .details { text-align:left; margin:1.5rem 0; padding:1rem; background:#f8fafc; border-radius:1rem; }
        .details div { display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid #e2e8f0; }
        .details div:last-child { border-bottom:none; }
        .badge { padding:0.2rem 1rem; border-radius:40px; font-weight:600; font-size:0.85rem; }
        .badge.approved { background:#dcfce7; color:#166534; }
        .badge.pending { background:#fef3c7; color:#92400e; }
        .badge.rejected { background:#fee2e2; color:#991b1b; }
        .btn { display:inline-block; margin-top:1rem; padding:0.8rem 2rem; background:#0a192f; color:#fff; border-radius:2rem; text-decoration:none; font-weight:600; }
        .btn:hover { background:#1a365d; }
    </style>
</head>
<body>
<div class="card">
    <?php if ($payment && $status == 'approved'): ?>
        <div class="icon success"><i class="fas fa-check-circle"></i></div>
        <h1>Payment Successful!</h1>
        <p>Thank you, <?= htmlspecialchars($reg['full_name']) ?>. Your admission is confirmed.</p>
    <?php elseif ($payment && $status == 'pending'): ?>
        <div class="icon pending"><i class="fas fa-clock"></i></div>
        <h1>Payment Submitted</h1>
        <p>Your offline payment is under review. We will verify and confirm shortly.</p>
    <?php else: ?>
        <div class="icon pending"><i class="fas fa-hourglass-half"></i></div>
        <h1>Processing</h1>
        <p>Your payment is being processed. You will receive a confirmation email.</p>
    <?php endif; ?>

    <div class="details">
        <div><span>Registration ID</span><strong><?= htmlspecialchars($reg['registration_id']) ?></strong></div>
        <div><span>Course</span><strong><?= htmlspecialchars($reg['course_name']) ?></strong></div>
        <div><span>Amount Paid</span><strong>₹ <?= number_format($payment['amount'] ?? $reg['final_amount'], 2) ?></strong></div>
        <?php if ($payment): ?>
            <div><span>Method</span><strong><?= ucfirst($payment['payment_method']) ?></strong></div>
            <div><span>Status</span><span class="badge <?= $status_class ?>"><?= $status_label ?></span></div>
            <div><span>Transaction ID</span><strong><?= $payment['transaction_id'] ?? '—' ?></strong></div>
        <?php endif; ?>
    </div>

    <a href="/" class="btn"><i class="fas fa-home"></i> Go to Home</a>
</div>
</body>
</html>