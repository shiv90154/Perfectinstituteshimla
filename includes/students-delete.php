<?php
require_once '../includes/config.php';

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$id = intval($_GET['id']);

$conn = getDBConnection();

$query = "DELETE FROM students WHERE id = $id LIMIT 1";

if ($conn->query($query)) {
    header("Location: students.php?msg=deleted");
    exit();
} else {
    echo "Error deleting student: " . $conn->error;
}
?>
