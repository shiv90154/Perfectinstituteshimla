<?php
require('../includes/config.php');

// ========================
// GET DASHBOARD STATS
// ========================
function getStats($conn) {
    $stats = [];

    $stats['total_students'] = $conn->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'];
    $stats['total_courses'] = $conn->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];
    $stats['total_registrations'] = $conn->query("SELECT COUNT(*) AS c FROM registrations")->fetch_assoc()['c'];


    return $stats;
}
$stats = getStats($conn);
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    :root {
        --navy: #0a192f;
        --white: #ffffff;
        --red: #e63946;
    }

    body {
        background: #f4f6f8;
        font-family: Arial, sans-serif;
    }

.dashboard-home { padding: 20px; }

.analytics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.stat-card {
    background: var(--white);
    padding: 20px;
    border-radius: 12px;
    display: flex;
    gap: 15px;
    align-items: center;
    border-left: 6px solid var(--red);
    box-shadow: 0px 4px 10px rgba(0,0,0,0.10);
}

.stat-card .stat-icon i {
    font-size: 40px;
    color: var(--navy);
}

.stat-info h3 {
    margin: 0;
    color: var(--navy);
    font-size: 28px;
    font-weight: bold;
}

.stat-info p {
    color: #505c6d;
    margin: 3px 0;
}

.charts-grid {
    margin-top: 40px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
}

.chart-card {
    background: var(--white);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0px 4px 12px rgba(0,0,0,0.10);
}

.chart-card h4 {
    margin-bottom: 10px;
    color: var(--navy);
}

/* Certificate Button */
.cert-btn {
    margin-top: 25px;
    padding: 12px 20px;
    background: var(--navy);
    color: white;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    display: inline-block;
}
.cert-btn:hover {
    background: var(--red);
}

</style>


<div class="dashboard-home">

    <!-- STATS CARDS -->
    <div class="analytics-grid">

        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-info">
                <h3><?= $stats['total_students'] ?></h3>
                <p>Total Students</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-book"></i></div>
            <div class="stat-info">
                <h3><?= $stats['total_courses'] ?></h3>
                <p>Total Courses</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-clipboard-check"></i></div>
            <div class="stat-info">
                <h3><?= $stats['total_registrations'] ?></h3>
                <p>Total Registrations</p>
            </div>
        </div>

        

    </div>

    <!-- CERTIFICATE BUTTON -->
    <a class="cert-btn" href="../admin/certificate_template.php?name=Demo+Student&course=Computer+Basics" target="_blank">
        Generate Sample Certificate
    </a>

    <!-- CHARTS -->
    <div class="charts-grid">
        <div class="chart-card">
            <h4>Monthly Registrations</h4>
            <div id="registrationChart"></div>
        </div>

        <div class="chart-card">
            <h4>Students & Courses Growth</h4>
            <div id="studentsCoursesChart"></div>
        </div>
    </div>

</div>

<script>
// ========================
// LOAD CHART DATA USING AJAX
// ========================
fetch("../admin/get_dashboard_data.php")
    .then(res => res.json())
    .then(data => {

        // Registration Chart (Navy Theme)
        let regChart = new ApexCharts(document.querySelector("#registrationChart"), {
            chart: { type: "line", height: 280 },
            colors: ['#e63946'],  // red
            series: [{ name: "Registrations", data: data.monthly_registrations }],
            xaxis: { categories: data.months },
            stroke: { width: 3 }
        });
        regChart.render();

        // Students & Courses Growth
        let scChart = new ApexCharts(document.querySelector("#studentsCoursesChart"), {
            chart: { type: "bar", height: 280 },
            colors: ['#0a192f', '#e63946'], // navy & red
            series: [
                { name: "Students", data: data.student_growth },
                { name: "Courses", data: data.course_growth }
            ],
            xaxis: { categories: data.months }
        });
        scChart.render();

    });
</script>
