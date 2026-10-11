<?php
// sidebar.php - Enhanced collapsible sidebar with permission checks (fixed function name)
// Expects $conn (database connection) and $_SESSION['admin_id'] to be set.
// Also expects $pageTitle (optional) for the top bar.

// Get collapsed state from cookie
$collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true';

// Safely get session data
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'Administrator';
$adminId = $_SESSION['admin_id'] ?? 0;

// Fetch user permissions if needed (optional)
$userPermissions = [];
if ($adminId && isset($conn)) {
    $permQuery = "SELECT p.permission_key FROM permissions p 
                  JOIN role_permissions rp ON p.id = rp.permission_id 
                  WHERE rp.role_id = (SELECT role_id FROM admins WHERE id = ?)";
    $stmt = $conn->prepare($permQuery);
    if ($stmt) {
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $userPermissions[] = $row['permission_key'];
        }
        $stmt->close();
    }
}

// Helper function to check permission (renamed to avoid conflict with functions.php)
function userCan($perm, $userPermissions) {
    return in_array($perm, $userPermissions);
}

// Determine active sections
$current_page = basename($_SERVER['PHP_SELF']);
$full_url = $_SERVER['REQUEST_URI'];

// Helper to check active state for lead submenu
function isLeadActive($view = null) {
    global $full_url;
    if ($view === null) {
        return strpos($full_url, 'leads.php') !== false && strpos($full_url, 'view=') === false;
    }
    return strpos($full_url, "leads.php?view=$view") !== false || strpos($full_url, "leads.php&view=$view") !== false;
}

// Helper for inquiries submenu
function isInquiryActive($status = null) {
    global $full_url;
    if ($status === null) {
        return strpos($full_url, 'inquiries.php') !== false && strpos($full_url, 'status=') === false;
    }
    return strpos($full_url, "inquiries.php?status=$status") !== false || strpos($full_url, "inquiries.php&status=$status") !== false;
}

// Helper for finance tabs
function isFinanceActive($tab = 'overview') {
    global $full_url;
    if ($tab === 'overview') {
        return strpos($full_url, 'finance.php') !== false && (strpos($full_url, 'tab=') === false || strpos($full_url, 'tab=overview') !== false);
    }
    return strpos($full_url, "finance.php?tab=$tab") !== false || strpos($full_url, "finance.php&tab=$tab") !== false;
}

$is_content_active = in_array($current_page, ['blogs.php', 'videos.php']);
$is_lead_active = strpos($full_url, 'leads.php') !== false;
$is_inquiry_active = strpos($full_url, 'inquiries.php') !== false;
$is_course_active = $current_page == 'courses.php';
$is_coupon_active = $current_page == 'coupons.php';  // ✅ NEW
$is_finance_active = strpos($full_url, 'finance.php') !== false;
$is_admin_active = in_array($current_page, ['admin_users.php', 'permissions.php']);

// ---- Rendering helpers (keep markup short & consistent) ----
function sbCan($perm, $userPermissions) {
    return empty($userPermissions) || in_array($perm, $userPermissions);
}
function sbLink($href, $icon, $label, $active) {
    $cls = $active ? 'sb-link is-active' : 'sb-link';
    echo '<a href="' . $href . '" class="' . $cls . '" title="' . htmlspecialchars($label) . '"' . ($active ? ' aria-current="page"' : '') . '>'
       . '<i class="' . $icon . ' sb-icon"></i><span class="sb-label">' . htmlspecialchars($label) . '</span></a>';
}
function sbGroupStart($icon, $label, $open) {
    echo '<div x-data="{ open: ' . ($open ? 'true' : 'false') . ' }" class="sb-group">'
       . '<button type="button" @click="open = !open" class="sb-link" title="' . htmlspecialchars($label) . '">'
       . '<i class="' . $icon . ' sb-icon"></i><span class="sb-label">' . htmlspecialchars($label) . '</span>'
       . '<i class="fas fa-chevron-down sb-chevron sb-label" :class="{ \'is-open\': open }"></i></button>'
       . '<div x-show="open" x-cloak class="sb-sub">';
}
function sbSub($href, $icon, $label, $active) {
    echo '<a href="' . $href . '" class="sb-sublink' . ($active ? ' is-active' : '') . '"><i class="' . $icon . '"></i><span>' . htmlspecialchars($label) . '</span></a>';
}
function sbGroupEnd() { echo '</div></div>'; }
?>
<script>
    // Apply saved collapsed state before paint (avoids flicker)
    (function () {
        try { if (document.cookie.indexOf('sidebar_collapsed=true') !== -1) document.documentElement.classList.add('sb-collapsed'); } catch (e) {}
    })();
</script>

