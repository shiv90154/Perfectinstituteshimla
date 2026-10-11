<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['payment_screenshot'])) {
        $upload_dir = 'uploads/payments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['payment_screenshot']['name']);
        $target_file = $upload_dir . $file_name;
        
        // Check file type
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
        
        // Check file size (5MB max)
        if ($_FILES['payment_screenshot']['size'] > 5000000) {
            echo json_encode(['success' => false, 'message' => 'File size too large (max 5MB)']);
            exit();
        }
        
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $target_file)) {
                echo json_encode([
                    'success' => true,
                    'file_path' => $target_file,
                    'file_name' => $file_name
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error uploading file']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        }
    }
}
?>