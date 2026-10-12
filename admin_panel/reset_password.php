<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/password_reset.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ensureResetSchema($conn);

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$reset = findResetByToken($conn, (string)$token);
$error = '';
$done = false;

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token. Please refresh the page and try again.');
    }
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE admins SET password = ?, failed_login_attempts = 0, account_locked_until = NULL, last_password_change = NOW(), password_expiry_date = NULL WHERE id = ?");
        $upd->bind_param('si', $hashed, $reset['id']);
        if ($upd->execute()) {
            $use = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            $use->bind_param('i', $reset['reset_id']);
            $use->execute();
            $done = true;
        } else {
            $error = 'Could not update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password · Perfect Institute</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>body{background:linear-gradient(145deg,#f6f8fc 0%,#e9eef5 100%);font-family:'Inter',system-ui,sans-serif}</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md bg-white rounded-2xl p-8 shadow-xl">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Reset Password</h1>

        <?php if ($done): ?>
            <div class="p-4 rounded-lg mb-6 text-sm bg-green-50 border-l-4 border-green-600 text-green-800">Your password has been updated.</div>
            <a href="login.php" class="block text-center text-white py-3 rounded-xl font-semibold bg-blue-800 hover:bg-blue-700">Go to Login</a>
        <?php elseif (!$reset): ?>
            <div class="p-4 rounded-lg mb-6 text-sm bg-red-50 border-l-4 border-red-700 text-red-900">This reset link is invalid or has expired.</div>
            <a href="forgot_password.php" class="block text-center text-blue-700 hover:underline">Request a new link</a>
        <?php else: ?>
            <p class="text-sm text-gray-500 mb-5">Set a new password for <strong><?php echo htmlspecialchars($reset['username']); ?></strong>.</p>
            <?php if ($error): ?>
                <div class="p-4 rounded-lg mb-5 text-sm bg-red-50 border-l-4 border-red-700 text-red-900"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="password" name="password" required minlength="8" placeholder="New password"
                       class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:border-blue-600">
                <input type="password" name="confirm" required minlength="8" placeholder="Confirm new password"
                       class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:border-blue-600">
                <button type="submit" class="w-full text-white py-3 rounded-xl font-semibold bg-blue-800 hover:bg-blue-700">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
