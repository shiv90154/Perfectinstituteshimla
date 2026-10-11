<?php
// Enable error reporting (remove after testing)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// ---- Manual SDK Include ----
require_once __DIR__ . '/../razorpay-php/Razorpay.php';
use Razorpay\Api\Api;

// ---- Configuration ----
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/payment_config.php';

$db = (new Database())->getConnection();

$reg_id = $_GET['reg_id'] ?? $_SESSION['registration_id'] ?? '';
if (!$reg_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No registration ID']);
    exit;
}

// Fetch net_amount and compute online total
$stmt = $db->prepare("SELECT net_amount FROM registrations WHERE registration_id = ?");
$stmt->execute([$reg_id]);
$reg = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$reg) {
    http_response_code(404);
    echo json_encode(['error' => 'Registration not found']);
    exit;
}

$net = (float)$reg['net_amount'];
$platform_fee = $net * (PLATFORM_FEE_PERCENT / 100);
$gst = $platform_fee * (GST_ON_FEE_PERCENT / 100);
$total = $net + $platform_fee + $gst;
$amount = $total * 100; // paise

try {
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    $orderData = [
        'receipt' => $reg_id,
        'amount' => $amount,
        'currency' => 'INR',
        'payment_capture' => 1
    ];
    $razorpayOrder = $api->order->create($orderData);
    $orderId = $razorpayOrder['id'];

    // Store order ID in DB (optional, if column exists)
    try {
        $update = $db->prepare("UPDATE registrations SET razorpay_order_id = ? WHERE registration_id = ?");
        $update->execute([$orderId, $reg_id]);
    } catch (PDOException $e) {
        // column may not exist – ignore
    }

    header('Content-Type: application/json');
    echo json_encode([
        'order_id' => $orderId,
        'amount' => $amount,
        'currency' => 'INR',
        'key_id' => RAZORPAY_KEY_ID
    ]);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Order creation failed: ' . $e->getMessage()]);
}