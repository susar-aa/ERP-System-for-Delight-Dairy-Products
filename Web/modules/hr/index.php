<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$today = date('Y-m-d');

// --- 1. KPI DATA ---

// Total Active Employees
$emp_sql = "SELECT COUNT(*) as c FROM employees WHERE status='active'";
$total_emp = $conn->query($emp_sql)->fetch_assoc()['c'];

// Attendance Today
$att_sql = "
    SELECT 
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status='leave' THEN 1 ELSE 0 END) as on_leave,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent
    FROM attendance 
    WHERE date = '$today'
";
$att_today = $conn->query($att_sql)->fetch_assoc();

// Last Payroll Cost
$pay_sql = "
    SELECT SUM(net_salary) as total, month_year 
    FROM payroll 
    WHERE month_year = (SELECT MAX(month_year) FROM payroll)
";
$last_payroll = $conn->query($pay_sql)->fetch_assoc();
$payroll_cost = $last_payroll['total'] ?? 0;
$payroll_month = $last_payroll['month_year'] ? date('F Y', strtotime($last_payroll['month_year'])) : 'N/A';

// --- 2. CHART DATA ---

// Chart 1: Attendance Trend (Last 7 Days)
$trend_sql = "
    SELECT date, COUNT(*) as present_count 
    FROM attendance 
    WHERE status = 'present' 
    AND date BETWEEN DATE_SUB('$today', INTERVAL 6 DAY) AND '$today'
    GROUP BY date 
    ORDER BY date ASC
";
$trend_res = $conn->query($trend_sql);
$trend_labels = [];
$trend_data = [];
while($row = $trend_res->fetch_assoc()) {
    $trend_labels[] = date('M d', strtotime($row['date']));
    $trend_data[] = $row['present_count'];
}

// Chart 2: Department Distribution
$dept_sql = "
    SELECT d.name, COUNT(e.id) as count 
    FROM employees e 
    JOIN departments d ON e.department_id = d.id 
    WHERE e.status = 'active' 
    GROUP BY d.id
";
$dept_res = $conn->query($dept_sql);
$dept_labels = [];
$dept_data = [];
while($row = $dept_res->fetch_assoc()) {
    $dept_labels[] = $row['name'];
    $dept_data[] = $row['count'];
}