<!-- Sidebar -->
<aside id="sidebar" class="admin-sidebar"
       x-data="{}"
       x-init="window.addEventListener('toggle-mobile-sidebar', () => document.body.classList.toggle('sb-open'))">

    <div class="sb-brand">
        <div class="sb-logo"><i class="fas fa-graduation-cap"></i></div>
        <div class="sb-label sb-brand-text">
            <strong>Perfect Institute</strong>
            <span>Admin Panel</span>
        </div>
        <button type="button" class="sb-collapse hidden lg:block" aria-label="Collapse sidebar"
                onclick="var c=document.documentElement.classList.toggle('sb-collapsed'); document.cookie='sidebar_collapsed='+c+'; path=/; max-age=31536000; SameSite=Lax';">
            <i class="fas fa-angles-left"></i>
        </button>
        <button type="button" class="sb-collapse lg:hidden" aria-label="Close sidebar" onclick="document.body.classList.remove('sb-open')">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <nav class="sb-nav" aria-label="Main navigation">
        <div class="sb-section sb-label">Main</div>
        <?php
        if (sbCan('view_dashboard', $userPermissions)) sbLink('dashboard.php', 'fas fa-gauge-high', 'Dashboard', $current_page == 'dashboard.php');
        if (sbCan('manage_registrations', $userPermissions)) sbLink('registrations.php', 'fas fa-clipboard-list', 'Registrations', $current_page == 'registrations.php');
        if (sbCan('manage_rejoined_students', $userPermissions)) sbLink('rejoined_students.php', 'fas fa-user-group', 'Rejoined Students', $current_page == 'rejoined_students.php');
        if (sbCan('manage_payments', $userPermissions)) sbLink('payments.php', 'fas fa-credit-card', 'Payments', $current_page == 'payments.php');
        ?>

        <div class="sb-section sb-label">Manage</div>
        <?php if (sbCan('manage_content', $userPermissions)):
            sbGroupStart('fas fa-newspaper', 'Content Management', $is_content_active);
            sbSub('blogs.php', 'fas fa-blog', 'StudyHub (Blogs)', $current_page == 'blogs.php');
            sbSub('videos.php', 'fab fa-youtube', 'Videos', $current_page == 'videos.php');
            sbGroupEnd();
        endif; ?>

        <?php if (sbCan('manage_leads', $userPermissions)):
            sbGroupStart('fas fa-users', 'Lead Management', $is_lead_active);
            sbSub('leads.php', 'fas fa-address-book', 'All Leads', isLeadActive(null));
            sbSub('leads.php?view=today', 'fas fa-calendar-day', "Today's Follow-ups", isLeadActive('today'));
            sbSub('leads.php?view=overdue', 'fas fa-triangle-exclamation', 'Overdue', isLeadActive('overdue'));
            sbSub('leads.php?view=upcoming', 'fas fa-calendar-alt', 'Upcoming', isLeadActive('upcoming'));
            sbSub('leads.php?view=converted', 'fas fa-circle-check', 'Converted', isLeadActive('converted'));
            sbGroupEnd();
        endif; ?>

        <?php if (sbCan('manage_inquiries', $userPermissions)):
            sbGroupStart('fas fa-circle-question', 'Inquiries', $is_inquiry_active);
            sbSub('inquiries.php?status=pending', 'fas fa-hourglass-half', 'Pending', isInquiryActive('pending'));
            sbSub('inquiries.php?status=completed', 'fas fa-check', 'Completed', isInquiryActive('completed'));
            sbSub('inquiries.php', 'fas fa-list', 'All Inquiries', isInquiryActive(null));
            sbGroupEnd();
        endif; ?>

        <?php if (sbCan('manage_courses', $userPermissions)):
            sbGroupStart('fas fa-book', 'Course Management', $is_course_active);
            sbSub('courses.php', 'fas fa-list-ul', 'All Courses', $current_page == 'courses.php');
            sbGroupEnd();
        endif; ?>

        <?php if (sbCan('manage_coupons', $userPermissions)):
            sbGroupStart('fas fa-tags', 'Coupon Management', $is_coupon_active);
            sbSub('coupons.php', 'fas fa-list', 'All Coupons', $current_page == 'coupons.php');
            sbGroupEnd();
        endif; ?>

        <?php if (sbCan('manage_finance', $userPermissions)):
            sbGroupStart('fas fa-coins', 'Finance Management', $is_finance_active);
            sbSub('finance.php', 'fas fa-chart-pie', 'Overview', isFinanceActive('overview'));
            sbSub('finance.php?tab=expenses', 'fas fa-chart-bar', 'Expenses', isFinanceActive('expenses'));
            sbSub('finance.php?tab=budget', 'fas fa-file-invoice', 'Budget', isFinanceActive('budget'));
            sbGroupEnd();
        endif; ?>

        <?php if (sbCan('manage_admins', $userPermissions) || sbCan('manage_roles', $userPermissions)): ?>
        <div class="sb-section sb-label">System</div>
        <?php
            sbGroupStart('fas fa-user-gear', 'Admin (Control Center)', $is_admin_active);
            sbSub('admin_users.php', 'fas fa-users-gear', 'Create & Manage', $current_page == 'admin_users.php');
            sbSub('permissions.php', 'fas fa-key', 'Permission Details', $current_page == 'permissions.php');
            sbGroupEnd();
        endif; ?>
    </nav>

    <div class="sb-user">
        <div class="sb-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
        <div class="sb-label sb-user-text">
            <strong><?php echo htmlspecialchars($adminName); ?></strong>
            <span><?php echo htmlspecialchars($adminRole); ?></span>
        </div>
        <a href="logout.php" class="sb-logout sb-label" title="Logout"><i class="fas fa-right-from-bracket"></i></a>
    </div>
</aside>

<!-- Mobile overlay -->
<div class="admin-overlay" onclick="document.body.classList.remove('sb-open')"></div>

<!-- Main column -->
<main class="admin-main">
    <!-- Top bar -->
    <div class="admin-topbar">
        <div class="flex items-center gap-3 min-w-0">
            <button type="button" class="topbar-btn lg:hidden" aria-label="Open menu" onclick="document.body.classList.add('sb-open')">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title truncate"><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h1>
        </div>
        <div class="flex items-center gap-3">
            <span class="topbar-user hidden sm:flex">
                <span class="sb-avatar sb-avatar-sm"><?php echo strtoupper(substr($adminName, 0, 1)); ?></span>
                <span><?php echo htmlspecialchars($adminName); ?></span>
            </span>
            <a href="logout.php" class="topbar-logout" title="Logout">
                <i class="fas fa-right-from-bracket"></i> <span class="hidden sm:inline">Logout</span>
            </a>
        </div>
    </div>
    <div class="admin-content">
    <!-- Content from individual pages is inserted here -->
