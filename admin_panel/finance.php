<?php
// finance.php - Enhanced with AJAX, Tailwind, manual income, and CSRF protection (fully secured)
$pageTitle = 'Finance Management';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_finance')) {
    header("Location: unauthorized.php");
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== AJAX HANDLER ====================
if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_finance')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        $action = $_POST['action'] ?? '';

        // ---------- EXPENSE HANDLERS ----------
        if ($action === 'add_expense') {
            if (empty($_POST['expense_title']) || empty($_POST['amount']) || empty($_POST['expense_date'])) {
                echo json_encode(['success' => false, 'message' => 'Title, amount, and date are required']);
                exit;
            }
            $title = $_POST['expense_title'];
            $category = $_POST['expense_category'];
            $amount = (float)$_POST['amount'];
            $payment_mode = $_POST['payment_mode'];
            $expense_date = $_POST['expense_date'];
            $description = $_POST['description'] ?? '';
            $added_by = $_SESSION['admin_name'] ?? 'Admin';

            $stmt = $conn->prepare("INSERT INTO institute_expenses (expense_title, expense_category, amount, payment_mode, expense_date, description, added_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdssss", $title, $category, $amount, $payment_mode, $expense_date, $description, $added_by);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Expense added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            exit;
        }

        if ($action === 'edit_expense') {
            $id = (int)$_POST['id'];
            if ($id <= 0 || empty($_POST['expense_title']) || empty($_POST['amount']) || empty($_POST['expense_date'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit;
            }
            $title = $_POST['expense_title'];
            $category = $_POST['expense_category'];
            $amount = (float)$_POST['amount'];
            $payment_mode = $_POST['payment_mode'];
            $expense_date = $_POST['expense_date'];
            $description = $_POST['description'] ?? '';

            $stmt = $conn->prepare("UPDATE institute_expenses SET 
                                    expense_title=?, expense_category=?, amount=?, 
                                    payment_mode=?, expense_date=?, description=? 
                                    WHERE id=? AND (is_deleted IS NULL OR is_deleted=0)");
            $stmt->bind_param("ssdsssi", $title, $category, $amount, $payment_mode, $expense_date, $description, $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Expense updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            exit;
        }

        if ($action === 'delete_expense') {
            $id = (int)$_POST['id'];
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE institute_expenses SET is_deleted=1 WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if ($conn->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Expense not found']);
            }
            exit;
        }

        if ($action === 'get_expense') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("SELECT * FROM institute_expenses WHERE id=? AND (is_deleted IS NULL OR is_deleted=0)");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                echo json_encode(['success' => true, 'expense' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Expense not found']);
            }
            exit;
        }

        // ---------- INCOME HANDLERS ----------
        if ($action === 'add_income') {
            if (empty($_POST['income_title']) || empty($_POST['amount']) || empty($_POST['income_date'])) {
                echo json_encode(['success' => false, 'message' => 'Title, amount, and date are required']);
                exit;
            }
            $title = $_POST['income_title'];
            $category = $_POST['income_category'] ?? '';
            $amount = (float)$_POST['amount'];
            $payment_mode = $_POST['payment_mode'] ?? '';
            $income_date = $_POST['income_date'];
            $description = $_POST['description'] ?? '';
            $added_by = $_SESSION['admin_name'] ?? 'Admin';

            $stmt = $conn->prepare("INSERT INTO institute_income (income_title, income_category, amount, payment_mode, income_date, description, added_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdssss", $title, $category, $amount, $payment_mode, $income_date, $description, $added_by);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Income added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            exit;
        }

        if ($action === 'edit_income') {
            $id = (int)$_POST['id'];
            if ($id <= 0 || empty($_POST['income_title']) || empty($_POST['amount']) || empty($_POST['income_date'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit;
            }
            $title = $_POST['income_title'];
            $category = $_POST['income_category'] ?? '';
            $amount = (float)$_POST['amount'];
            $payment_mode = $_POST['payment_mode'] ?? '';
            $income_date = $_POST['income_date'];
            $description = $_POST['description'] ?? '';

            $stmt = $conn->prepare("UPDATE institute_income SET 
                                    income_title=?, income_category=?, amount=?, 
                                    payment_mode=?, income_date=?, description=? 
                                    WHERE id=? AND (is_deleted IS NULL OR is_deleted=0)");
            $stmt->bind_param("ssdsssi", $title, $category, $amount, $payment_mode, $income_date, $description, $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Income updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            exit;
        }

        if ($action === 'delete_income') {
            $id = (int)$_POST['id'];
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE institute_income SET is_deleted=1 WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if ($conn->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Income deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Income not found']);
            }
            exit;
        }

        if ($action === 'get_income') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("SELECT * FROM institute_income WHERE id=? AND (is_deleted IS NULL OR is_deleted=0)");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                echo json_encode(['success' => true, 'income' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Income not found']);
            }
            exit;
        }
    }

    // Handle GET request for chart data
    if (isset($_GET['ajax']) && $_GET['action'] === 'chart_data') {
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');

        // Validate dates
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit;
        }

        // Income from approved payments
        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(created_at, '%b %Y') AS month, SUM(amount) AS total 
            FROM payments 
            WHERE payment_status='approved' 
              AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY MIN(created_at) ASC
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $incomePayments = $stmt->get_result();

        // Income from manual entries
        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(income_date, '%b %Y') AS month, SUM(amount) AS total 
            FROM institute_income 
            WHERE income_date BETWEEN ? AND ? 
              AND (is_deleted IS NULL OR is_deleted=0)
            GROUP BY DATE_FORMAT(income_date, '%Y-%m')
            ORDER BY MIN(income_date) ASC
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $incomeManual = $stmt->get_result();

        $incomeMap = [];
        while ($row = $incomePayments->fetch_assoc()) {
            $incomeMap[$row['month']] = ($incomeMap[$row['month']] ?? 0) + $row['total'];
        }
        while ($row = $incomeManual->fetch_assoc()) {
            $incomeMap[$row['month']] = ($incomeMap[$row['month']] ?? 0) + $row['total'];
        }
        uksort($incomeMap, function($a, $b) {
            return strtotime('01 '.$a) - strtotime('01 '.$b);
        });
        $incomeData = [];
        foreach ($incomeMap as $month => $total) {
            $incomeData[] = ['month' => $month, 'total' => $total];
        }

        // Expenses
        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(expense_date, '%b %Y') AS month, SUM(amount) AS total 
            FROM institute_expenses 
            WHERE expense_date BETWEEN ? AND ? 
              AND (is_deleted IS NULL OR is_deleted=0)
            GROUP BY DATE_FORMAT(expense_date, '%Y-%m') 
            ORDER BY MIN(expense_date) ASC
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $expenseData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['income' => $incomeData, 'expense' => $expenseData]);
        exit;
    }
}

// ==================== MAIN PAGE LOGIC ====================
$tab = $_GET['tab'] ?? 'overview';

// Validate and set date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 months'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $start_date = date('Y-m-d', strtotime('-6 months'));
    $end_date = date('Y-m-d');
}

// Overview: summary cards (using prepared statements)
$stmt = $conn->prepare("SELECT SUM(amount) AS total FROM payments WHERE payment_status='approved' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$totalPayments = $stmt->get_result()->fetch_assoc()['total'] ?: 0;

$stmt = $conn->prepare("SELECT SUM(amount) AS total FROM institute_income WHERE income_date BETWEEN ? AND ? AND (is_deleted IS NULL OR is_deleted=0)");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$totalManualIncome = $stmt->get_result()->fetch_assoc()['total'] ?: 0;
$totalIncome = $totalPayments + $totalManualIncome;

$stmt = $conn->prepare("SELECT SUM(amount) AS total FROM institute_expenses WHERE expense_date BETWEEN ? AND ? AND (is_deleted IS NULL OR is_deleted=0)");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$totalExpense = $stmt->get_result()->fetch_assoc()['total'] ?: 0;
$netBalance = $totalIncome - $totalExpense;

$pendingPayments = $conn->query("SELECT COUNT(*) AS cnt FROM payments WHERE payment_status='pending'")->fetch_assoc()['cnt'] ?: 0;

// Budget data
$expenseByCategoryStmt = $conn->prepare("SELECT expense_category, SUM(amount) AS total FROM institute_expenses WHERE expense_date BETWEEN ? AND ? AND (is_deleted IS NULL OR is_deleted=0) GROUP BY expense_category");
$expenseByCategoryStmt->bind_param("ss", $start_date, $end_date);
$expenseByCategoryStmt->execute();
$expenseByCategory = $expenseByCategoryStmt->get_result();

$incomeByMonthStmt = $conn->prepare("
    SELECT month, total FROM (
        SELECT DATE_FORMAT(created_at, '%b %Y') AS month, DATE_FORMAT(created_at, '%Y-%m') AS month_num, amount AS total FROM payments WHERE payment_status='approved' AND DATE(created_at) BETWEEN ? AND ?
        UNION ALL
        SELECT DATE_FORMAT(income_date, '%b %Y') AS month, DATE_FORMAT(income_date, '%Y-%m') AS month_num, amount AS total FROM institute_income WHERE income_date BETWEEN ? AND ? AND (is_deleted IS NULL OR is_deleted=0)
    ) AS combined GROUP BY month_num, month ORDER BY month_num DESC LIMIT 6
");
$incomeByMonthStmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
$incomeByMonthStmt->execute();
$incomeByMonth = $incomeByMonthStmt->get_result();

// Payments tab
$incomeLimit = 10;
$incomePage = isset($_GET['income_page']) ? (int)$_GET['income_page'] : 1;
$incomeOffset = ($incomePage - 1) * $incomeLimit;
$incomeCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM payments WHERE payment_status='approved' AND DATE(created_at) BETWEEN ? AND ?");
$incomeCountStmt->bind_param("ss", $start_date, $end_date);
$incomeCountStmt->execute();
$incomeTotal = $incomeCountStmt->get_result()->fetch_assoc()['cnt'];
$incomePages = ceil($incomeTotal / $incomeLimit);

$incomesStmt = $conn->prepare("
    SELECT p.*, r.full_name 
    FROM payments p 
    LEFT JOIN registrations r ON p.registration_id = r.registration_id 
    WHERE p.payment_status='approved' AND DATE(p.created_at) BETWEEN ? AND ?
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$incomesStmt->bind_param("ssii", $start_date, $end_date, $incomeLimit, $incomeOffset);
$incomesStmt->execute();
$incomes = $incomesStmt->get_result();

// Manual income tab
$manualIncomeLimit = 10;
$manualIncomePage = isset($_GET['manual_income_page']) ? (int)$_GET['manual_income_page'] : 1;
$manualIncomeOffset = ($manualIncomePage - 1) * $manualIncomeLimit;
$manualIncomeCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM institute_income WHERE income_date BETWEEN ? AND ? AND (is_deleted IS NULL OR is_deleted=0)");
$manualIncomeCountStmt->bind_param("ss", $start_date, $end_date);
$manualIncomeCountStmt->execute();
$manualIncomeTotal = $manualIncomeCountStmt->get_result()->fetch_assoc()['cnt'];
$manualIncomePages = ceil($manualIncomeTotal / $manualIncomeLimit);

$manualIncomesStmt = $conn->prepare("SELECT * FROM institute_income WHERE income_date BETWEEN ? AND ? AND (is_deleted IS NULL OR is_deleted=0) ORDER BY income_date DESC LIMIT ? OFFSET ?");
$manualIncomesStmt->bind_param("ssii", $start_date, $end_date, $manualIncomeLimit, $manualIncomeOffset);
$manualIncomesStmt->execute();
$manualIncomes = $manualIncomesStmt->get_result();

// Expenses tab
$expenseLimit = 10;
$expensePage = isset($_GET['expense_page']) ? (int)$_GET['expense_page'] : 1;
$expenseOffset = ($expensePage - 1) * $expenseLimit;
$expenseCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM institute_expenses WHERE expense_date BETWEEN ? AND ? AND (is_deleted IS NULL OR is_deleted=0)");
$expenseCountStmt->bind_param("ss", $start_date, $end_date);
$expenseCountStmt->execute();
$expenseTotal = $expenseCountStmt->get_result()->fetch_assoc()['cnt'];
$expensePages = ceil($expenseTotal / $expenseLimit);

$expensesStmt = $conn->prepare("SELECT * FROM institute_expenses WHERE expense_date BETWEEN ? AND ? AND (is_deleted IS NULL OR is_deleted=0) ORDER BY expense_date DESC LIMIT ? OFFSET ?");
$expensesStmt->bind_param("ssii", $start_date, $end_date, $expenseLimit, $expenseOffset);
$expensesStmt->execute();
$expenses = $expensesStmt->get_result();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Toast style -->
<style>
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 50px;
        color: white;
        font-weight: 500;
        z-index: 2000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
    }
    .toast.success { background: #06d6a0; }
    .toast.error { background: #ef476f; }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    #editModal.hidden, #editIncomeModal.hidden { display: none; }
</style>

<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Finance Management</h2>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex flex-wrap -mb-px">
            <a href="?tab=overview&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="mr-2 py-2 px-4 font-medium text-sm rounded-t-lg <?php echo $tab=='overview' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'; ?>">
                Overview
            </a>
            <a href="?tab=payments&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="mr-2 py-2 px-4 font-medium text-sm rounded-t-lg <?php echo $tab=='payments' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'; ?>">
                Payments
            </a>
            <a href="?tab=manual_income&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="mr-2 py-2 px-4 font-medium text-sm rounded-t-lg <?php echo $tab=='manual_income' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'; ?>">
                Manual Income
            </a>
            <a href="?tab=expenses&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="mr-2 py-2 px-4 font-medium text-sm rounded-t-lg <?php echo $tab=='expenses' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'; ?>">
                Expenses
            </a>
            <a href="?tab=budget&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="mr-2 py-2 px-4 font-medium text-sm rounded-t-lg <?php echo $tab=='budget' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'; ?>">
                Budget
            </a>
            <a href="?tab=add_income" class="mr-2 py-2 px-4 font-medium text-sm rounded-t-lg <?php echo $tab=='add_income' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'; ?>">
                Add Income
            </a>
            <a href="?tab=add" class="py-2 px-4 font-medium text-sm rounded-t-lg <?php echo $tab=='add' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'; ?>">
                Add Expense
            </a>
        </nav>
    </div>

    <!-- Date Range Filter -->
    <?php if (in_array($tab, ['overview', 'payments', 'manual_income', 'expenses', 'budget'])): ?>
    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="px-3 py-2 border rounded-lg">
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-filter mr-2"></i> Apply Filter
                </button>
            </div>
            <div>
                <a href="?tab=<?php echo htmlspecialchars($tab); ?>" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">
                    <i class="fas fa-times mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Tab contents -->
    <?php if ($tab == 'overview'): ?>
        <!-- Overview Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-4 text-white">
                <p class="text-green-100 text-sm">Total Income (Payments + Manual)</p>
                <p class="text-2xl font-bold">₹<?php echo number_format($totalIncome, 2); ?></p>
                <p class="text-xs text-green-200 mt-1"><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
            </div>
            <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-lg p-4 text-white">
                <p class="text-red-100 text-sm">Total Expenses</p>
                <p class="text-2xl font-bold">₹<?php echo number_format($totalExpense, 2); ?></p>
                <p class="text-xs text-red-200 mt-1"><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
            </div>
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-4 text-white">
                <p class="text-blue-100 text-sm">Net Balance</p>
                <p class="text-2xl font-bold <?php echo $netBalance >= 0 ? '' : 'text-red-200'; ?>">₹<?php echo number_format($netBalance, 2); ?></p>
            </div>
            <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg shadow-lg p-4 text-white">
                <p class="text-yellow-100 text-sm">Pending Payments</p>
                <p class="text-2xl font-bold"><?php echo $pendingPayments; ?></p>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3">Income Trend (Selected Period)</h3>
                <canvas id="incomeChart" class="h-64 w-full"></canvas>
                <!-- Empty message will be added by JS -->
            </div>
            <div class="bg-white border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3">Expense Breakdown by Category</h3>
                <canvas id="expensePieChart" class="h-64 w-full"></canvas>
                <?php if ($expenseByCategory->num_rows == 0): ?>
                <p class="text-center text-gray-500 mt-4">No expenses for this period.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($tab == 'payments'): ?>
        <!-- Payments List -->
        <h3 class="text-lg font-semibold mb-3">Approved Payments (<?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>)</h3>
        <?php if ($incomes->num_rows == 0): ?>
        <p class="text-center text-gray-500 py-8">No approved payments found for this period.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reg ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($inc = $incomes->fetch_assoc()): ?>
                    <tr>
                        <td class="px-4 py-3 text-sm"><?php echo date('d M Y', strtotime($inc['created_at'])); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php if (!empty($inc['registration_id'])): ?>
                                <a href="registration_view.php?id=<?php echo urlencode($inc['registration_id']); ?>" class="text-indigo-600 hover:underline">
                                    <?php echo htmlspecialchars($inc['registration_id']); ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($inc['payer_name'] ?: $inc['full_name']); ?></td>
                        <td class="px-4 py-3 text-sm font-medium">₹<?php echo number_format($inc['amount'], 2); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo ucfirst(htmlspecialchars($inc['payment_method'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($incomePages > 1): ?>
        <div class="flex justify-center mt-4">
            <nav class="inline-flex rounded-md shadow">
                <?php if ($incomePage > 1): ?>
                <a href="?tab=payments&income_page=<?php echo $incomePage-1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-l-md">Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $incomePages; $i++): ?>
                    <?php if ($i == $incomePage): ?>
                    <span class="px-3 py-2 bg-indigo-600 border border-indigo-600 text-sm font-medium text-white"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?tab=payments&income_page=<?php echo $i; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($incomePage < $incomePages): ?>
                <a href="?tab=payments&income_page=<?php echo $incomePage+1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-r-md">Next</a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($tab == 'manual_income'): ?>
        <!-- Manual Income List -->
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-lg font-semibold">Manual Income Records (<?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>)</h3>
            <a href="?tab=add_income" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-plus-circle mr-2"></i> Add Income
            </a>
        </div>
        <?php if ($manualIncomes->num_rows == 0): ?>
        <p class="text-center text-gray-500 py-8">No manual income records found for this period.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Mode</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Added By</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="income-tbody">
                    <?php while ($inc = $manualIncomes->fetch_assoc()): ?>
                    <tr data-id="<?php echo $inc['id']; ?>">
                        <td class="px-4 py-3 text-sm"><?php echo date('d M Y', strtotime($inc['income_date'])); ?></td>
                        <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($inc['income_title']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($inc['income_category'] ?: '—'); ?></td>
                        <td class="px-4 py-3 text-sm">₹<?php echo number_format($inc['amount'], 2); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($inc['payment_mode'] ?: '—'); ?></td>
                        <td class="px-4 py-3 text-sm max-w-xs truncate"><?php echo htmlspecialchars($inc['description']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($inc['added_by']); ?></td>
                        <td class="px-4 py-3 text-sm space-x-2">
                            <button onclick="editIncome(<?php echo $inc['id']; ?>)" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteIncome(<?php echo $inc['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($manualIncomePages > 1): ?>
        <div class="flex justify-center mt-4">
            <nav class="inline-flex rounded-md shadow">
                <?php if ($manualIncomePage > 1): ?>
                <a href="?tab=manual_income&manual_income_page=<?php echo $manualIncomePage-1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-l-md">Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $manualIncomePages; $i++): ?>
                    <?php if ($i == $manualIncomePage): ?>
                    <span class="px-3 py-2 bg-indigo-600 border border-indigo-600 text-sm font-medium text-white"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?tab=manual_income&manual_income_page=<?php echo $i; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($manualIncomePage < $manualIncomePages): ?>
                <a href="?tab=manual_income&manual_income_page=<?php echo $manualIncomePage+1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-r-md">Next</a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($tab == 'expenses'): ?>
        <!-- Expenses List -->
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-lg font-semibold">Expense Records (<?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>)</h3>
            <a href="?tab=add" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-plus-circle mr-2"></i> Add Expense
            </a>
        </div>
        <?php if ($expenses->num_rows == 0): ?>
        <p class="text-center text-gray-500 py-8">No expense records found for this period.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Mode</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Added By</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="expenses-tbody">
                    <?php while ($exp = $expenses->fetch_assoc()): ?>
                    <tr data-id="<?php echo $exp['id']; ?>">
                        <td class="px-4 py-3 text-sm"><?php echo date('d M Y', strtotime($exp['expense_date'])); ?></td>
                        <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($exp['expense_title']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($exp['expense_category']); ?></td>
                        <td class="px-4 py-3 text-sm">₹<?php echo number_format($exp['amount'], 2); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($exp['payment_mode']); ?></td>
                        <td class="px-4 py-3 text-sm max-w-xs truncate"><?php echo htmlspecialchars($exp['description']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($exp['added_by']); ?></td>
                        <td class="px-4 py-3 text-sm space-x-2">
                            <button onclick="editExpense(<?php echo $exp['id']; ?>)" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteExpense(<?php echo $exp['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($expensePages > 1): ?>
        <div class="flex justify-center mt-4">
            <nav class="inline-flex rounded-md shadow">
                <?php if ($expensePage > 1): ?>
                <a href="?tab=expenses&expense_page=<?php echo $expensePage-1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-l-md">Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $expensePages; $i++): ?>
                    <?php if ($i == $expensePage): ?>
                    <span class="px-3 py-2 bg-indigo-600 border border-indigo-600 text-sm font-medium text-white"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?tab=expenses&expense_page=<?php echo $i; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($expensePage < $expensePages): ?>
                <a href="?tab=expenses&expense_page=<?php echo $expensePage+1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-r-md">Next</a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($tab == 'budget'): ?>
        <!-- Budget Report -->
        <h3 class="text-lg font-semibold mb-4">Budget Summary (<?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3">Income by Month (Payments + Manual)</h3>
                <?php if ($incomeByMonth->num_rows == 0): ?>
                <p class="text-gray-500">No income data for this period.</p>
                <?php else: ?>
                <table class="min-w-full">
                    <thead><tr><th class="text-left">Month</th><th class="text-left">Amount</th></tr></thead>
                    <tbody>
                        <?php 
                        $incomeByMonth->data_seek(0);
                        while ($row = $incomeByMonth->fetch_assoc()): ?>
                        <tr><td><?php echo htmlspecialchars($row['month']); ?></td><td>₹<?php echo number_format($row['total'], 2); ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div class="bg-white border rounded-lg p-4">
                <h3 class="text-lg font-semibold mb-3">Expense by Category</h3>
                <?php if ($expenseByCategory->num_rows == 0): ?>
                <p class="text-gray-500">No expenses for this period.</p>
                <?php else: ?>
                <table class="min-w-full">
                    <thead><tr><th class="text-left">Category</th><th class="text-left">Amount</th></tr></thead>
                    <tbody>
                        <?php 
                        $expenseByCategory->data_seek(0);
                        while ($row = $expenseByCategory->fetch_assoc()): ?>
                        <tr><td><?php echo htmlspecialchars($row['expense_category']); ?></td><td>₹<?php echo number_format($row['total'], 2); ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($tab == 'add_income'): ?>
        <!-- Add Income Form -->
        <div class="max-w-2xl mx-auto">
            <h3 class="text-lg font-semibold mb-4">Add New Income</h3>
            <form id="incomeForm" onsubmit="submitIncome(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Income Title *</label>
                    <input type="text" name="income_title" id="income_title" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <input type="text" name="income_category" id="income_category" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., Donation, Other Services">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) *</label>
                    <input type="number" step="0.01" name="amount" id="income_amount" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode</label>
                    <select name="payment_mode" id="income_payment_mode" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Income Date *</label>
                    <input type="date" name="income_date" id="income_date" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="income_description" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-save mr-2"></i> Save Income
                </button>
                <div id="income-form-message" class="mt-3"></div>
            </form>
        </div>

    <?php elseif ($tab == 'add'): ?>
        <!-- Add Expense Form -->
        <div class="max-w-2xl mx-auto">
            <h3 class="text-lg font-semibold mb-4">Add New Expense</h3>
            <form id="expenseForm" onsubmit="submitExpense(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expense Title *</label>
                    <input type="text" name="expense_title" id="expense_title" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select name="expense_category" id="expense_category" required class="w-full px-3 py-2 border rounded-lg">
                        <option value="Rent">Rent</option>
                        <option value="Staff Salary">Staff Salary</option>
                        <option value="Ads / Marketing">Ads / Marketing</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Miscellaneous">Miscellaneous</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹) *</label>
                    <input type="number" step="0.01" name="amount" id="expense_amount" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode *</label>
                    <select name="payment_mode" id="expense_payment_mode" required class="w-full px-3 py-2 border rounded-lg">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expense Date *</label>
                    <input type="date" name="expense_date" id="expense_date" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="expense_description" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-save mr-2"></i> Save Expense
                </button>
                <div id="expense-form-message" class="mt-3"></div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Expense Modal -->
<div id="editModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeEditModal()"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold mb-4">Edit Expense</h3>
            <form id="editExpenseForm" onsubmit="updateExpense(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="expense_title" id="edit_title" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="expense_category" id="edit_category" required class="w-full px-3 py-2 border rounded-lg">
                        <option value="Rent">Rent</option>
                        <option value="Staff Salary">Staff Salary</option>
                        <option value="Ads / Marketing">Ads / Marketing</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Miscellaneous">Miscellaneous</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input type="number" step="0.01" name="amount" id="edit_amount" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode</label>
                    <select name="payment_mode" id="edit_payment_mode" required class="w-full px-3 py-2 border rounded-lg">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="expense_date" id="edit_date" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_description" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Income Modal -->
<div id="editIncomeModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeEditIncomeModal()"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold mb-4">Edit Income</h3>
            <form id="editIncomeForm" onsubmit="updateIncome(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="edit_income_id">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="income_title" id="edit_income_title" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <input type="text" name="income_category" id="edit_income_category" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input type="number" step="0.01" name="amount" id="edit_income_amount" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode</label>
                    <select name="payment_mode" id="edit_income_payment_mode" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="income_date" id="edit_income_date" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_income_description" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeEditIncomeModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toast notification
function showToast(msg, type) {
    const msgDiv = document.createElement('div');
    msgDiv.className = `toast ${type}`;
    msgDiv.textContent = msg;
    document.body.appendChild(msgDiv);
    setTimeout(() => {
        msgDiv.style.opacity = '0';
        setTimeout(() => msgDiv.remove(), 500);
    }, 3000);
}

// ========== EXPENSE FUNCTIONS ==========
function submitExpense(e) {
    e.preventDefault();
    const form = document.getElementById('expenseForm');
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'add_expense');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            form.reset();
            setTimeout(() => window.location.href = '?tab=expenses', 1000);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function editExpense(id) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            ajax: 1, 
            action: 'get_expense', 
            id: id,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const exp = data.expense;
            document.getElementById('edit_id').value = exp.id;
            document.getElementById('edit_title').value = exp.expense_title;
            document.getElementById('edit_category').value = exp.expense_category;
            document.getElementById('edit_amount').value = exp.amount;
            document.getElementById('edit_payment_mode').value = exp.payment_mode;
            document.getElementById('edit_date').value = exp.expense_date;
            document.getElementById('edit_description').value = exp.description;
            document.getElementById('editModal').classList.remove('hidden');
        } else {
            showToast(data.message, 'error');
        }
    });
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function updateExpense(e) {
    e.preventDefault();
    const form = document.getElementById('editExpenseForm');
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'edit_expense');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeEditModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function deleteExpense(id) {
    if (!confirm('Are you sure you want to delete this expense?')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            ajax: 1, 
            action: 'delete_expense', 
            id: id,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) row.remove();
        } else {
            showToast(data.message, 'error');
        }
    });
}

// ========== INCOME FUNCTIONS ==========
function submitIncome(e) {
    e.preventDefault();
    const form = document.getElementById('incomeForm');
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'add_income');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            form.reset();
            setTimeout(() => window.location.href = '?tab=manual_income', 1000);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function editIncome(id) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            ajax: 1, 
            action: 'get_income', 
            id: id,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const inc = data.income;
            document.getElementById('edit_income_id').value = inc.id;
            document.getElementById('edit_income_title').value = inc.income_title;
            document.getElementById('edit_income_category').value = inc.income_category || '';
            document.getElementById('edit_income_amount').value = inc.amount;
            document.getElementById('edit_income_payment_mode').value = inc.payment_mode || '';
            document.getElementById('edit_income_date').value = inc.income_date;
            document.getElementById('edit_income_description').value = inc.description || '';
            document.getElementById('editIncomeModal').classList.remove('hidden');
        } else {
            showToast(data.message, 'error');
        }
    });
}

function closeEditIncomeModal() {
    document.getElementById('editIncomeModal').classList.add('hidden');
}

function updateIncome(e) {
    e.preventDefault();
    const form = document.getElementById('editIncomeForm');
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'edit_income');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeEditIncomeModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function deleteIncome(id) {
    if (!confirm('Are you sure you want to delete this income?')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            ajax: 1, 
            action: 'delete_income', 
            id: id,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) row.remove();
        } else {
            showToast(data.message, 'error');
        }
    });
}

// Charts (if on overview tab)
<?php if ($tab == 'overview'): ?>
// Income chart
const incomeCtx = document.getElementById('incomeChart')?.getContext('2d');
if (incomeCtx) {
    fetch('?ajax=1&action=chart_data&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>')
        .then(res => res.json())
        .then(data => {
            if (data.income.length === 0) {
                // Show empty message
                const chartDiv = document.getElementById('incomeChart').parentNode;
                const msg = document.createElement('p');
                msg.className = 'text-center text-gray-500 mt-4';
                msg.textContent = 'No income data for this period.';
                chartDiv.appendChild(msg);
                return;
            }
            const labels = data.income.map(item => item.month);
            const values = data.income.map(item => item.total);
            new Chart(incomeCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Income (₹)',
                        data: values,
                        borderColor: 'green',
                        backgroundColor: 'rgba(0,128,0,0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } }
                }
            });
        });
}

// Expense pie chart
const expenseCtx = document.getElementById('expensePieChart')?.getContext('2d');
if (expenseCtx) {
    <?php
    $expenseByCategory->data_seek(0);
    $catLabels = [];
    $catValues = [];
    while ($row = $expenseByCategory->fetch_assoc()) {
        $catLabels[] = $row['expense_category'];
        $catValues[] = $row['total'];
    }
    ?>
    new Chart(expenseCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($catLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($catValues); ?>,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40']
            }]
        }
    });
}
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>