// Recent Hires
$hires_sql = "SELECT full_name, designation, joined_date FROM employees WHERE status='active' ORDER BY joined_date DESC LIMIT 5";
$recent_hires = $conn->query($hires_sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Overview | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .grid-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; border-left: 4px solid #3498db; }
        .kpi-card h3 { margin: 0 0 5px 0; font-size: 13px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-card .value { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .kpi-card i { font-size: 30px; color: #ecf0f1; }
        
        .kpi-blue { border-color: #3498db; } .kpi-blue i { color: #ebf5fb; }
        .kpi-green { border-color: #27ae60; } .kpi-green i { color: #d5f5e3; }
        .kpi-orange { border-color: #f39c12; } .kpi-orange i { color: #fdebd0; }
        .kpi-purple { border-color: #9b59b6; } .kpi-purple i { color: #f5eef8; }

        /* Quick Links */
        .quick-links { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px; }
        .q-btn { background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #34495e; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; border: 1px solid transparent; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .q-btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-color: #3498db; color: #2980b9; }
        .q-btn i { font-size: 24px; margin-bottom: 10px; color: #3498db; }
        .q-btn span { font-weight: 600; font-size: 13px; }

        /* Charts & Tables Area */
        .dashboard-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .chart-box { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); min-height: 300px; display: flex; flex-direction: column; }
        .chart-header { font-weight: bold; font-size: 16px; margin-bottom: 20px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 10px; border-bottom: 2px solid #eee; color: #7f8c8d; font-size: 12px; text-transform: uppercase; }
        .data-table td { padding: 12px 10px; border-bottom: 1px solid #f9f9f9; font-size: 14px; }
        .join-badge { background: #e8f4fd; color: #3498db; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <h2 style="margin:0;">HR Overview</h2>
            <p style="color:#777; margin:5px 0 0 0;">Workforce analytics and daily status.</p>
        </div>

        <!-- 1. QUICK LINKS -->
        <div class="quick-links">
            <a href="manage_employees.php" class="q-btn">
                <i class="fas fa-users"></i>
                <span>Employees</span>
            </a>
            <a href="mark_attendance.php" class="q-btn">
                <i class="fas fa-clock"></i>
                <span>Mark Attendance</span>
            </a>
            <a href="salary_advance.php" class="q-btn">
                <i class="fas fa-hand-holding-usd"></i>
                <span>Salary Advance</span>
            </a>
            <a href="payroll.php" class="q-btn">
                <i class="fas fa-money-check-alt"></i>
                <span>Payroll</span>
            </a>
            <a href="manage_employees.php?action=new" class="q-btn">
                <i class="fas fa-user-plus"></i>
                <span>Add Employee</span>
            </a>
        </div>

        <!-- 2. KPI CARDS -->
        <div class="grid-container">
            <div class="kpi-card kpi-blue">
                <div>
                    <h3>Total Active Staff</h3>
                    <div class="value"><?php echo $total_emp; ?></div>
                </div>
                <i class="fas fa-id-badge" style="color:#3498db;"></i>
            </div>
            
            <div class="kpi-card kpi-green">
                <div>
                    <h3>Present Today</h3>
                    <div class="value"><?php echo $att_today['present'] ?? 0; ?></div>
                </div>
                <i class="fas fa-check-circle" style="color:#27ae60;"></i>
            </div>

            <div class="kpi-card kpi-orange">
                <div>
                    <h3>On Leave Today</h3>
                    <div class="value"><?php echo $att_today['on_leave'] ?? 0; ?></div>
                </div>
                <i class="fas fa-plane-departure" style="color:#f39c12;"></i>
            </div>

            <div class="kpi-card kpi-purple">
                <div>
                    <h3>Payroll Cost (<?php echo $payroll_month; ?>)</h3>
                    <div class="value" style="font-size:20px;">Rs <?php echo number_format($payroll_cost / 1000, 1); ?>k</div>
                </div>
                <i class="fas fa-file-invoice-dollar" style="color:#9b59b6;"></i>
            </div>
        </div>

        <!-- 3. CHARTS & DATA -->
        <div class="dashboard-row">
            
            <!-- Chart: Attendance Trend -->
            <div class="chart-box" style="flex: 2;">
                <div class="chart-header">Attendance Trend (Last 7 Days)</div>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="attChart"></canvas>
                </div>
            </div>

            <!-- Chart: Dept Distribution -->
            <div class="chart-box" style="flex: 1;">
                <div class="chart-header">Staff by Department</div>
                <div style="height:220px; width:100%; display:flex; justify-content:center;">
                    <canvas id="deptChart"></canvas>
                </div>
            </div>

        </div>
        
        <!-- Recent Hires -->
        <div class="dashboard-row">
             <div class="chart-box" style="height:auto;">
                <div class="chart-header">Recent Joiners</div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Joined Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if($recent_hires->num_rows > 0):
                            while($row = $recent_hires->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['designation']); ?></td>
                            <td><span class="join-badge"><?php echo date('M d, Y', strtotime($row['joined_date'])); ?></span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" style="text-align:center; padding:20px; color:#aaa;">No recent activity.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>

    <!-- Chart Config -->
    <script>
        // 1. Line Chart: Attendance
        const ctxAtt = document.getElementById('attChart').getContext('2d');
        new Chart(ctxAtt, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_labels); ?>,
                datasets: [{
                    label: 'Present Count',
                    data: <?php echo json_encode($trend_data); ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2, 4] } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });

        // 2. Doughnut Chart: Departments
        const ctxDept = document.getElementById('deptChart').getContext('2d');
        new Chart(ctxDept, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($dept_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($dept_data); ?>,
                    backgroundColor: [
                        '#3498db', '#9b59b6', '#f39c12', '#e74c3c', '#2ecc71', '#34495e'
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    </script>
</body>
</html>