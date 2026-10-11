<?php
// functions.php - Secure permission check with prepared statements
/**
 * Check if an admin has a specific permission.
 *
 * @param mysqli $conn Database connection
 * @param int    $admin_id The admin ID
 * @param string $permission_key The permission key (e.g., 'manage_admins')
 * @return bool
 */
function hasPermission($conn, $admin_id, $permission_key) {
    $stmt = $conn->prepare("
        SELECT 1
        FROM admins a
        INNER JOIN roles r ON a.role_id = r.id
        INNER JOIN role_permissions rp ON r.id = rp.role_id
        INNER JOIN permissions p ON rp.permission_id = p.id
        WHERE a.id = ? AND p.permission_key = ?
        LIMIT 1
    ");
    if (!$stmt) {
        error_log("Permission check prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("is", $admin_id, $permission_key);
    $stmt->execute();
    $result = $stmt->get_result();
    $has = $result->num_rows > 0;
    $stmt->close();
    return $has;
}

/**
 * Recalculate registrations.payment_status from approved payments.
 * pending (nothing paid) / partial (some paid) / paid (>= course fee after discount).
 * 'refunded' is set manually and is never overwritten here.
 */
function syncRegistrationPaymentStatus($conn, $registration_id) {
    $stmt = $conn->prepare("SELECT payment_status, net_amount, total_amount, discount_amount FROM registrations WHERE registration_id = ? LIMIT 1");
    $stmt->bind_param('s', $registration_id);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$reg || $reg['payment_status'] === 'refunded') {
        return;
    }

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS paid FROM payments WHERE registration_id = ? AND payment_status = 'approved' AND (is_deleted IS NULL OR is_deleted = 0)");
    $stmt->bind_param('s', $registration_id);
    $stmt->execute();
    $paid = (float)$stmt->get_result()->fetch_assoc()['paid'];
    $stmt->close();

    // Course fee after discount; fall back to total - discount for old rows without net_amount
    $fee = (float)$reg['net_amount'];
    if ($fee <= 0) {
        $fee = max(0, (float)$reg['total_amount'] - (float)$reg['discount_amount']);
    }

    if ($paid <= 0) {
        $status = 'pending';
    } elseif ($fee > 0 && $paid + 0.009 >= $fee) {
        $status = 'paid';
    } else {
        $status = 'partial';
    }

    $stmt = $conn->prepare("UPDATE registrations SET payment_status = ? WHERE registration_id = ?");
    $stmt->bind_param('ss', $status, $registration_id);
    $stmt->execute();
    $stmt->close();
}
?>