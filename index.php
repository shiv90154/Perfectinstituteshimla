<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get requested page, sanitize strictly
$page = $_GET['page'] ?? 'home';
$page = preg_replace('/[^a-zA-Z0-9\-_]/', '', $page);

// Define paths
$phpPath  = __DIR__ . "/pages/{$page}.php";
$htmlPath = __DIR__ . "/{$page}.html";

$isPhpPage  = file_exists($phpPath);
$isHtmlPage = file_exists($htmlPath);

// Debug mode (add ?debug=1 to URL)
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

if ($debug) {
    echo '<div style="background:#f0f0f0; border:1px solid #ccc; padding:10px; margin:10px; font-family:monospace;">';
    echo '<strong>Debug Info:</strong><br>';
    echo 'Page requested: ' . htmlspecialchars($page) . '<br>';
    echo 'PHP path: ' . htmlspecialchars($phpPath) . '<br>';
    echo 'Exists? ' . ($isPhpPage ? 'YES' : 'NO') . '<br>';
    echo 'HTML path: ' . htmlspecialchars($htmlPath) . '<br>';
    echo 'Exists? ' . ($isHtmlPage ? 'YES' : 'NO') . '<br>';
    echo 'Current directory: ' . __DIR__ . '<br>';
    echo 'Document root: ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . '<br>';
    echo '</div>';
}

// Include the page (which already contains its own <html> and <head>)
if ($isPhpPage) {
    include $phpPath;
} elseif ($isHtmlPage) {
    include $htmlPath;
} else {
    http_response_code(404);
    include __DIR__ . '/404.php';
}
?>