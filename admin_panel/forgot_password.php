<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/password_reset.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ensureResetSchema($conn);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token. Please refresh the page and try again.');
    }
    $identity = trim($_POST['identity'] ?? '');
    if ($identity !== '') {
        $stmt = $conn->prepare("SELECT id, name, email FROM admins WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1");
        $stmt->bind_param('ss', $identity, $identity);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        if ($admin && filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
            $link = createResetLink($conn, (int)$admin['id']);
            sendResetEmail($admin['email'], $admin['name'], $link);
        }
    }
    // Same message in every case to avoid revealing which accounts exist
    $message = 'If an account with a registered email exists, a password reset link has been sent. If you do not receive it, please contact the administrator.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password · Perfect Institute</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>body{background:linear-gradient(145deg,#f6f8fc 0%,#e9eef5 100%);font-family:'Inter',system-ui,sans-serif}</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md bg-white rounded-2xl p-8 shadow-xl">
        <h1 class="text-2xl font-bold text-gray-800 mb-1">Forgot Password</h1>
        <p class="text-sm text-gray-500 mb-6">Enter your username or registered email and we'll send you a reset link.</p>

        <?php if ($message): ?>
            <div class="p-4 rounded-lg mb-6 text-sm bg-green-50 border-l-4 border-green-600 text-green-800"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="text" name="identity" required placeholder="Username or email"
                   class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:border-blue-600">
            <button type="submit" class="w-full text-white py-3 rounded-xl font-semibold bg-blue-800 hover:bg-blue-700">
                <i class="fas fa-paper-plane mr-2"></i>Send Reset Link
            </button>
        </form>
        <p class="text-center mt-6 text-sm"><a href="login.php" class="text-blue-700 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to login</a></p>
    </div>
</body>
</html>
