<?php

require_once '../config/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$course_filter = $_GET['course'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$query = "SELECT * FROM registrations WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $query .= " AND status = :status";
    $params[':status'] = $status_filter;
}

if ($course_filter !== 'all') {
    $query .= " AND course_name LIKE :course";
    $params[':course'] = "%$course_filter%";
}

if (!empty($search_query)) {
    $query .= " AND (full_name LIKE :search OR email LIKE :search OR contact LIKE :search OR registration_id LIKE :search)";
    $params[':search'] = "%$search_query%";
}

if (!empty($date_from)) {
    $query .= " AND DATE(registration_date) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(registration_date) <= :date_to";
    $params[':date_to'] = $date_to;
}

$query .= " ORDER BY registration_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as payment_pending
    FROM registrations";

$stats_stmt = $db->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get unique courses for filter
$courses_query = "SELECT DISTINCT course_name FROM registrations ORDER BY course_name";
$courses_stmt = $db->query($courses_query);
$courses = $courses_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle actions (approve/reject/delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['id'])) {
        $action = $_POST['action'];
        $id = $_POST['id'];
        
        switch ($action) {
            case 'approve':
                $update_query = "UPDATE registrations SET status = 'approved' WHERE id = :id";
                break;
            case 'reject':
                $update_query = "UPDATE registrations SET status = 'rejected' WHERE id = :id";
                break;
            case 'delete':
                $update_query = "DELETE FROM registrations WHERE id = :id";
                break;
            case 'mark_paid':
                $update_query = "UPDATE registrations SET payment_status = 'paid' WHERE id = :id";
                break;
            default:
                break;
        }
        
        if (isset($update_query)) {
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':id', $id);
            if ($update_stmt->execute()) {
                $_SESSION['message'] = "Action completed successfully!";
            
                exit();
            }
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Perfect Institute</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #0a192f;
            --red: #e63946;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --gray: #6c757d;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f5f7fa;
            color: #333;
        }

   

       

        /* Main Container */
        .admin-container {
            padding: 30px;
            max-width: 1800px;
            margin: 0 auto;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .icon-total { background: var(--navy); }
        .icon-pending { background: var(--warning); }
        .icon-approved { background: var(--success); }
        .icon-rejected { background: var(--red); }
        .icon-paid { background: #28a745; }
        .icon-payment-pending { background: #ffc107; }

        .stat-content h3 {
            font-size: 2rem;
            margin-bottom: 5px;
            color: var(--navy);
        }

        .stat-content p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .filter-section h3 {
            color: var(--navy);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--navy);
            font-size: 0.9rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 2px solid #e1e5eb;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--navy);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .filter-btn {
            padding: 10px 20px;
            background: var(--navy);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            height: 40px;
        }

        .filter-btn:hover {
            background: var(--light-navy);
        }

        .reset-btn {
            background: var(--gray);
        }

        .reset-btn:hover {
            background: #5a6268;
        }

        /* Registrations Table */
        .registrations-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            color: var(--navy);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .export-btn {
            padding: 8px 15px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            text-decoration: none;
        }

        .export-btn:hover {
            background: #218838;
        }

        .table-container {
            overflow-x: auto;
        }

        .registrations-table {
            width: 100%;
            border-collapse: collapse;
        }

        .registrations-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--navy);
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }

        .registrations-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
        }

        .registrations-table tr:hover {
            background: #f8f9fa;
        }

        .registrations-table tr:nth-child(even) {
            background: #fcfcfc;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .payment-paid {
            background: #d4edda;
            color: #155724;
        }

        .payment-pending {
            background: #fff3cd;
            color: #856404;
        }

        .payment-partial {
            background: #cce5ff;
            color: #004085;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-paid {
            background: #28a745;
            color: white;
        }

        .btn-paid:hover {
            background: #218838;
        }

        .btn-delete {
            background: #6c757d;
            color: white;
        }

        .btn-delete:hover {
            background: #5a6268;
        }

        /* Modal for View Details */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            animation: modalSlide 0.3s;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: var(--navy);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.1);
        }

        .modal-body {
            padding: 30px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .detail-item {
            margin-bottom: 15px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--gray);
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .page-link {
            padding: 8px 15px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: var(--navy);
            font-weight: 600;
            transition: all 0.3s;
        }

        .page-link:hover {
            background: var(--navy);
            color: white;
            border-color: var(--navy);
        }

        .page-link.active {
            background: var(--navy);
            color: white;
            border-color: var(--navy);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .admin-container {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .table-actions {
                flex-direction: column;
            }

            .registrations-table {
                font-size: 0.9rem;
            }

            .registrations-table th,
            .registrations-table td {
                padding: 10px 5px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .modal-body {
                padding: 20px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-header">
 

    <!-- Main Container -->
    <div class="admin-container">
        <!-- Stats Section -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                    <p>Total Registrations</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                    <p>Pending Approval</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['approved'] ?? 0; ?></h3>
                    <p>Approved</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['rejected'] ?? 0; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-paid">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['paid'] ?? 0; ?></h3>
                    <p>Payment Complete</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-payment-pending">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['payment_pending'] ?? 0; ?></h3>
                    <p>Payment Pending</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h3><i class="fas fa-filter"></i> Filter Registrations</h3>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="all">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="course">Course</label>
                    <select name="course" id="course">
                        <option value="all">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo htmlspecialchars($course); ?>" 
                                <?php echo $course_filter === $course ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="search">Search (Name/Email/Phone/ID)</label>
                    <input type="text" name="search" id="search" 
                           value="<?php echo htmlspecialchars($search_query); ?>"
                           placeholder="Search...">
                </div>

                <div class="filter-group">
                    <label for="date_from">From Date</label>
                    <input type="date" name="date_from" id="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">To Date</label>
                    <input type="date" name="date_to" id="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="dashboard.php" class="filter-btn reset-btn">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Registrations Table -->
        <div class="registrations-container">
            <div class="table-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Registrations (<?php echo count($registrations); ?>)
                </h3>
                <div class="table-actions">
                    <button class="export-btn" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table class="registrations-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registrations)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                    <p style="color: #666;">No registrations found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registrations as $registration): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($registration['registration_id']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($registration['full_name']); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($registration['father_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($registration['course_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($registration['email']); ?><br>
                                        <small style="color: #666;">DOB: <?php echo date('d-m-Y', strtotime($registration['dob'])); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($registration['contact']); ?><br>
                                        <?php if (!empty($registration['whatsapp'])): ?>
                                            <small style="color: #666;">WA: <?php echo htmlspecialchars($registration['whatsapp']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d-m-Y', strtotime($registration['registration_date'])); ?><br>
                                        <small style="color: #666;"><?php echo date('h:i A', strtotime($registration['registration_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $registration['status']; ?>">
                                            <?php echo ucfirst($registration['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge payment-<?php echo $registration['payment_status']; ?>">
                                            <?php echo ucfirst($registration['payment_status']); ?>
                                        </span><br>
                                        <?php if ($registration['amount_paid'] > 0): ?>
                                            <small style="color: #666;">₹<?php echo number_format($registration['amount_paid'], 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn btn-view" onclick="viewRegistration(<?php echo $registration['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if ($registration['status'] == 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id" value="<?php echo $registration['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="action-btn btn-approve" onclick="return confirm('Approve this registration?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id" value="<?php echo $registration['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="action-btn btn-reject" onclick="return confirm('Reject this registration?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($registration['payment_status'] == 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id" value="<?php echo $registration['id']; ?>">
                                                    <input type="hidden" name="action" value="mark_paid">
                                                    <button type="submit" class="action-btn btn-paid" onclick="return confirm('Mark payment as paid?')">
                                                        <i class="fas fa-credit-card"></i> Mark Paid
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="id" value="<?php echo $registration['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="action-btn btn-delete" onclick="return confirm('Delete this registration permanently?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal for View Details -->
        <div class="modal-overlay" id="detailsModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-user-graduate"></i> Registration Details</h3>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Details will be loaded here via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // View registration details
        function viewRegistration(id) {
            // Show loading
            document.getElementById('modalBody').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #0a192f;"></i>
                    <p>Loading details...</p>
                </div>
            `;
            
            // Show modal
            document.getElementById('detailsModal').style.display = 'flex';
            
            // Load details via AJAX
            fetch(`get_registration.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalBody').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('modalBody').innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i>
                            <p>Error loading details. Please try again.</p>
                        </div>
                    `;
                });
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Export to Excel
        function exportToExcel() {
            // Get table data
            const table = document.querySelector('.registrations-table');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            rows.forEach(row => {
                const rowData = [];
                row.querySelectorAll('th, td').forEach(cell => {
                    // Remove action buttons from export
                    if (!cell.closest('.action-buttons')) {
                        rowData.push(cell.innerText.replace(/\n/g, ' ').trim());
                    }
                });
                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            });
            
            // Create download link
            const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'registrations_' + new Date().toISOString().split('T')[0] + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Auto-close alert messages after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>