<?php
require_once __DIR__ . '/../config/db_connect.php';
$db = (new Database())->getConnection();

$code = $_GET['code'] ?? '';
$amount = (float)($_GET['amount'] ?? 0);

if (!$code) {
    echo json_encode(['success' => false, 'message' => 'Coupon code required']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND status='active' AND valid_from <= CURDATE() AND valid_to >= CURDATE()");
$stmt->execute([$code]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon']);
    exit;
}
if ($coupon['used_count'] >= $coupon['max_uses']) {
    echo json_encode(['success' => false, 'message' => 'Coupon usage limit exceeded']);
    exit;
}
if ($amount < $coupon['min_order_amount']) {
    echo json_encode(['success' => false, 'message' => 'Minimum order amount is ₹' . number_format($coupon['min_order_amount'], 2)]);
    exit;
}

// Calculate discount
if ($coupon['discount_type'] == 'percentage') {
    $discount = ($amount * $coupon['discount_value']) / 100;
} else {
    $discount = $coupon['discount_value'];
}
if ($discount > $amount) $discount = $amount;

echo json_encode([
    'success' => true,
    'discount' => $discount,
    'coupon_id' => $coupon['id']
]);
?>