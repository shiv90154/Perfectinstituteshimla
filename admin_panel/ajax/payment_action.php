<?php
// ajax/payment_action.php
require_once '../includes/config.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$id || !in_array($action, ['approve', 'reject', 'delete'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if ($action == 'approve') {
    $conn->query("UPDATE payments SET payment_status='approved' WHERE id=$id");
    $message = "Payment approved successfully.";
} elseif ($action == 'reject') {
    $conn->query("UPDATE payments SET payment_status='rejected' WHERE id=$id");
    $message = "Payment rejected.";
} elseif ($action == 'delete') {
    $conn->query("DELETE FROM payments WHERE id=$id");
    $message = "Payment deleted.";
}

if ($conn->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => $message, 'action' => $action, 'id' => $id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Operation failed or no changes.']);
}
?>