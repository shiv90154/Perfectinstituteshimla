<?php
session_start();
require_once 'includes/config.php';

// Security: Regenerate session ID to prevent fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$username = '';

// Configuration
define('MAX_FAILED_ATTEMPTS', 5);
define('LOCKOUT_DURATION_MINUTES', 30);
define('PASSWORD_EXPIRY_DAYS', 90);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token. Please refresh the page and try again.');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Fetch admin with role and security fields
        $stmt = $conn->prepare("
            SELECT a.id, a.name, a.username, a.password, a.status, a.role_id, r.role_name,
                   a.failed_login_attempts, a.account_locked_until,
                   a.last_password_change, a.password_expiry_date
            FROM admins a
            LEFT JOIN roles r ON a.role_id = r.id
            WHERE a.username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            // Check if account is locked
            if (!empty($admin['account_locked_until']) && strtotime($admin['account_locked_until']) > time()) {
                $lockoutTime = date('H:i', strtotime($admin['account_locked_until']));
                $error = "Account locked. Please try again after $lockoutTime.";
            }
            // Check account status
            elseif ($admin['status'] !== 'active') {
                $error = 'Your account is blocked. Please contact the administrator.';
            }
            // Verify password
            elseif (password_verify($password, $admin['password'])) {
                // Successful login: reset failed attempts
                $resetStmt = $conn->prepare("UPDATE admins SET failed_login_attempts = 0, account_locked_until = NULL WHERE id = ?");
                $resetStmt->bind_param("i", $admin['id']);
                $resetStmt->execute();
                $resetStmt->close();

                // Set session variables
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role_name'] ?? 'Administrator';
                $_SESSION['admin_role_id'] = $admin['role_id'];

                // Update last login timestamp
                $updateStmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $admin['id']);
                $updateStmt->execute();
                $updateStmt->close();

                // Check password expiry
                $passwordExpired = false;
                if (!empty($admin['password_expiry_date'])) {
                    if (strtotime($admin['password_expiry_date']) < time()) {
                        $passwordExpired = true;
                    }
                } elseif (!empty($admin['last_password_change'])) {
                    // Fallback: if no explicit expiry date, calculate based on last change
                    $expiryTimestamp = strtotime($admin['last_password_change'] . ' + ' . PASSWORD_EXPIRY_DAYS . ' days');
                    if ($expiryTimestamp < time()) {
                        $passwordExpired = true;
                    }
                }

                // Regenerate session ID after login
                session_regenerate_id(true);

                // Redirect to change password if expired
                if ($passwordExpired) {
                    $_SESSION['force_password_change'] = true;
                    header("Location: change_password.php");
                    exit;
                }

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                // Failed login: increment attempts and possibly lock account
                $failedAttempts = $admin['failed_login_attempts'] + 1;
                $lockUntil = null;

                if ($failedAttempts >= MAX_FAILED_ATTEMPTS) {
                    $lockUntil = date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_DURATION_MINUTES . ' minutes'));
                    $error = "Too many failed attempts. Account locked for " . LOCKOUT_DURATION_MINUTES . " minutes.";
                } else {
                    $remaining = MAX_FAILED_ATTEMPTS - $failedAttempts;
                    $error = "Invalid username or password. $remaining attempt(s) remaining.";
                }

                $updateFailStmt = $conn->prepare("UPDATE admins SET failed_login_attempts = ?, account_locked_until = ? WHERE id = ?");
                $updateFailStmt->bind_param("isi", $failedAttempts, $lockUntil, $admin['id']);
                $updateFailStmt->execute();
                $updateFailStmt->close();
            }
        } else {
            // Username not found - generic error to prevent user enumeration
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login · Perfect Institute</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(145deg, #f6f8fc 0%, #e9eef5 100%);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        .login-card {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(4px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0,0,0,0.02);
        }
        .input-field {
            transition: all 0.2s ease;
            background-color: #f9fafc;
            border: 1px solid #e2e8f0;
        }
        .input-field:focus {
            background-color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
        }
        .btn-primary {
            background: linear-gradient(145deg, #1e3a8a, #1e40af);
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(30,58,138,0.2), 0 2px 4px -1px rgba(30,58,138,0.1);
        }
        .btn-primary:hover {
            background: linear-gradient(145deg, #1e3a8a, #2563eb);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(30,58,138,0.3);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .brand-icon {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            box-shadow: 0 10px 15px -3px rgba(30,58,138,0.2);
        }
        .error-alert {
            background-color: #fef2f2;
            border-left: 4px solid #b91c1c;
            color: #7f1d1d;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="brand-icon inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4">
                <i class="fas fa-graduation-cap text-2xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Perfect Institute</h1>
            <p class="text-gray-500 mt-1 text-sm font-medium">Admin Portal · Secure Access</p>
        </div>

        <!-- Login Card -->
        <div class="login-card rounded-2xl p-8">
            <?php if ($error): ?>
                <div class="error-alert flex items-start p-4 rounded-lg mb-6 text-sm" role="alert">
                    <i class="fas fa-exclamation-circle mr-3 mt-0.5 flex-shrink-0"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <!-- Username Field -->
                <div>
                    <label for="username" class="block text-sm font-semibold text-gray-700 mb-1.5">
                        <i class="fas fa-user mr-2 text-blue-600"></i>Username
                    </label>
                    <input type="text" id="username" name="username" required
                           value="<?php echo htmlspecialchars($username); ?>"
                           class="input-field w-full px-4 py-3 rounded-xl text-gray-700 placeholder-gray-400 focus:outline-none"
                           placeholder="Enter your username">
                </div>

                <!-- Password Field with Show/Hide -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-1.5">
                        <i class="fas fa-lock mr-2 text-blue-600"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                               class="input-field w-full px-4 py-3 rounded-xl text-gray-700 placeholder-gray-400 focus:outline-none pr-12"
                               placeholder="••••••••">
                        <button type="button" onclick="togglePassword()"
                                class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-blue-600 transition">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                        class="btn-primary w-full text-white py-3.5 px-4 rounded-xl focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600 font-semibold text-base shadow-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
            </form>

            <!-- Footer -->
            <p class="text-xs text-gray-400 text-center mt-8">
                &copy; <?php echo date('Y'); ?> Perfect Institute. All rights reserved.
            </p>
        </div>
    </div>

    <!-- JavaScript for Password Toggle -->
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>