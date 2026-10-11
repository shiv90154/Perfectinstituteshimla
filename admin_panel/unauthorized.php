<?php
/**
 * unauthorized.php - Access Denied Page
 * Displayed when a logged-in user lacks permission for a page.
 */

$pageTitle = 'Access Denied';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated at all
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

/**
 * Log unauthorized access attempts for security monitoring.
 *
 * @param int    $user_id
 * @param string $username
 * @param string $requested_page
 * @param string $ip
 */
function logUnauthorizedAttempt($user_id, $username, $requested_page, $ip) {
    $log_message = sprintf(
        "[UNAUTHORIZED] User ID: %d | Username: %s | Page: %s | IP: %s | Time: %s",
        $user_id,
        $username,
        $requested_page,
        $ip,
        date('Y-m-d H:i:s')
    );
    error_log($log_message);
}

// Log the attempt
logUnauthorizedAttempt(
    $_SESSION['admin_id'],
    $_SESSION['admin_username'] ?? 'unknown',
    $_SERVER['REQUEST_URI'] ?? 'unknown',
    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
);

// Get user details safely
$user_role = $_SESSION['admin_role'] ?? 'Unknown';
$user_name = $_SESSION['admin_name'] ?? 'User';

// Include layout
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="flex items-center justify-center min-h-[calc(100vh-200px)] p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center border border-gray-100">
        <!-- Icon -->
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-lock text-4xl text-red-500"></i>
        </div>

        <!-- Title -->
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Access Denied</h1>

        <!-- Message -->
        <p class="text-gray-600 mb-4">
            You do not have permission to view this page.
        </p>

        <!-- User Info -->
        <div class="bg-gray-50 rounded-lg p-3 mb-6 text-sm">
            <p class="text-gray-700">
                <span class="font-medium">Logged in as:</span>
                <?php echo htmlspecialchars($user_name); ?>
            </p>
            <p class="text-gray-700">
                <span class="font-medium">Your role:</span>
                <span class="inline-block bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded-full text-xs ml-1">
                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user_role))); ?>
                </span>
            </p>
        </div>

        <!-- Help Text -->
        <p class="text-xs text-gray-400 mb-6">
            If you believe this is an error, please contact your administrator.
        </p>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="dashboard.php" 
               class="inline-flex items-center justify-center px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors duration-200 shadow-md hover:shadow-lg">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
            <!-- Optional: Request Access button (you can link to a contact form) -->
            <a href="mailto:info@perfectinstitute.in?subject=Access%20Request%20-%20<?php echo urlencode($user_name); ?>" 
               class="inline-flex items-center justify-center px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200">
                <i class="fas fa-envelope mr-2"></i>
                Request Access
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>