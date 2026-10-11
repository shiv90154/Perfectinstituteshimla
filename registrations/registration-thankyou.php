<?php
session_start();
require_once __DIR__ . '/../config/db_connect.php';
$db = (new Database())->getConnection();

$reg_id = $_SESSION['registration_id'] ?? '';
if (!$reg_id) {
    header("Location: index.php");
    exit;
}

$stmt = $db->prepare("SELECT registration_id, full_name, course_name, email, final_amount FROM registrations WHERE registration_id = ?");
$stmt->execute([$reg_id]);
$reg = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$reg) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Registration Submitted – Perfect Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background:#f5f7fa; padding:2rem; display:flex; justify-content:center; align-items:center; min-height:100vh; }
        .card { max-width:600px; width:100%; background:#fff; border-radius:2rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.2); padding:2rem; text-align:center; }
        .icon { font-size:4rem; color:#10b981; margin-bottom:1rem; }
        h1 { margin-bottom:0.5rem; }
        .details { text-align:left; margin:1.5rem 0; padding:1rem; background:#f8fafc; border-radius:1rem; }
        .details div { display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid #e2e8f0; }
        .details div:last-child { border-bottom:none; }
        .btn { display:inline-block; margin-top:1rem; padding:0.8rem 2rem; background:#0a192f; color:#fff; border-radius:2rem; text-decoration:none; font-weight:600; }
        .btn-success { background:#10b981; }
        .btn-success:hover { background:#059669; }
        .btn:hover { transform:translateY(-2px); }
    </style>
</head>
<body>
<div class="card">
    <div class="icon"><i class="fas fa-check-circle"></i></div>
    <h1>Registration Successful!</h1>
    <p>Your details have been submitted. Please complete the payment to confirm your admission.</p>

    <div class="details">
        <div><span>Registration ID</span><strong><?= htmlspecialchars($reg['registration_id']) ?></strong></div>
        <div><span>Name</span><strong><?= htmlspecialchars($reg['full_name']) ?></strong></div>
        <div><span>Course</span><strong><?= htmlspecialchars($reg['course_name']) ?></strong></div>
        <div><span>Total Payable</span><strong>₹ <?= number_format($reg['final_amount'] ?? 0, 2) ?></strong></div>
        <div><span>Email</span><strong><?= htmlspecialchars($reg['email']) ?></strong></div>
    </div>

    <a href="payment.php?reg_id=<?= urlencode($reg['registration_id']) ?>" class="btn btn-success"><i class="fas fa-credit-card"></i> Proceed to Payment</a>
    <br><br>
    <a href="/" class="btn">Go to Home</a>
</div>
</body>
</html>