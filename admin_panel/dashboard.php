<?php
/**
 * Dashboard - Main admin overview with stats, charts, and recent activity
 * Requires: view_dashboard permission
 */

$pageTitle = 'Dashboard';
require_once 'includes/config.php';

// Authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/functions.php';

// Permission check
if (!hasPermission($conn, $_SESSION['admin_id'], 'view_dashboard')) {
    header("Location: unauthorized.php");
    exit;
}

/* ==================== AJAX HANDLERS ==================== */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // Helper function to send JSON error and exit
    function jsonError($message, $code = 500) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }

    // Main stats
    if ($_GET['ajax'] === 'stats') {
        $stats = [];
        $queries = [
            'totalStudents'   => "SELECT COUNT(*) AS cnt FROM registrations WHERE status='approved'",
            'totalCourses'    => "SELECT COUNT(*) AS cnt FROM courses WHERE is_deleted=0",
            'totalLeads'      => "SELECT COUNT(*) AS cnt FROM leads_data WHERE is_deleted=0",
            'totalRevenue'    => "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE payment_status='approved'",
            'totalExpenses'   => "SELECT COALESCE(SUM(amount), 0) AS total FROM institute_expenses WHERE is_deleted=0",
            'todayFollowups'  => "SELECT COUNT(*) AS cnt FROM leads_data WHERE DATE(next_followup_date) = CURDATE() AND is_deleted=0",
            'pendingRegs'     => "SELECT COUNT(*) AS cnt FROM registrations WHERE status='pending'",
            'pendingPayments' => "SELECT COUNT(*) AS cnt FROM payments WHERE payment_status='pending'",
            'overdueFollowups'=> "SELECT COUNT(*) AS cnt FROM leads_data WHERE next_followup_date < CURDATE() AND is_deleted=0"
        ];

        foreach ($queries as $key => $sql) {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                jsonError("Database error: " . $conn->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stats[$key] = $row['cnt'] ?? $row['total'] ?? 0;
            $stmt->close();
        }

        $stats['netProfit'] = $stats['totalRevenue'] - $stats['totalExpenses'];

        echo json_encode($stats);
        exit;
    }

    // Monthly income & expenses for chart (last 6 months)
    if ($_GET['ajax'] === 'monthly_data') {
        $months = [];
        $income = [];
        $expense = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[] = date('M Y', strtotime("-$i months"));

            // Income
            $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments 
                                    WHERE payment_status='approved' 
                                    AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            if (!$stmt) jsonError("Prepare failed: " . $conn->error);
            $stmt->bind_param('s', $month);
            $stmt->execute();
            $inc = $stmt->get_result()->fetch_assoc()['total'];
            $income[] = (float)$inc;
            $stmt->close();

            // Expenses
            $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM institute_expenses 
                                    WHERE is_deleted=0 
                                    AND DATE_FORMAT(expense_date, '%Y-%m') = ?");
            if (!$stmt) jsonError("Prepare failed: " . $conn->error);
            $stmt->bind_param('s', $month);
            $stmt->execute();
            $exp = $stmt->get_result()->fetch_assoc()['total'];
            $expense[] = (float)$exp;
            $stmt->close();
        }

        echo json_encode([
            'months'  => $months,
            'income'  => $income,
            'expense' => $expense
        ]);
        exit;
    }

    // Recent leads (with follow-up info)
    if ($_GET['ajax'] === 'recent_leads') {
        $stmt = $conn->prepare("SELECT name, status, next_followup_date FROM leads_data WHERE is_deleted=0 ORDER BY id DESC LIMIT 7");
        if (!$stmt) jsonError("Prepare failed: " . $conn->error);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode($rows);
        exit;
    }

    // Recent registrations
    if ($_GET['ajax'] === 'recent_regs') {
        $stmt = $conn->prepare("SELECT full_name, course_name, status FROM registrations ORDER BY id DESC LIMIT 7");
        if (!$stmt) jsonError("Prepare failed: " . $conn->error);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode($rows);
        exit;
    }

    // Recent payments
    if ($_GET['ajax'] === 'recent_payments') {
        $stmt = $conn->prepare("SELECT p.amount, r.full_name, p.payment_status 
                                FROM payments p 
                                JOIN registrations r ON p.registration_id = r.registration_id 
                                ORDER BY p.id DESC LIMIT 7");
        if (!$stmt) jsonError("Prepare failed: " . $conn->error);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode($rows);
        exit;
    }

    // Today's follow-ups list
    if ($_GET['ajax'] === 'today_followups') {
        $stmt = $conn->prepare("SELECT id, name, phone, next_followup_date FROM leads_data WHERE DATE(next_followup_date) = CURDATE() AND is_deleted=0 ORDER BY next_followup_date ASC");
        if (!$stmt) jsonError("Prepare failed: " . $conn->error);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode($rows);
        exit;
    }

    // Invalid AJAX request
    jsonError("Invalid AJAX endpoint", 400);
}

