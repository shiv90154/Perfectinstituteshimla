<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/payment_config.php';
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$payment_id = $input['razorpay_payment_id'] ?? '';
$order_id = $input['razorpay_order_id'] ?? '';
$signature = $input['razorpay_signature'] ?? '';
$reg_id = $input['reg_id'] ?? '';

if (!$reg_id || !$payment_id || !$order_id || !$signature) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Verify signature
$generated = hash_hmac('sha256', $order_id . '|' . $payment_id, RAZORPAY_KEY_SECRET);
if ($generated !== $signature) {
    echo json_encode(['success' => false, 'message' => 'Invalid signature']);
    exit;
}

$db = (new Database())->getConnection();
$db->beginTransaction();
try {
    // Fetch net_amount and compute total
    $stmt = $db->prepare("SELECT net_amount FROM registrations WHERE registration_id = ?");
    $stmt->execute([$reg_id]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);
    $net = (float)$reg['net_amount'];
    $platform_fee = $net * (PLATFORM_FEE_PERCENT / 100);
    $gst = $platform_fee * (GST_ON_FEE_PERCENT / 100);
    $total_paid = $net + $platform_fee + $gst;

    // Update registration
    $updateReg = $db->prepare("UPDATE registrations SET payment_status = 'paid', status = 'approved', payment_method = 'online', platform_fee = ?, gst_amount = ?, final_amount = ? WHERE registration_id = ?");
    $updateReg->execute([$platform_fee, $gst, $total_paid, $reg_id]);

    // Insert payment with correct amount
    $insertPay = $db->prepare("
        INSERT INTO payments (registration_id, payment_method, transaction_id, amount, platform_fee, gst_amount, payment_status, is_offline, created_at)
        VALUES (?, 'Razorpay', ?, ?, ?, ?, 'approved', 0, NOW())
    ");
    $insertPay->execute([$reg_id, $payment_id, $total_paid, $platform_fee, $gst]);

    // Coupon usage is counted when the registration is created (registrations/index.php)

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}