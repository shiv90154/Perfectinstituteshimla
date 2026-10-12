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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .hero {
            background:
                radial-gradient(circle at 20% 20%, rgba(96,165,250,.35), transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(139,92,246,.35), transparent 45%),
                linear-gradient(145deg, #0f1f4d 0%, #1e3a8a 55%, #1d4ed8 100%);
        }
        .hero::after {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background-image: radial-gradient(rgba(255,255,255,.12) 1px, transparent 1px);
            background-size: 22px 22px;
        }
        .input-field {
            background: #f8fafc; border: 1.5px solid #e2e8f0; transition: all .2s ease;
        }
        .input-field:focus {
            background: #fff; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,.12); outline: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            box-shadow: 0 10px 20px -8px rgba(37,99,235,.6);
            transition: all .2s ease;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 14px 24px -8px rgba(37,99,235,.7); }
        .btn-primary:active { transform: translateY(0); }
        .error-alert { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .fade-up { animation: fadeUp .5s ease both; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50">
<div class="min-h-screen grid lg:grid-cols-2">

    <!-- Left: branding panel (desktop) -->
    <div class="hero relative hidden lg:flex flex-col justify-between p-12 text-white overflow-hidden">
        <div class="relative z-10 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center">
                <i class="fas fa-graduation-cap text-lg"></i>
            </div>
            <span class="text-lg font-semibold tracking-tight">Perfect Institute</span>
        </div>

        <div class="relative z-10 max-w-md">
            <h2 class="text-4xl font-extrabold leading-tight tracking-tight">Manage your institute, all in one place.</h2>
            <p class="mt-4 text-blue-100/90 leading-relaxed">Registrations, payments, leads, courses and finance &mdash; a single secure dashboard for your team.</p>
            <ul class="mt-8 space-y-3 text-sm text-blue-50">
                <li class="flex items-center gap-3"><i class="fas fa-clipboard-list w-5 text-blue-200"></i> Student registrations &amp; admissions</li>
                <li class="flex items-center gap-3"><i class="fas fa-credit-card w-5 text-blue-200"></i> Payments &amp; finance tracking</li>
                <li class="flex items-center gap-3"><i class="fas fa-users w-5 text-blue-200"></i> Leads &amp; inquiries follow-up</li>
            </ul>
        </div>

        <p class="relative z-10 text-xs text-blue-200/80">&copy; <?php echo date('Y'); ?> Perfect Institute. All rights reserved.</p>
    </div>

    <!-- Right: login form -->
    <div class="flex items-center justify-center p-6 sm:p-10">
        <div class="w-full max-w-md fade-up">
            <!-- Mobile brand -->
            <div class="lg:hidden text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-3 text-white" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6)">
                    <i class="fas fa-graduation-cap text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-800">Perfect Institute</h1>
            </div>

            <div class="mb-8">
                <h1 class="hidden lg:block text-3xl font-extrabold text-slate-900 tracking-tight">Welcome back</h1>
                <p class="text-slate-500 mt-2 text-sm">Sign in to the admin portal to continue.</p>
            </div>

            <?php if ($error): ?>
                <div class="error-alert flex items-start p-4 rounded-xl mb-6 text-sm" role="alert">
                    <i class="fas fa-exclamation-circle mr-3 mt-0.5 flex-shrink-0"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div>
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="username" name="username" required autocomplete="username" autofocus
                               value="<?php echo htmlspecialchars($username); ?>"
                               class="input-field w-full pl-11 pr-4 py-3 rounded-xl text-slate-800 placeholder-slate-400"
                               placeholder="Enter your username">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                        <a href="forgot_password.php" class="text-sm font-medium text-blue-700 hover:text-blue-900 hover:underline">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               class="input-field w-full pl-11 pr-12 py-3 rounded-xl text-slate-800 placeholder-slate-400"
                               placeholder="Enter your password">
                        <button type="button" onclick="togglePassword()" aria-label="Show or hide password"
                                class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-blue-600 transition">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="btn-primary w-full text-white py-3.5 px-4 rounded-xl font-semibold text-base focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600">
                    Sign In <i class="fas fa-arrow-right ml-2 text-sm"></i>
                </button>
            </form>

            <p class="mt-8 text-center text-xs text-slate-400">
                <i class="fas fa-shield-halved mr-1"></i> Secure admin access &middot; Authorized personnel only
            </p>
        </div>
    </div>
</div>

<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye', !show);
        icon.classList.toggle('fa-eye-slash', show);
    }
</script>
</body>
</html>