// Include layout components
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    .dashboard-container { max-width: 1400px; margin: 0 auto; }

    /* Greeting banner */
    .dash-hero {
        position: relative; overflow: hidden;
        display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
        padding: 1.4rem 1.6rem; margin-bottom: 1.25rem;
        border-radius: 18px; color: #fff;
        background: linear-gradient(120deg, #4338ca 0%, #6366f1 55%, #8b5cf6 100%);
        box-shadow: 0 10px 30px rgba(79, 70, 229, .28);
    }
    .dash-hero::after {
        content: ""; position: absolute; right: -60px; top: -80px; width: 260px; height: 260px; border-radius: 50%;
        background: rgba(255,255,255,.1);
    }
    .dash-hero h2 { font-size: 1.35rem; font-weight: 700; letter-spacing: -.01em; }
    .dash-hero p { font-size: .85rem; opacity: .85; margin-top: .2rem; }
    .dash-date { font-size: .8rem; font-weight: 500; padding: .45rem .9rem; border-radius: 999px; background: rgba(255,255,255,.16); position: relative; z-index: 1; }

    /* Quick actions */
    .quick-actions { display: flex; gap: .6rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
    .quick-btn {
        background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
        padding: .55rem .95rem; font-size: .84rem; font-weight: 500; color: var(--text);
        text-decoration: none; transition: .15s; display: inline-flex; align-items: center; gap: .5rem;
        box-shadow: 0 1px 2px rgba(16,24,40,.04);
    }
    .quick-btn i { color: var(--brand); }
    .quick-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); transform: translateY(-1px); }
    .quick-btn:hover i { color: #fff; }

    /* Stat cards */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
    .stat-card {
        display: flex; align-items: center; gap: .9rem;
        background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
        padding: 1rem 1.1rem; box-shadow: var(--shadow); transition: transform .15s, box-shadow .15s;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(16,24,40,.08); }
    .stat-icon { flex: none; width: 2.8rem; height: 2.8rem; border-radius: 12px; display: grid; place-items: center; font-size: 1.1rem; }
    .stat-icon.blue   { background: #e8ecff; color: #4361ee; }
    .stat-icon.green  { background: #dcfce7; color: #16a34a; }
    .stat-icon.purple { background: #f0e9ff; color: #7c3aed; }
    .stat-icon.gray   { background: #eef0f4; color: #64748b; }
    .stat-icon.red    { background: #fee2e2; color: #dc2626; }
    .stat-icon.orange { background: #fff1d6; color: #d97706; }
    .stat-body { min-width: 0; }
    .stat-title { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .stat-value { font-size: 1.55rem; font-weight: 700; line-height: 1.2; letter-spacing: -.02em; color: var(--text); }
    .stat-value.green { color: #16a34a; }
    .stat-value.red { color: #dc2626; }

    /* Cards */
    .chart-card, .recent-card {
        background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
        padding: 1.15rem 1.25rem; box-shadow: var(--shadow);
    }
    .chart-card { margin-bottom: 1.25rem; }
    .chart-header, .recent-header {
        font-weight: 600; font-size: .95rem; color: var(--text); margin-bottom: .9rem;
        display: flex; align-items: center; gap: .55rem;
    }
    .chart-header i, .recent-header i { color: var(--brand); }
    .chart-container { position: relative; height: 290px; width: 100%; }

    .recent-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem; }
    .recent-list { list-style: none; padding: 0; margin: 0; }
    .recent-list li {
        padding: .65rem 0; border-bottom: 1px solid #f0f2f8;
        display: flex; justify-content: space-between; align-items: center; gap: .6rem; font-size: .86rem;
    }
    .recent-list li:last-child { border-bottom: none; }

    .badge { background: #eef0f4; color: #475569; padding: .18rem .6rem; border-radius: 999px; font-size: .68rem; font-weight: 600; white-space: nowrap; text-transform: capitalize; }
    .badge.new { background: #e0e7ff; color: #4338ca; }
    .badge.converted, .badge.approved { background: #dcfce7; color: #15803d; }
    .badge.pending { background: #fef3c7; color: #a16207; }
    .badge.followup { background: #fef3c7; color: #b45309; }
    .badge.overdue { background: #fee2e2; color: #b91c1c; }

    .text-small { font-size: .78rem; color: var(--muted); }
    .loading-spinner { display: flex; justify-content: center; align-items: center; height: 90px; color: #adb5bd; gap: .5rem; }
    .error { color: #dc2626; padding: 1rem; text-align: center; }

    @media (max-width: 600px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: .75rem; }
        .stat-card { padding: .8rem; gap: .6rem; flex-direction: column; align-items: flex-start; }
        .stat-value { font-size: 1.25rem; }
        .chart-container { height: 240px; }
        .dash-hero { padding: 1.1rem; }
    }
</style>

<div class="dashboard-container">
    <div class="dash-hero">
        <div style="position:relative;z-index:1;">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?> 👋</h2>
            <p>Here is what is happening at Perfect Institute today.</p>
        </div>
        <span class="dash-date"><i class="far fa-calendar"></i> <?php echo date('l, d M Y'); ?></span>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="leads.php" class="quick-btn"><i class="fas fa-user-plus"></i> Add Lead</a>
        <a href="registrations.php" class="quick-btn"><i class="fas fa-clipboard-list"></i> Registrations</a>
        <a href="finance.php?tab=add" class="quick-btn"><i class="fas fa-plus-circle"></i> Add Expense</a>
        <a href="payments.php" class="quick-btn"><i class="fas fa-credit-card"></i> Payments</a>
        <a href="leads.php?followup=today" class="quick-btn"><i class="fas fa-bell"></i> Today's Follow-ups</a>
    </div>

    <!-- Stats Cards (initially showing a single loading spinner) -->
    <div class="stats-grid" id="stats-cards">
        <div class="stat-card loading-spinner">
            <i class="fas fa-spinner fa-pulse"></i> Loading stats...
        </div>
    </div>

    <!-- Income vs Expense Chart -->
    <div class="chart-card">
        <div class="chart-header"><i class="fas fa-chart-line"></i> Monthly Income vs Expenses (Last 6 Months)</div>
        <div class="chart-container">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>

    <!-- Today's Follow-ups Mini List -->
    <div class="recent-card" id="today-followups-card" style="margin-bottom:1.5rem; display:none;">
        <div class="recent-header"><i class="fas fa-calendar-day"></i> Today's Follow-ups</div>
        <ul class="recent-list" id="today-followups-list"></ul>
    </div>

    <!-- Recent Data Grid -->
    <div class="recent-grid">
        <div class="recent-card">
            <div class="recent-header"><i class="fas fa-users"></i> Recent Leads</div>
            <ul class="recent-list" id="recent-leads">
                <li class="loading-spinner"><i class="fas fa-spinner fa-pulse"></i> Loading...</li>
            </ul>
        </div>
        <div class="recent-card">
            <div class="recent-header"><i class="fas fa-user-graduate"></i> Recent Registrations</div>
            <ul class="recent-list" id="recent-regs">
                <li class="loading-spinner"><i class="fas fa-spinner fa-pulse"></i> Loading...</li>
            </ul>
        </div>
        <div class="recent-card">
            <div class="recent-header"><i class="fas fa-credit-card"></i> Recent Payments</div>
            <ul class="recent-list" id="recent-payments">
                <li class="loading-spinner"><i class="fas fa-spinner fa-pulse"></i> Loading...</li>
            </ul>
        </div>
    </div>
</div>

<script>
// Helper: Format number as Indian currency
function formatMoney(num) {
    return Number(num).toLocaleString('en-IN');
}

// Robust HTML escaping to prevent XSS
function escapeHtml(text) {
    if (text == null) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
        '/': '&#x2F;'
    };
    return String(text).replace(/[&<>"'/]/g, function(m) { return map[m]; });
}

// Fetch and display stats
fetch('?ajax=stats')
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.error) throw new Error(data.error);
        
        const statsHtml = `
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Students</div>
                    <div class="stat-value">${data.totalStudents}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-book"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Courses</div>
                    <div class="stat-value">${data.totalCourses}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-phone-alt"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Leads</div>
                    <div class="stat-value">${data.totalLeads}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-rupee-sign"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Revenue</div>
                    <div class="stat-value green">₹${formatMoney(data.totalRevenue)}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gray"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Expenses</div>
                    <div class="stat-value">₹${formatMoney(data.totalExpenses)}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon ${data.netProfit >= 0 ? 'green' : 'red'}"><i class="fas fa-chart-line"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Net Profit</div>
                    <div class="stat-value ${data.netProfit >= 0 ? 'green' : 'red'}">₹${formatMoney(data.netProfit)}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Today's Follow-ups</div>
                    <div class="stat-value">${data.todayFollowups}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Overdue Follow-ups</div>
                    <div class="stat-value">${data.overdueFollowups}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Pending Registrations</div>
                    <div class="stat-value">${data.pendingRegs}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-body">
                    <div class="stat-title">Pending Payments</div>
                    <div class="stat-value">${data.pendingPayments}</div>
                </div>
            </div>
        `;
        document.getElementById('stats-cards').innerHTML = statsHtml;
    })
    .catch(err => {
        console.error('Stats fetch failed:', err);
        document.getElementById('stats-cards').innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load statistics</div>';
    });

// Monthly chart
fetch('?ajax=monthly_data')
    .then(res => res.json())
    .then(data => {
        if (data.error) throw new Error(data.error);
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.months,
                datasets: [
                    {
                        label: 'Income (₹)',
                        data: data.income,
                        backgroundColor: '#22c55e',
                        borderRadius: 6
                    },
                    {
                        label: 'Expenses (₹)',
                        data: data.expense,
                        backgroundColor: '#f87171',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: ₹${formatMoney(ctx.raw)}`
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '₹' + formatMoney(value)
                        }
                    }
                }
            }
        });
    })
    .catch(err => console.error('Monthly data fetch failed:', err));

// Recent leads
fetch('?ajax=recent_leads')
    .then(res => res.json())
    .then(leads => {
        let html = '';
        if (leads.length === 0) {
            html = '<li class="text-small">No recent leads</li>';
        } else {
            leads.forEach(l => {
                let statusClass = '';
                if (l.status === 'New') statusClass = 'new';
                else if (l.status === 'Converted') statusClass = 'converted';
                else statusClass = 'pending';

                let followupHtml = '';
                if (l.next_followup_date) {
                    const today = new Date().toISOString().split('T')[0];
                    if (l.next_followup_date === today) {
                        followupHtml = '<span class="badge followup">Today</span>';
                    } else if (l.next_followup_date < today) {
                        followupHtml = '<span class="badge overdue">Overdue</span>';
                    } else {
                        const d = new Date(l.next_followup_date);
                        followupHtml = '<span class="badge">' + d.toLocaleDateString('en-IN', {day:'2-digit', month:'short'}) + '</span>';
                    }
                }
                html += `<li><span>${escapeHtml(l.name)}</span> <span class="badge ${statusClass}">${escapeHtml(l.status)}</span> ${followupHtml}</li>`;
            });
        }
        document.getElementById('recent-leads').innerHTML = html;
    })
    .catch(err => {
        console.error('Recent leads fetch failed:', err);
        document.getElementById('recent-leads').innerHTML = '<li class="error">Failed to load</li>';
    });

// Recent registrations
fetch('?ajax=recent_regs')
    .then(res => res.json())
    .then(regs => {
        let html = '';
        if (regs.length === 0) {
            html = '<li class="text-small">No recent registrations</li>';
        } else {
            regs.forEach(r => {
                let statusClass = r.status === 'approved' ? 'approved' : (r.status === 'pending' ? 'pending' : '');
                html += `<li><div><strong>${escapeHtml(r.full_name)}</strong><br><span class="text-small">${escapeHtml(r.course_name)}</span></div> <span class="badge ${statusClass}">${escapeHtml(r.status)}</span></li>`;
            });
        }
        document.getElementById('recent-regs').innerHTML = html;
    })
    .catch(err => {
        console.error('Recent regs fetch failed:', err);
        document.getElementById('recent-regs').innerHTML = '<li class="error">Failed to load</li>';
    });

// Recent payments
fetch('?ajax=recent_payments')
    .then(res => res.json())
    .then(payments => {
        let html = '';
        if (payments.length === 0) {
            html = '<li class="text-small">No recent payments</li>';
        } else {
            payments.forEach(p => {
                let statusClass = p.payment_status === 'approved' ? 'approved' : 'pending';
                html += `<li><span>${escapeHtml(p.full_name)}</span> <span class="badge ${statusClass}">${escapeHtml(p.payment_status)}</span> <span>₹${formatMoney(p.amount)}</span></li>`;
            });
        }
        document.getElementById('recent-payments').innerHTML = html;
    })
    .catch(err => {
        console.error('Recent payments fetch failed:', err);
        document.getElementById('recent-payments').innerHTML = '<li class="error">Failed to load</li>';
    });

// Today's follow-ups mini list
fetch('?ajax=today_followups')
    .then(res => res.json())
    .then(followups => {
        if (followups.length === 0) return;
        let html = '';
        followups.forEach(f => {
            html += `<li><a href="leads.php?edit=${f.id}" style="color:inherit; text-decoration:none;">${escapeHtml(f.name)}</a> <span class="text-small">${escapeHtml(f.phone)}</span></li>`;
        });
        document.getElementById('today-followups-list').innerHTML = html;
        document.getElementById('today-followups-card').style.display = 'block';
    })
    .catch(err => console.error('Today follow-ups fetch failed:', err));
</script>

<?php include 'includes/footer.php'; ?>